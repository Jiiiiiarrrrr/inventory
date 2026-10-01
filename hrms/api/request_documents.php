<?php
// =====================================================================
// Request documents helper (formal letter + signature).
// Include after common.php. Used by request_submit.php / requests.php /
// staff_requests.php so every requests endpoint sees the same columns.
// =====================================================================

// Idempotent migration: adds letter + signature columns to both request
// tables. Safe to call on every request (SHOW COLUMNS check first).
function hrms_ensure_request_doc_columns($pdo) {
    $tables = ['hrms_approval_requests', 'hrms_leave_requests'];
    $cols = [
        'letter_path'       => "VARCHAR(255) NULL",
        'letter_name'       => "VARCHAR(255) NULL",
        'signature_path'    => "VARCHAR(255) NULL",
        'signed_at'         => "TIMESTAMP NULL DEFAULT NULL",
        'signed_by'         => "VARCHAR(150) NULL",
        'signer_name'       => "VARCHAR(150) NULL",
        'signer_photo_path' => "VARCHAR(255) NULL",
    ];
    foreach ($tables as $t) {
        foreach ($cols as $col => $def) {
            try {
                if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$col'")->fetch()) {
                    $pdo->exec("ALTER TABLE `$t` ADD `$col` $def");
                }
            } catch (Exception $e) { /* column exists or table missing */ }
        }
    }
}

// Idempotent migration: registered signature columns (applicant e-signs
// the offer before hiring; that signature becomes their official one).
function hrms_ensure_signature_columns($pdo) {
    $pairs = [
        'hrms_employees'  => 'registered_signature_path',
        'hrms_applicants' => 'registered_signature_path',
    ];
    foreach ($pairs as $t => $col) {
        try {
            if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE '$col'")->fetch()) {
                $pdo->exec("ALTER TABLE `$t` ADD `$col` VARCHAR(255) NULL");
            }
        } catch (Exception $e) {}
    }
    // Once-a-month re-registration lock timestamp.
    try {
        if (!$pdo->query("SHOW COLUMNS FROM hrms_employees LIKE 'registered_signature_updated_at'")->fetch()) {
            $pdo->exec("ALTER TABLE hrms_employees ADD registered_signature_updated_at TIMESTAMP NULL DEFAULT NULL");
        }
    } catch (Exception $e) {}
}

// 30-day lock between registered-signature updates.
define('HRMS_SIG_UPDATE_LOCK_DAYS', 30);

// Returns ['can'=>bool,'days_left'=>int] for the once-a-month rule.
function hrms_sig_update_lock($emp) {
    $path = (string)($emp['registered_signature_path'] ?? '');
    $upd  = $emp['registered_signature_updated_at'] ?? null;
    if ($path === '' || !$upd) return ['can' => true, 'days_left' => 0];
    $diff = (time() - strtotime($upd)) / 86400;
    if ($diff >= HRMS_SIG_UPDATE_LOCK_DAYS) return ['can' => true, 'days_left' => 0];
    return ['can' => false, 'days_left' => (int)ceil(HRMS_SIG_UPDATE_LOCK_DAYS - $diff)];
}

// Stores a registered signature PNG (data URL) under uploads/signatures/.
function hrms_save_registered_signature($dataUrl) {
    $dataUrl = trim((string)$dataUrl);
    if ($dataUrl === '' || strpos($dataUrl, 'data:image/png;base64,') !== 0) {
        response(['success' => false, 'message' => 'Please draw your signature first.'], 400);
    }
    $bin = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
    if ($bin === false || strlen($bin) < 64 || strlen($bin) > 1024 * 1024) {
        response(['success' => false, 'message' => 'Signature image is invalid. Please clear and sign again.'], 400);
    }
    $dir = __DIR__ . '/../uploads/signatures';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $safe = 'reg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    if (@file_put_contents("$dir/$safe", $bin) === false) {
        response(['success' => false, 'message' => 'Failed to save signature. Check folder permissions (hrms/uploads/signatures).'], 500);
    }
    return 'uploads/signatures/' . $safe;
}

