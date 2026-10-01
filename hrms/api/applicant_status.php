<?php
// ===== APPLICANT STATUS API =====
// GET  : application + offer info for the applicant portal.
//        Returns has_signature (whether the offer was e-signed already).
// POST : {action:'e_sign', signature} — logged-in applicant e-signs the
//        published offer. This recorded signature becomes their official
//        signature used later for request-signing security.
//
// Must stay reachable by anonymous visitors — applicant-status.php (the
// public "check by email, no login" page) calls the GET branch below with
// ?email=... and no session. common.php's auth gate would otherwise 401
// that before this file's own (more precise) permission checks ever run;
// e_sign and any session-only lookups already re-check $_SESSION['user']
// themselves further down, so this define doesn't loosen those.
define('HRMS_NO_AUTH_GATE', true);
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";
ini_set('display_errors', '0'); // keep JSON clean
session_start();
$pdo = conn();
hrms_feature_init($pdo);
hrms_ensure_signature_columns($pdo);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $d = getBody();
    if (($d['action'] ?? '') !== 'e_sign') response(['success' => false, 'message' => 'Unknown action'], 400);
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'applicant') {
        response(['success' => false, 'message' => 'Please log in to the applicant portal first.'], 401);
    }
    $email = $_SESSION['user']['email'];

    $stmt = $pdo->prepare("SELECT * FROM hrms_applicants WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $a = $stmt->fetch();
    if (!$a) response(['success' => false, 'message' => 'No application found for this account.'], 404);
    if (empty($a['offer_published_at'])) response(['success' => false, 'message' => 'There is no published offer to sign yet.'], 400);
    if (in_array($a['status'], ['Rejected', 'Hired', 'Converted to Staff'], true)) {
        response(['success' => false, 'message' => 'This application can no longer be e-signed.'], 400);
    }

    $pw = trim((string)($d['password'] ?? ''));
    if ($pw !== '' && strlen($pw) < 6) response(['success' => false, 'message' => 'Your staff password must be at least 6 characters.'], 400);
    $path = hrms_save_registered_signature($d['signature'] ?? '');
    $pdo->prepare("UPDATE hrms_applicants SET registered_signature_path = ? WHERE id = ?")->execute([$path, $a['id']]);
    if ($pw !== '') {
        $pdo->prepare("UPDATE hrms_applicants SET staff_password_hash = ? WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), $a['id']]);
    }
    addLog($a['name'], 'Offer E-Signed', 'Applicant e-signed the published offer (registered signature recorded)');
    notify_hrms($pdo, 'Offer e-signed', $a['name'] . ' e-signed the offer. Their signature is now on file for hiring.', 'admin');
    response(['success' => true, 'message' => 'Signature recorded. This is now your official signature.']);
}

$email = trim($_GET['email'] ?? '');
if ($email === '' && isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'applicant') {
    $email = $_SESSION['user']['email'];
}
if ($email === '') {
    $data = getBody();
    $email = trim($data['email'] ?? '');
}
if ($email === '') response(['success'=>false,'message'=>'Email is required'],400);

// If logged in as applicant, only allow their own email.
if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'applicant' && strtolower($_SESSION['user']['email']) !== strtolower($email)) {
    response(['success'=>false,'message'=>'Permission denied'],403);
}

$stmt = $pdo->prepare("SELECT id,name,first_name,last_name,position,interview_mode,status,stage,created_at AS date,email,
    offer_department,offer_salary,offer_schedule_days,offer_schedule_start,offer_schedule_end,offer_notes,offer_published_at,contract_path,
    (registered_signature_path IS NOT NULL AND registered_signature_path <> '') AS has_signature,
    (SELECT p.slots_total FROM hrms_positions p WHERE p.title = hrms_applicants.position LIMIT 1) AS position_slots_total,
    (SELECT COUNT(*) FROM hrms_applicants a2 WHERE a2.position = hrms_applicants.position AND a2.status <> 'Rejected') AS position_slots_filled
    FROM hrms_applicants WHERE email=? ORDER BY id DESC");
$stmt->execute([$email]);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    // Only meaningful when the position exists in hrms_positions — legacy/
    // ad-hoc position names (not in that table) get nulls, and the
    // frontend just omits the slot line for those.
    if ($r['position_slots_total'] !== null) {
        $r['position_slots_total'] = (int)$r['position_slots_total'];
        $r['position_slots_filled'] = (int)$r['position_slots_filled'];
    }
}
unset($r);

response(['success'=>true,'data'=>$rows]);