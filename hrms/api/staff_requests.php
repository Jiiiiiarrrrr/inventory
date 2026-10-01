<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";
$pdo = conn();
hrms_ensure_request_doc_columns($pdo);

$u = require_roles(['staff', 'admin', 'superadmin']);

// Find employee linked to this account
$emp = null;
if (!empty($u['employee_id'])) {
    $s = $pdo->prepare("SELECT id, name FROM hrms_employees WHERE id = ? LIMIT 1");
    $s->execute([$u['employee_id']]);
    $emp = $s->fetch();
}
if (!$emp) {
    $s = $pdo->prepare("SELECT id, name FROM hrms_employees WHERE email = ? LIMIT 1");
    $s->execute([$u['email']]);
    $emp = $s->fetch();
}
if (!$emp) response(['success' => false, 'message' => 'No employee profile linked'], 403);

$method = $_SERVER["REQUEST_METHOD"];

if ($method === 'GET') {
    $out = [];
    // Leave requests from hrms_leave_requests (with letter + signature)
    $s = $pdo->prepare("SELECT 'Leave' as kind, id, leave_type, CONCAT(leave_type,' leave: ',start_date,' to ',end_date,' - ',reason) as detail, status, DATE(created_at) as date, created_at, letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path, 'leave' as source FROM hrms_leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
    $s->execute([$emp['id']]);
    $out = array_merge($out, $s->fetchAll());

    // General requests from hrms_approval_requests (with letter + signature)
    $s = $pdo->prepare("SELECT kind, id, detail, status, DATE(created_at) as date, created_at, routed_to, letter_path, letter_name, signature_path, signed_at, signed_by, signer_name, signer_photo_path, 'approval' as source FROM hrms_approval_requests WHERE employee_id = ? ORDER BY created_at DESC");
    $s->execute([$emp['id']]);
    $out = array_merge($out, $s->fetchAll());

    response(['success' => true, 'data' => $out]);
}

if ($method === 'POST') {
    // Unsigned JSON submissions are no longer accepted. Requests must
    // carry a formal letter + signature via request_submit.php.
    response(['success' => false, 'message' => 'Requests must include a signed formal letter. Please use the request form: upload your letter, then sign before sending.'], 400);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
