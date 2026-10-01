<?php
// ===== MY REGISTERED SIGNATURE API =====
// GET  : {has, path, updated_at, can_update, days_left} for the logged-in
//        user's employee record (preview + once-a-month lock info).
// POST : {action:'update', signature} — re-register the signature.
//        Allowed only once every 30 days (HRMS_SIG_UPDATE_LOCK_DAYS),
//        or any time when no registered signature exists yet.
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";
ini_set('display_errors', '0'); // keep JSON clean
$pdo = conn();
hrms_feature_init($pdo);
hrms_ensure_signature_columns($pdo);

$u = require_login();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $emp = employee_for_user($pdo, $u);
    if (!$emp) response(['success' => true, 'has' => false, 'path' => '', 'updated_at' => null, 'can_update' => true, 'days_left' => 0]);
    $path = (string)($emp['registered_signature_path'] ?? '');
    $lock = hrms_sig_update_lock($emp);
    response([
        'success'    => true,
        'has'        => $path !== '',
        'path'       => $path,
        'updated_at' => $emp['registered_signature_updated_at'] ?? null,
        'can_update' => $lock['can'],
        'days_left'  => $lock['days_left'],
    ]);
}

if ($method === 'POST') {
    $d = getBody();
    if (($d['action'] ?? '') !== 'update') response(['success' => false, 'message' => 'Unknown action'], 400);
    $emp = employee_for_user($pdo, $u);
    if (!$emp) response(['success' => false, 'message' => 'No employee profile linked'], 403);

    $lock = hrms_sig_update_lock($emp);
    if (!$lock['can']) {
        response(['success' => false, 'message' => 'You can update your registered signature again in ' . $lock['days_left'] . ' day(s) — updates are allowed once a month.'], 400);
    }

    $path = hrms_save_registered_signature($d['signature'] ?? '');
    $pdo->prepare("UPDATE hrms_employees SET registered_signature_path = ?, registered_signature_updated_at = NOW() WHERE id = ?")
        ->execute([$path, $emp['id']]);
    addLog($emp['name'], 'Signature Updated', 'Registered signature re-registered from profile');
    response(['success' => true, 'message' => 'Registered signature updated. It will be used to verify your future request signatures.']);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
