<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/system_init.php";
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "superadmin") response(["success"=>false,"message"=>"Superadmin only"],403);
$pdo=conn(); hrms_init_schema($pdo);

function safeCount($pdo,$sql){
    try {
        $result = $pdo->query($sql);
        return $result ? (int)$result->fetchColumn() : 0;
    } catch(Exception $e) { return 0; }
}

$summary=[
    "pendingApprovals" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Pending'"),
    "approvedRequests" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Approved'"),
    "rejectedRequests" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Rejected'"),
    "cancelledRequests" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Cancelled'"),
    "employees" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_employees"),
    "openApplicants" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_applicants WHERE status NOT IN ('Hired','Rejected')"),
    "payrollReleased" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_payroll WHERE gross > 0"),
    "archivedItems" => safeCount($pdo,"SELECT COUNT(*) FROM hrms_archived_items")
];

$logs=[];
try {
    $logs = $pdo->query("SELECT employee_name AS employee,type,detail,created_at AS date FROM hrms_audit_logs ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch(Exception $e) { $logs = []; }

response(["success"=>true,"summary"=>$summary,"activity"=>$logs]);
