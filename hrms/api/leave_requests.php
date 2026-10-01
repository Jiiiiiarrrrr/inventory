<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
$pdo=conn(); hrms_feature_init($pdo); $u=current_user_or_fail(); $method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){
    if(in_array($u['role'],['admin','superadmin'])){ $rows=$pdo->query("SELECT * FROM hrms_leave_requests ORDER BY created_at DESC LIMIT 200")->fetchAll(); response(['success'=>true,'data'=>$rows]); }
    $emp=employee_for_user($pdo,$u); if(!$emp) response(['success'=>false,'message'=>'No employee profile linked'],403);
    $st=$pdo->prepare("SELECT * FROM hrms_leave_requests WHERE employee_id=? ORDER BY created_at DESC"); $st->execute([$emp['id']]); response(['success'=>true,'data'=>$st->fetchAll()]);
}
if($method==='POST'){
    if($u['role']!=='staff' && $u['role']!=='admin') response(['success'=>false,'message'=>'Only staff/HR employee can request leave'],403);
    $emp=employee_for_user($pdo,$u); if(!$emp) response(['success'=>false,'message'=>'No employee profile linked'],403);
    $d=getBody(); $type=$d['leave_type']??'Paid'; if(!in_array($type,['Paid','Unpaid'])) $type='Paid'; $start=$d['start_date']??''; $end=$d['end_date']??''; $reason=trim($d['reason']??'');
    if(!$start||!$end||$reason==='') response(['success'=>false,'message'=>'Start date, end date, and reason are required'],400);
    if($start>$end) response(['success'=>false,'message'=>'End date must be after start date'],400);
    $ds=DateTime::createFromFormat('Y-m-d',$start); $de=DateTime::createFromFormat('Y-m-d',$end);
    if(!$ds||!$de||$ds->format('Y-m-d')!==$start||$de->format('Y-m-d')!==$end) response(['success'=>false,'message'=>'Invalid date format (use YYYY-MM-DD)'],400);
    $days=$ds->diff($de)->days+1;
    $pdo->prepare("INSERT INTO hrms_leave_requests (employee_id,employee_name,leave_type,start_date,end_date,days,reason) VALUES (?,?,?,?,?,?,?)")->execute([$emp['id'],$emp['name'],$type,$start,$end,$days,$reason]);
    notify_hrms($pdo,'Leave request',$emp['name'].' submitted a '.$type.' leave request.','admin'); notify_hrms($pdo,'Leave request',$emp['name'].' submitted a '.$type.' leave request.','superadmin');
    addLog($emp['name'],'Leave Request',"Submitted $type leave from $start to $end"); response(['success'=>true]);
}
if($method==='PUT'){
    if(!in_array($u['role'],['admin','superadmin'])) response(['success'=>false,'message'=>'Permission denied'],403);
    $d=getBody(); $id=intval($d['id']??0); $status=$d['status']??''; $comment=trim($d['comment']??''); if(!in_array($status,['Approved','Rejected','Cancelled'])) response(['success'=>false,'message'=>'Invalid status'],400);
    $st=$pdo->prepare("SELECT * FROM hrms_leave_requests WHERE id=?"); $st->execute([$id]); $lr=$st->fetch(); if(!$lr) response(['success'=>false,'message'=>'Leave request not found'],404);
    $pdo->prepare("UPDATE hrms_leave_requests SET status=?, admin_comment=? WHERE id=?")->execute([$status,$comment,$id]);
    notify_hrms($pdo,'Leave '.$status,"Your leave request was $status. $comment",null,null,null);
    addLog($lr['employee_name'],'Leave '.$status,"Leave request #$id marked $status"); response(['success'=>true]);
}
response(['success'=>false,'message'=>'Method not allowed'],405);
