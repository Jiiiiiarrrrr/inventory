<?php
// ===== APPLICANTS API =====
// GET  : list all applicants (admin / superadmin)
// POST JSON  : {action:'accept'|'advance'|'reject'|'hire', id}
// POST multipart: action=publish_offer, id, offer_department, offer_salary,
//                 offer_schedule_days/start/end, offer_notes, contract(file)
// Stage pipeline: Pending -> Accepted -> Initial Interview -> Actual Interview
//                 -> Final Interview -> (offer / hire)
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";
$pdo = conn();
hrms_feature_init($pdo);
hrms_ensure_signature_columns($pdo);
hrms_ensure_applicant_profile_columns($pdo);

$u = require_roles(['admin', 'superadmin']);
$method = $_SERVER['REQUEST_METHOD'];

const APPLICANT_STAGES = ['Pending', 'Accepted', 'Initial Interview', 'Actual Interview', 'Final Interview'];
const HIRE_DEFAULT_PASSWORD = 'staff123';

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM hrms_applicants ORDER BY created_at DESC LIMIT 500")->fetchAll();
    response(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    $d = $isMultipart ? $_POST : getBody();
    $action = $d['action'] ?? '';
    $id = intval($d['id'] ?? 0);
    if (!$id) response(['success' => false, 'message' => 'Applicant id is required'], 400);

    $st = $pdo->prepare("SELECT * FROM hrms_applicants WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) response(['success' => false, 'message' => 'Applicant not found'], 404);

    // ---------- accept for interview ----------
    if ($action === 'accept') {
        if ($a['status'] === 'Rejected') response(['success' => false, 'message' => 'Applicant was already rejected'], 400);
        $pdo->prepare("UPDATE hrms_applicants SET stage = 'Accepted', status = 'Pending' WHERE id = ?")->execute([$id]);
        addLog($a['name'], 'Applicant Accepted', "Accepted for interview ({$a['position']})");
        notify_hrms($pdo, 'Application update', 'You were accepted for an interview at Brew & Co.', null, $a['email']);
        response(['success' => true, 'message' => 'Applicant accepted for interview.']);
    }

    // ---------- advance to next interview stage ----------
    if ($action === 'advance') {
        $cur = $a['stage'] ?? 'Pending';
        $idx = array_search($cur, APPLICANT_STAGES, true);
        if ($idx === false || $idx >= count(APPLICANT_STAGES) - 1) {
            response(['success' => false, 'message' => 'Applicant is already at the final interview stage. Publish an offer or hire instead.'], 400);
        }
        $next = APPLICANT_STAGES[$idx + 1];
        $pdo->prepare("UPDATE hrms_applicants SET stage = ? WHERE id = ?")->execute([$next, $id]);
        addLog($a['name'], 'Applicant Advanced', "Moved to stage: $next");
        notify_hrms($pdo, 'Application update', "Your application moved to: $next.", null, $a['email']);
        response(['success' => true, 'message' => "Moved to $next."]);
    }

    // ---------- reject ----------
    if ($action === 'reject') {
        $pdo->prepare("UPDATE hrms_applicants SET status = 'Rejected' WHERE id = ?")->execute([$id]);
        addLog($a['name'], 'Applicant Rejected', "Rejected for {$a['position']}");
        notify_hrms($pdo, 'Application update', 'Unfortunately your application was not successful this time.', null, $a['email']);
        response(['success' => true, 'message' => 'Applicant rejected.']);
    }

    // ---------- publish offer (multipart, optional contract file) ----------
    if ($action === 'publish_offer') {
        $dept   = trim($d['offer_department'] ?? '');
        $salary = floatval($d['offer_salary'] ?? 0);
        if ($dept === '')   response(['success' => false, 'message' => 'Department is required'], 400);
        if ($salary <= 0)   response(['success' => false, 'message' => 'Monthly salary is required'], 400);
        if ($salary > 1000000000) response(['success' => false, 'message' => 'Monthly salary cannot exceed ₱1,000,000,000 (1 billion).'], 400);
        $contractPath = $a['contract_path'];
        if (isset($_FILES['contract']) && $_FILES['contract']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['contract']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                response(['success' => false, 'message' => 'Contract must be a PDF, DOC, or DOCX file'], 400);
            }
            if ($_FILES['contract']['size'] > 8 * 1024 * 1024) {
                response(['success' => false, 'message' => 'Contract file is too large (max 8MB)'], 400);
            }
            $dir = __DIR__ . '/../uploads/contracts';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $safe = 'contract_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['contract']['tmp_name'], "$dir/$safe")) {
                response(['success' => false, 'message' => 'Failed to save contract file'], 500);
            }
            $contractPath = 'uploads/contracts/' . $safe;
        }
        if (empty($contractPath)) {
            response(['success' => false, 'message' => 'A contract document (PDF, DOC, or DOCX) is required to publish an offer.'], 400);
        }
        // Shift schedule is NOT part of the offer anymore — staff follow the
        // schedule assigned in Schedule Management (superadmin-approved).
        $pdo->prepare("UPDATE hrms_applicants SET offer_department = ?, offer_salary = ?, offer_notes = ?, contract_path = ?, offer_published_at = NOW() WHERE id = ?")
            ->execute([
                $dept, $salary,
                trim($d['offer_notes'] ?? '') ?: null,
                $contractPath, $id,
            ]);
        // If this person is already hired, keep their employee record
        // (contract salary + department) in sync with the offer.
        if (($a['status'] ?? '') === 'Hired') {
            $pdo->prepare("UPDATE hrms_employees SET monthly_salary = ?, department = ? WHERE email = ?")
                ->execute([$salary, $dept, $a['email']]);
        }
        addLog($a['name'], 'Offer Published', "Offer published: $dept, salary $salary");
        notify_hrms($pdo, 'Job offer published', 'Your job offer from Brew & Co. is now available in your applicant portal.', null, $a['email']);
        response(['success' => true, 'message' => 'Offer published.']);
    }

    // ---------- hire: create employee + staff login ----------
    if ($action === 'hire') {
        if (empty($a['offer_published_at'])) {
            response(['success' => false, 'message' => 'Publish an offer first before hiring.'], 400);
        }
        if (empty($a['registered_signature_path'])) {
            response(['success' => false, 'message' => 'The applicant must e-sign the offer in the applicant portal before hiring (their signature is required for request security).'], 400);
        }
        $chk = $pdo->prepare("SELECT id FROM hrms_employees WHERE email = ? LIMIT 1");
        $chk->execute([$a['email']]);
        if ($chk->fetch()) response(['success' => false, 'message' => 'An employee with this email already exists'], 409);

        $ownHash = trim((string)($a['staff_password_hash'] ?? ''));
        $pwHash  = $ownHash !== '' ? $ownHash : secure_hash(HIRE_DEFAULT_PASSWORD);
        $mustChg = $ownHash !== '' ? 0 : 1;
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO hrms_employees (name, email, role, department, position, monthly_salary, schedule_days, schedule_start, schedule_end, status, hire_date, account_type, registered_signature_path)
                           VALUES (?,?,?,?,?,?,?,?,?, 'active', CURDATE(), 'Staff', ?)")
                ->execute([
                    $a['name'], $a['email'],
                    $a['position'],
                    $a['offer_department'],
                    $a['position'],
                    $a['offer_salary'],
                    $a['offer_schedule_days'] ?? 'Mon,Tue,Wed,Thu,Fri',
                    $a['offer_schedule_start'] ?? '09:00',
                    $a['offer_schedule_end'] ?? '17:00',
                    $a['registered_signature_path'],
                ]);
            $empId = (int)$pdo->lastInsertId();

            $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(explode('@', $a['email'])[0])) ?: 'staff';
            $username = $base; $n = 1;
            while (true) {
                $chk = $pdo->prepare("SELECT id FROM hrms_users WHERE username = ? LIMIT 1");
                $chk->execute([$username]);
                if (!$chk->fetch()) break;
                $username = $base . (++$n);
            }
            $pdo->prepare("INSERT INTO hrms_users (username, full_name, email, password, role, employee_id, must_change_password) VALUES (?,?,?,?, 'staff', ?, ?)")
                ->execute([$username, $a['name'], $a['email'], $pwHash, $empId, $mustChg]);

            $pdo->prepare("UPDATE hrms_applicants SET status = 'Hired', stage = 'Hired' WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            response(['success' => false, 'message' => 'Failed to hire applicant: ' . $e->getMessage()], 500);
        }
        addLog($a['name'], 'Applicant Hired', "Hired as {$a['position']} ({$a['offer_department']})");
        notify_hrms($pdo, 'New hire', $a['name'] . ' was hired as ' . $a['position'] . '.', 'superadmin');
        notify_hrms($pdo, 'Congratulations!', 'You are now hired at Brew & Co. ' . ($ownHash !== '' ? 'Your staff login is ready — use your email (or username ' . $username . ') with the password you set when e-signing your offer.' : 'Your staff login is ready (temporary password: ' . HIRE_DEFAULT_PASSWORD . ').'), null, $a['email']);
        response(['success' => true, 'message' => $ownHash !== '' ? 'Applicant hired. Staff login created: ' . $username . ' / the password they set at e-sign.' : 'Applicant hired. Staff login: ' . $username . ' / ' . HIRE_DEFAULT_PASSWORD . ' (must change on first login).']);
    }

    response(['success' => false, 'message' => 'Unknown action'], 400);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);