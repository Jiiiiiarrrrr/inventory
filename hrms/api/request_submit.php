<?php
// =====================================================================
// Signed request submission endpoint.
// Accepts multipart/form-data from the Requests page and the staff
// portal. A request is ONLY created when BOTH are present:
//   1. letter    - formal letter document (pdf/doc/docx, max 8MB)
//   2. signature - PNG data URL drawn on the signature pad
// Fields: kind (General|Leave), detail, routed_to (hr|superadmin),
//         leave_type, start_date, end_date, reason (optional for Leave)
// =====================================================================
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";

// API endpoint: never let PHP warnings/notices corrupt the JSON body.
ini_set('display_errors', '0');

// If a PHP fatal still occurs, report it as JSON instead of an empty 500.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PHP fatal error: ' . $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')']);
    }
});

$pdo = conn();
hrms_feature_init($pdo);
hrms_ensure_request_doc_columns($pdo);
hrms_ensure_employee_id_nullable($pdo);
hrms_ensure_leave_submitted_by($pdo);

$u = require_roles(['staff', 'admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$emp = employee_for_user($pdo, $u);
// Staff must always have an employee profile; HR/superadmin accounts may
// submit without one (their request is filed under their user name).
if (!$emp && !in_array($u['role'], ['admin', 'superadmin'], true)) {
    response(['success' => false, 'message' => 'No employee profile linked'], 403);
}
$empId   = $emp ? (int)$emp['id'] : null;
$empName = $emp ? (string)$emp['name'] : (string)($u['full_name'] ?? $u['username'] ?? $u['email']);

$kind   = $_POST['kind'] ?? 'General';
$routed = $_POST['routed_to'] ?? 'superadmin';
if (!in_array($routed, ['hr', 'superadmin'], true)) $routed = 'superadmin';
$notifyRole = ($routed === 'hr') ? 'admin' : 'superadmin';
$routeLabel = ($routed === 'hr') ? 'HR' : 'Superadmin';

// --- Required: formal letter + signature BEFORE anything is sent -----
$letter = hrms_save_request_letter($_FILES['letter'] ?? null);
$sig    = hrms_save_request_signature($_POST['signature'] ?? '', $empName);

// --- Security: the drawn signature must MATCH the staff's registered
//     signature (e-signed by applicants before hiring). First-time
//     signers (no registered signature yet) get theirs recorded now. ---
hrms_ensure_signature_columns($pdo);
$regPath = $emp ? (string)($emp['registered_signature_path'] ?? '') : '';
$sigNote = '';
$absReg  = __DIR__ . '/../' . $regPath;
if ($regPath !== '' && !is_file($absReg)) {
    // Registered signature file is gone from disk (e.g. uploads folder was
    // restored/replaced): fall through so this signing re-records it.
    $regPath = '';
}
if ($regPath !== '') {
    if (function_exists('imagecreatefromstring')) {
        $score = hrms_signature_similarity(__DIR__ . '/../' . $sig['path'], $absReg);
        if ($score < HRMS_SIG_THRESHOLD) {
            @unlink(__DIR__ . '/../' . $sig['path']);
            response(['success' => false, 'message' => 'Security check failed: your signature matches only ' . round($score * 100) . '% of your registered signature (minimum ' . round(HRMS_SIG_THRESHOLD * 100) . '%). Please sign again the same way you registered it.'], 400);
        }
        $sigNote = ' Signature verified against your registered signature (' . round($score * 100) . '% match).';
    } else {
        // GD image library not enabled in php.ini — fail open with a note
        // instead of crashing the whole submission.
        $sigNote = ' (Note: server GD image library is not enabled — similarity check was skipped. Enable extension=gd in php.ini to activate it.)';
    }
} elseif ($emp) {
    $pdo->prepare("UPDATE hrms_employees SET registered_signature_path = ?, registered_signature_updated_at = NOW() WHERE id = ?")->execute([$sig['path'], $emp['id']]);
    $sigNote = ' This signature was recorded as your official registered signature.';
} else {
    $sigNote = ' (No employee profile on file — the signature is kept with this request only.)';
}

// --- Security: a selfie from the camera is stored with the request. ---
$signerPhoto = hrms_save_request_signer_photo($_POST['signer_photo'] ?? '');

// The signer is always the authenticated employee (the typed-name field
// was removed in v2 — identity comes from the session, not from input).
$signerName = $empName;

$pdo->beginTransaction();
try {
    if ($kind === 'Leave') {
        $type  = $_POST['leave_type'] ?? 'Paid';
        if (!in_array($type, ['Paid', 'Unpaid'], true)) $type = 'Paid';
        $start = trim($_POST['start_date'] ?? '');
        $end   = trim($_POST['end_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') $reason = trim($_POST['detail'] ?? '');
        if (!$start || !$end || $reason === '') {
            $pdo->rollBack();
            response(['success' => false, 'message' => 'Leave type, dates, and reason are required'], 400);
        }
        if ($start > $end) {
            $pdo->rollBack();
            response(['success' => false, 'message' => 'End date must be after start date'], 400);
        }
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
        $pdo->prepare("INSERT INTO hrms_leave_requests
            (employee_id, employee_name, leave_type, start_date, end_date, days, reason,
             letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path, submitted_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$empId, $empName, $type, $start, $end, $days, $reason,
                       $letter['path'], $letter['name'], $sig['path'], $sig['at'], $sig['by'], $signerName, $signerPhoto, $u['id'] ?? null]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();

        notify_hrms($pdo, 'Leave request', $empName . ' submitted a ' . $type . ' leave request (' . $start . ' to ' . $end . ') with a signed formal letter.', $notifyRole);
        addLog($empName, 'Leave Request', "Submitted $type leave from $start to $end (signed letter: " . $letter['name'] . ")");
        response(['success' => true, 'message' => 'Leave request sent to ' . $routeLabel . ' with your signed letter.' . $sigNote, 'id' => $id, 'source' => 'leave']);
    }

    // General request (default)
    $detail = trim($_POST['detail'] ?? '');
    if ($detail === '') {
        $pdo->rollBack();
        response(['success' => false, 'message' => 'Request detail is required'], 400);
    }
    $pdo->prepare("INSERT INTO hrms_approval_requests
        (employee_id, employee_name, kind, detail, status, routed_to, submitted_by,
         letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path)
        VALUES (?,?,?,?, 'Pending', ?,?,?,?,?,?,?,?,?)")
        ->execute([$empId, $empName, 'General', $detail, $routed, $u['id'] ?? null,
                   $letter['path'], $letter['name'], $sig['path'], $sig['at'], $sig['by'], $signerName, $signerPhoto]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();

    notify_hrms($pdo, 'General request', $empName . ' submitted a general request with a signed formal letter.', $notifyRole);
    addLog($empName, 'General Request', "Submitted general request to $routeLabel (signed letter: " . $letter['name'] . ")");
    response(['success' => true, 'message' => 'Request sent to ' . $routeLabel . ' with your signed letter.' . $sigNote, 'id' => $id, 'source' => 'approval']);
} catch (Exception $e) {
    $pdo->rollBack();
    response(['success' => false, 'message' => 'Failed to submit request: ' . $e->getMessage()], 500);
}
