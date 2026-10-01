<?php
// ===== HRMS STAFF API =====
// NOTE: this version reads the hrms_ prefixed tables (hrms_employees,
// hrms_schedules, hrms_payroll) to match the current database.
// Payslips only appear here after Finance approves the release
// (payroll_status = 'Released').
require_once __DIR__ . "/common.php"; require_once __DIR__ . "/security.php";
$pdo=conn(); init_security_schema($pdo); $u=require_roles(['staff']);
$emp=null;
if(!empty($u['employee_id'])){
    $s=$pdo->prepare("SELECT id,name,email,role,department FROM hrms_employees WHERE id=? LIMIT 1");
    $s->execute([$u['employee_id']]);$emp=$s->fetch();
}
if(!$emp){
    $s=$pdo->prepare("SELECT id,name,email,role,department FROM hrms_employees WHERE email=? LIMIT 1");
    $s->execute([$u['email']]);$emp=$s->fetch();
}
if(!$emp)response(['success'=>false,'message'=>'No employee profile linked'],403);
$s=$pdo->prepare("SELECT employee_id,days,start,end,approval_status FROM hrms_schedules WHERE employee_id=? AND approval_status='Approved' ORDER BY id DESC LIMIT 1");
$s->execute([$emp['id']]);$schedule=$s->fetch();
$s=$pdo->prepare("SELECT * FROM hrms_payroll WHERE employee_id=? AND payroll_status='Released' ORDER BY COALESCE(released_at,created_at) DESC,id DESC LIMIT 1");
$s->execute([$emp['id']]);$payroll=$s->fetch();
if($payroll&&floatval($payroll['sss']??0)==0)$payroll=array_merge($payroll,compute_payroll($payroll['gross'],$payroll['other_deductions']??0));
if(!$payroll)$payroll=['gross'=>0,'sss'=>0,'philhealth'=>0,'pagibig'=>0,'other_deductions'=>0,'deductions'=>0,'salary'=>0,'net_pay'=>0];
response(['success'=>true,'employee'=>$emp,'schedule'=>$schedule,'payroll'=>$payroll]);