// Allows HR/superadmin accounts that have no employee profile to submit
// requests: employee_id becomes nullable on both request tables.
function hrms_ensure_employee_id_nullable($pdo) {
    foreach (['hrms_approval_requests', 'hrms_leave_requests'] as $t) {
        $col = $pdo->query("SHOW COLUMNS FROM `$t` LIKE 'employee_id'")->fetch();
        if ($col && ($col['Null'] ?? 'NO') === 'NO') {
            $pdo->exec("ALTER TABLE `$t` MODIFY COLUMN employee_id INT NULL");
        }
    }
}

// Tracks who submitted a leave request so users without an employee row
// (HR/superadmin) still see their own leaves under "My Requests".
function hrms_ensure_leave_submitted_by($pdo) {
    $col = $pdo->query("SHOW COLUMNS FROM hrms_leave_requests LIKE 'submitted_by'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE hrms_leave_requests ADD COLUMN submitted_by INT NULL");
    }
}

// ===== Signature similarity (GD): normalized ink-grid comparison =====
define('HRMS_SIG_THRESHOLD', 0.55);   // 55% match minimum

function hrms_sig_grid($path, $size = 48) {
    if (!is_file($path)) return null;
    $img = @imagecreatefromstring((string)file_get_contents($path));
    if (!$img) return null;
    $w = imagesx($img); $h = imagesy($img);
    $mask = array_fill(0, $w * $h, false);
    $minx = $w; $miny = $h; $maxx = -1; $maxy = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $alpha = ($rgb >> 24) & 127;
            $r = ($rgb >> 16) & 255; $g = ($rgb >> 8) & 255; $b = $rgb & 255;
            $lum = ($r + $g + $b) / 3;
            $ink = ($alpha <= 100) && ($lum < 160);
            $mask[$y * $w + $x] = $ink;
            if ($ink) {
                if ($x < $minx) $minx = $x;
                if ($x > $maxx) $maxx = $x;
                if ($y < $miny) $miny = $y;
                if ($y > $maxy) $maxy = $y;
            }
        }
    }
    imagedestroy($img);
    if ($maxx < 0) return null;
    $gw = $maxx - $minx + 1; $gh = $maxy - $miny + 1;
    $grid = array_fill(0, $size * $size, false);
    for ($gy = 0; $gy < $size; $gy++) {
        for ($gx = 0; $gx < $size; $gx++) {
            $sx = $minx + (int)(($gx + 0.5) * $gw / $size);
            $sy = $miny + (int)(($gy + 0.5) * $gh / $size);
            $sx = max($minx, min($maxx, $sx));
            $sy = max($miny, min($maxy, $sy));
            $grid[$gy * $size + $gx] = $mask[$sy * $w + $sx];
        }
    }
    return $grid;
}

function hrms_signature_similarity($pathA, $pathB, $size = 48) {
    $a = hrms_sig_grid($pathA, $size);
    $b = hrms_sig_grid($pathB, $size);
    if (!$a || !$b) return 0.0;
    $dilate = function ($g) use ($size) {
        $out = $g;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($g[$y * $size + $x]) {
                    for ($dy = -1; $dy <= 1; $dy++) {
                        for ($dx = -1; $dx <= 1; $dx++) {
                            $ny = $y + $dy; $nx = $x + $dx;
                            if ($ny >= 0 && $ny < $size && $nx >= 0 && $nx < $size) $out[$ny * $size + $nx] = true;
                        }
                    }
                }
            }
        }
        return $out;
    };
    $da = $dilate($a); $db = $dilate($b);
    $ca = 0; $cb = 0; $ha = 0; $hb = 0;
    for ($i = 0; $i < $size * $size; $i++) {
        if ($a[$i]) { $ca++; if ($db[$i]) $ha++; }
        if ($b[$i]) { $cb++; if ($da[$i]) $hb++; }
    }
    if (!$ca || !$cb) return 0.0;
    $p = $ha / $ca; $r = $hb / $cb;
    return ($p + $r) > 0 ? (2 * $p * $r) / ($p + $r) : 0.0;
}

