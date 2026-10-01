<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
$pdo=conn(); hrms_feature_init($pdo); $u=require_role_any(['admin','superadmin']);
$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){
    $id=intval($_GET['applicant_id']??0); if($id<=0) response(['success'=>false,'message'=>'Applicant ID required'],400);
    $st=$pdo->prepare("SELECT *, ROUND((communication+experience+availability+attitude)/4,2) AS average_score FROM hrms_interview_scores WHERE applicant_id=? LIMIT 1");
    $st->execute([$id]); response(['success'=>true,'data'=>$st->fetch()?:null]);
}
if($method==='POST'||$method==='PUT'){
    $d=getBody(); $id=intval($d['applicant_id']??0); if($id<=0) response(['success'=>false,'message'=>'Applicant ID required'],400);
    $vals=[]; foreach(['communication','experience','availability','attitude'] as $k){$v=intval($d[$k]??0); if($v<1||$v>5) response(['success'=>false,'message'=>'Scores must be 1 to 5'],400); $vals[$k]=$v;}
    $rec=$d['recommendation']??'Pending'; if(!in_array($rec,['Pending','Hire','Reject'])) $rec='Pending'; $remarks=trim($d['remarks']??'');
    $sql="INSERT INTO hrms_interview_scores (applicant_id,communication,experience,availability,attitude,remarks,recommendation,scored_by) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE communication=VALUES(communication), experience=VALUES(experience), availability=VALUES(availability), attitude=VALUES(attitude), remarks=VALUES(remarks), recommendation=VALUES(recommendation), scored_by=VALUES(scored_by)";
    $pdo->prepare($sql)->execute([$id,$vals['communication'],$vals['experience'],$vals['availability'],$vals['attitude'],$remarks,$rec,$u['name']??$u['username']]);
    addLog($u['name']??'HR','Interview Score',"Saved interview score for applicant #$id");
    response(['success'=>true]);
}
response(['success'=>false,'message'=>'Method not allowed'],405);
