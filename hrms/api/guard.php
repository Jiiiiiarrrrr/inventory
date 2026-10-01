<?php
// Unified HRMS security/role/error helper.
// Include this after common.php in API files.
if (session_status() === PHP_SESSION_NONE) session_start();

function hrms_json_error($message, $code = 400) {
    response(["success" => false, "message" => $message], $code);
}
function hrms_user() { return $_SESSION["user"] ?? null; }
function hrms_require_login() {
    if (!hrms_user()) hrms_json_error("Login required", 401);
    return hrms_user();
}
function hrms_require_roles($roles) {
    $u = hrms_require_login();
    if (!in_array($u["role"], $roles)) hrms_json_error("Permission denied", 403);
    return $u;
}
function hrms_is_hash($p) { return preg_match('/^\$2y\$|^\$argon2/i', (string)$p); }
function hrms_password_ok($input, $stored) { return hrms_is_hash($stored) ? password_verify($input, $stored) : hash_equals((string)$stored, (string)$input); }
function hrms_hash($p) { return password_hash($p, PASSWORD_DEFAULT); }
function hrms_money_positive($v, $name = "Amount") {
    if ($v === null || $v === "" || !is_numeric($v) || floatval($v) <= 0) hrms_json_error("$name must be greater than 0", 400);
    return round(floatval($v), 2);
}
function hrms_money_nonnegative($v, $name = "Amount") {
    if ($v === null || $v === "" || !is_numeric($v) || floatval($v) < 0) hrms_json_error("$name cannot be negative", 400);
    return round(floatval($v), 2);
}
function hrms_init($pdo) {
    try { $pdo->query("ALTER TABLE hrms_users ADD employee_id INT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_employees ADD account_type VARCHAR(30) NOT NULL DEFAULT 'Staff'"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_applicants ADD cv_path VARCHAR(255) NULL"); } catch (Exception $e) {}
    $payCols = [
        "daily_rate" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "monthly_gross" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "yearly_gross" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "sss" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "philhealth" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "pagibig" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "late_minutes" => "INT NOT NULL DEFAULT 0",
        "undertime_minutes" => "INT NOT NULL DEFAULT 0",
        "attendance_deduction" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "other_deductions" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "net_pay" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "payroll_period" => "VARCHAR(30) NULL",
        "payroll_status" => "VARCHAR(20) NOT NULL DEFAULT 'Released'",
        "released_at" => "TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($payCols as $col => $def) { try { if (!$pdo->query("SHOW COLUMNS FROM hrms_payroll LIKE '$col'")->fetch()) $pdo->exec("ALTER TABLE hrms_payroll ADD $col $def"); } catch(Exception $e){} }
    try { $pdo->query("ALTER TABLE hrms_schedules ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending'"); } catch(Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_schedules ADD approval_comment TEXT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_notifications (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,role VARCHAR(30) NULL,employee_email VARCHAR(150) NULL,title VARCHAR(150) NOT NULL,message TEXT NOT NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_approval_history (id INT AUTO_INCREMENT PRIMARY KEY,request_id INT NULL,request_type VARCHAR(50) NULL,employee VARCHAR(150) NULL,old_status VARCHAR(30) NULL,new_status VARCHAR(30) NOT NULL,comment TEXT NULL,acted_by VARCHAR(150) NULL,acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_attendance_records (id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,type ENUM('time_in','time_out') NOT NULL,photo_path VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_att_emp(employee_id),INDEX idx_att_date(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_leave_requests (id INT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,employee_name VARCHAR(150) NOT NULL,leave_type ENUM('Paid','Unpaid') NOT NULL DEFAULT 'Paid',start_date DATE NOT NULL,end_date DATE NOT NULL,days INT NOT NULL DEFAULT 1,reason TEXT NOT NULL,status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',admin_comment TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_archived_items (id INT AUTO_INCREMENT PRIMARY KEY,item_type VARCHAR(50) NOT NULL,original_id INT NULL,name VARCHAR(150) NOT NULL,email VARCHAR(150) DEFAULT NULL,role VARCHAR(100) DEFAULT NULL,department VARCHAR(100) DEFAULT NULL,reason TEXT DEFAULT NULL,removed_by VARCHAR(100) DEFAULT NULL,data_json LONGTEXT DEFAULT NULL,removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,restored_at TIMESTAMP NULL DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e) {}
}
function hrms_notify($pdo, $title, $message, $role=null, $email=null, $userId=null) {
    try { $pdo->prepare("INSERT INTO hrms_notifications (user_id,role,employee_email,title,message) VALUES (?,?,?,?,?)")->execute([$userId,$role,$email,$title,$message]); } catch(Exception $e) {}
}
function hrms_audit($employee, $type, $detail) { try { addLog($employee, $type, $detail); } catch(Exception $e) {} }
?>