// Storage folder for letters + signatures: hrms/uploads/requests/
function hrms_request_upload_dir() {
    $dir = __DIR__ . '/../uploads/requests';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

// Validates + stores the uploaded formal letter. Returns ['path','name'].
// Responds with a 400 JSON error if the letter is missing/invalid.
function hrms_save_request_letter($file) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        response(['success' => false, 'message' => 'A formal letter is required. Please upload your request letter (PDF, DOC, or DOCX) before submitting.'], 400);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Letter upload failed. Please try again.';
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $msg = 'Letter file is too large (max 8MB).';
        }
        response(['success' => false, 'message' => $msg], 400);
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        response(['success' => false, 'message' => 'Letter file is too large (max 8MB).'], 400);
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        response(['success' => false, 'message' => 'The formal letter must be a document file (PDF, DOC, or DOCX).'], 400);
    }
    $safe = 'letter_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], hrms_request_upload_dir() . '/' . $safe)) {
        response(['success' => false, 'message' => 'Failed to save the letter upload. Check folder permissions (hrms/uploads/requests).'], 500);
    }
    return [
        'path' => 'uploads/requests/' . $safe,
        'name' => substr(basename($file['name']), 0, 250),
    ];
}

// Validates + stores the signer's selfie (JPEG/PNG data URL from the camera).
// Returns the relative path. Responds 400 if missing/invalid.
function hrms_save_request_signer_photo($dataUrl) {
    $dataUrl = trim((string)$dataUrl);
    $ext = null;
    if (strpos($dataUrl, 'data:image/jpeg;base64,') === 0) $ext = 'jpg';
    elseif (strpos($dataUrl, 'data:image/png;base64,') === 0) $ext = 'png';
    if ($ext === null) {
        response(['success' => false, 'message' => 'A security photo is required. Please enable your camera and try again.'], 400);
    }
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bin === false || strlen($bin) < 64 || strlen($bin) > 2 * 1024 * 1024) {
        response(['success' => false, 'message' => 'Security photo is invalid. Please retake it.'], 400);
    }
    $safe = 'signer_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@file_put_contents(hrms_request_upload_dir() . '/' . $safe, $bin) === false) {
        response(['success' => false, 'message' => 'Failed to save the security photo. Check folder permissions (hrms/uploads/requests).'], 500);
    }
    return 'uploads/requests/' . $safe;
}

// Normalizes a person name for comparison (case + whitespace insensitive).
function hrms_name_norm($name) {
    return strtolower(preg_replace('/\s+/', ' ', trim((string)$name)));
}

// Validates + stores the drawn signature (PNG data URL from the canvas).
// Returns ['path','by','at']. Responds 400 if missing/invalid.
function hrms_save_request_signature($dataUrl, $signerName) {
    $dataUrl = trim((string)$dataUrl);
    if ($dataUrl === '' || strpos($dataUrl, 'data:image/png;base64,') !== 0) {
        response(['success' => false, 'message' => 'Please draw your signature before the request is sent.'], 400);
    }
    $bin = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
    if ($bin === false || strlen($bin) < 64 || strlen($bin) > 1024 * 1024) {
        response(['success' => false, 'message' => 'Signature image is invalid. Please clear and sign again.'], 400);
    }
    $safe = 'sig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    if (@file_put_contents(hrms_request_upload_dir() . '/' . $safe, $bin) === false) {
        response(['success' => false, 'message' => 'Failed to save the signature. Check folder permissions (hrms/uploads/requests).'], 500);
    }
    return [
        'path' => 'uploads/requests/' . $safe,
        'by'   => (string)$signerName,
        'at'   => date('Y-m-d H:i:s'),
    ];
}

// Applicant profile fields collected on the public application form
// (contact number, birthdate, gender, etc.). Centralized here so every
// file that touches hrms_applicants — the public application endpoint
// and the HR-side applicants API/module — shares one source of truth
// for the column list. Idempotent, safe to call on every request.
function hrms_ensure_applicant_profile_columns($pdo) {
    $cols = [
        'contact_number'       => "VARCHAR(20) NULL",
        'birthdate'            => "DATE NULL",
        'gender'                => "VARCHAR(30) NULL",
        'address'               => "TEXT NULL",
        'civil_status'          => "VARCHAR(20) NULL",
        'preferred_start_date'  => "DATE NULL",
        'referral_source'       => "VARCHAR(50) NULL",
    ];
    foreach ($cols as $col => $def) {
        try {
            if (!$pdo->query("SHOW COLUMNS FROM hrms_applicants LIKE '$col'")->fetch()) {
                $pdo->exec("ALTER TABLE hrms_applicants ADD $col $def");
            }
        } catch (Exception $e) {}
    }
}