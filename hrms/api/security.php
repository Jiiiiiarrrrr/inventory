<?php
// Security + validation helper. Include after common.php.
if (session_status() === PHP_SESSION_NONE) session_start();

function init_security_schema($pdo) {
    try { $pdo->query("ALTER TABLE hrms_users ADD employee_id INT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD sss DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD philhealth DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD pagibig DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD payroll_period VARCHAR(30) NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD payroll_status VARCHAR(20) NOT NULL DEFAULT 'Released'"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD released_at TIMESTAMP NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_schedules ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_schedules ADD approval_comment TEXT NULL"); } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_notifications (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,role VARCHAR(30) NULL,employee_email VARCHAR(150) NULL,title VARCHAR(150) NOT NULL,message TEXT NOT NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_approval_history (id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NULL,request_type VARCHAR(50) NULL,employee VARCHAR(150) NULL,old_status VARCHAR(30) NULL,new_status VARCHAR(30) NOT NULL,comment TEXT NULL,acted_by VARCHAR(150) NULL,acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_archived_items (id INT AUTO_INCREMENT PRIMARY KEY,item_type VARCHAR(50) NOT NULL,original_id INT NULL,name VARCHAR(150) NOT NULL,email VARCHAR(150) DEFAULT NULL,role VARCHAR(100) DEFAULT NULL,department VARCHAR(100) DEFAULT NULL,reason TEXT DEFAULT NULL,removed_by VARCHAR(100) DEFAULT NULL,data_json LONGTEXT DEFAULT NULL,removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,restored_at TIMESTAMP NULL DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function current_user() { return $_SESSION['user'] ?? null; }
function require_login() { if (!current_user()) response(['success'=>false,'message'=>'Login required'],401); return current_user(); }
function require_roles($roles) { $u=require_login(); if (!in_array($u['role'],$roles)) response(['success'=>false,'message'=>'Permission denied'],403); return $u; }
function is_hash($password) { return preg_match('/^\$2y\$|^\$argon2/i', (string)$password); }
function password_ok($input, $stored) { return is_hash($stored) ? password_verify($input,$stored) : hash_equals((string)$stored,(string)$input); }
function secure_hash($password) { return password_hash($password, PASSWORD_DEFAULT); }
function positive_money($v, $name='Amount') { if ($v === null || $v === '' || !is_numeric($v) || floatval($v) <= 0) response(['success'=>false,'message'=>"$name must be greater than 0"],400); return round(floatval($v),2); }
function non_negative_money($v, $name='Amount') { if ($v === null || $v === '' || !is_numeric($v) || floatval($v) < 0) response(['success'=>false,'message'=>"$name cannot be negative"],400); return round(floatval($v),2); }
function notify_user($pdo,$title,$message,$role=null,$email=null,$userId=null){ try{$pdo->prepare("INSERT INTO hrms_notifications (user_id,role,employee_email,title,message) VALUES (?,?,?,?,?)")->execute([$userId,$role,$email,$title,$message]);}catch(Exception $e){} }
function history_row($pdo,$requestId,$type,$employee,$old,$new,$comment='',$by=''){ try{$pdo->prepare("INSERT INTO hrms_approval_history (request_id,request_type,employee,old_status,new_status,comment,acted_by) VALUES (?,?,?,?,?,?,?)")->execute([$requestId,$type,$employee,$old,$new,$comment,$by]);}catch(Exception $e){} }
function compute_payroll($gross,$other=0){ $gross=round(floatval($gross),2); $other=round(floatval($other),2); $sss=round($gross*.05,2); $phil=round($gross*.025,2); $pag=round(min($gross,10000)*.02,2); $ded=round($sss+$phil+$pag+$other,2); $net=round($gross-$ded,2); return ['gross'=>$gross,'sss'=>$sss,'philhealth'=>$phil,'pagibig'=>$pag,'other_deductions'=>$other,'deductions'=>$ded,'salary'=>$net,'net_pay'=>$net]; }
