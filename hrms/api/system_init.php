<?php
// Shared schema upgrade helper. Include this from API files after common.php.
function hrms_init_schema($pdo) {
    try { $pdo->query("ALTER TABLE hrms_users ADD employee_id INT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            role VARCHAR(30) NULL,
            employee_email VARCHAR(150) NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_approval_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NULL,
            request_type VARCHAR(50) NULL,
            employee VARCHAR(150) NULL,
            old_status VARCHAR(30) NULL,
            new_status VARCHAR(30) NOT NULL,
            comment TEXT NULL,
            acted_by VARCHAR(150) NULL,
            acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_archived_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_type VARCHAR(50) NOT NULL,
            original_id INT NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            role VARCHAR(100) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            removed_by VARCHAR(100) DEFAULT NULL,
            data_json LONGTEXT DEFAULT NULL,
            removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    $payrollCols = [
        "sss" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "philhealth" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "pagibig" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "other_deductions" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "net_pay" => "DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "payroll_period" => "VARCHAR(30) NULL",
        "released_at" => "TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($payrollCols as $col => $def) {
        try { if (!$pdo->query("SHOW COLUMNS FROM hrms_payroll LIKE '$col'")->fetch()) $pdo->exec("ALTER TABLE hrms_payroll ADD $col $def"); } catch (Exception $e) {}
    }

    try { if (!$pdo->query("SHOW COLUMNS FROM hrms_schedules LIKE 'approval_status'")->fetch()) $pdo->exec("ALTER TABLE hrms_schedules ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { if (!$pdo->query("SHOW COLUMNS FROM hrms_schedules LIKE 'approval_comment'")->fetch()) $pdo->exec("ALTER TABLE hrms_schedules ADD approval_comment TEXT NULL"); } catch (Exception $e) {}
}

function notify_user($pdo, $title, $message, $role = null, $email = null, $userId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO hrms_notifications (user_id, role, employee_email, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $role, $email, $title, $message]);
    } catch (Exception $e) {}
}

function add_approval_history($pdo, $requestId, $type, $employee, $oldStatus, $newStatus, $comment = '', $actedBy = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO hrms_approval_history (request_id, request_type, employee, old_status, new_status, comment, acted_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$requestId, $type, $employee, $oldStatus, $newStatus, $comment, $actedBy]);
    } catch (Exception $e) {}
}
