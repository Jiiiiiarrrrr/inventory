<?php
function hrms_feature_init($pdo) {
    try { $pdo->query("ALTER TABLE hrms_users ADD employee_id INT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_employees ADD account_type VARCHAR(30) NOT NULL DEFAULT 'Staff'"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD payroll_status VARCHAR(20) NOT NULL DEFAULT 'Released'"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD payroll_period VARCHAR(30) NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_payroll ADD released_at TIMESTAMP NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->query("ALTER TABLE hrms_applicants ADD staff_password_hash VARCHAR(255) NULL"); } catch (Exception $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        role VARCHAR(30) NULL,
        employee_email VARCHAR(150) NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_interview_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT NOT NULL,
        communication TINYINT NOT NULL DEFAULT 0,
        experience TINYINT NOT NULL DEFAULT 0,
        availability TINYINT NOT NULL DEFAULT 0,
        attitude TINYINT NOT NULL DEFAULT 0,
        remarks TEXT NULL,
        recommendation ENUM('Pending','Hire','Reject') NOT NULL DEFAULT 'Pending',
        scored_by VARCHAR(150) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_applicant_score (applicant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        type ENUM('time_in','time_out') NOT NULL,
        photo_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attendance_employee (employee_id),
        INDEX idx_attendance_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_name VARCHAR(150) NOT NULL,
        leave_type ENUM('Paid','Unpaid') NOT NULL DEFAULT 'Paid',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        days INT NOT NULL DEFAULT 1,
        reason TEXT NOT NULL,
        status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
        admin_comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

    // ===== Finance role & payroll approval flow =====
    // Extend hrms_users.role with the new 'finance' role.
    try {
        $roleCol = $pdo->query("SHOW COLUMNS FROM hrms_users LIKE 'role'")->fetch();
        if ($roleCol && stripos((string)($roleCol['Type'] ?? ''), 'finance') === false) {
            $pdo->exec("ALTER TABLE hrms_users MODIFY role ENUM('superadmin','admin','staff','finance') NOT NULL DEFAULT 'staff'");
        }
    } catch (Exception $e) {}

    // Payroll approval tracking columns.
    $finCols = [
        "finance_comment"  => "TEXT NULL",
        "finance_acted_by" => "VARCHAR(150) NULL",
        "finance_acted_at" => "TIMESTAMP NULL DEFAULT NULL",
        "submitted_at"     => "TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($finCols as $col => $def) {
        try { if (!$pdo->query("SHOW COLUMNS FROM hrms_payroll LIKE '$col'")->fetch()) $pdo->exec("ALTER TABLE hrms_payroll ADD $col $def"); } catch (Exception $e) {}
    }

    // Monthly payroll budget (managed by the Finance role).
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_budget (
        id INT AUTO_INCREMENT PRIMARY KEY,
        month CHAR(7) NOT NULL UNIQUE,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

    // ===== Open positions & slots (public job board on apply.php) =====
    // slots_total is the headcount HR wants for the role; "filled" is
    // computed on read from hrms_applicants (Rejected doesn't count, so a
    // rejected applicant frees the slot back up). linked_role ties each
    // position to the hrms_users.role a hired applicant is provisioned
    // with (see the 'hire' action in api/applicants.php) — today that is
    // always 'staff', since only staff accounts are ever created from a
    // hire; admin/superadmin are never reachable through this pipeline.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            slots_total INT NOT NULL DEFAULT 1,
            is_open TINYINT(1) NOT NULL DEFAULT 1,
            linked_role VARCHAR(30) NOT NULL DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // In case the table already existed from an earlier version without this column.
        try {
            if (!$pdo->query("SHOW COLUMNS FROM hrms_positions LIKE 'linked_role'")->fetch()) {
                $pdo->exec("ALTER TABLE hrms_positions ADD linked_role VARCHAR(30) NOT NULL DEFAULT 'staff'");
            }
        } catch (Exception $e) {}
        // In case the table already existed from an earlier version without
        // a unique key on title (CREATE TABLE IF NOT EXISTS is a no-op on an
        // existing table, so the UNIQUE above never applies retroactively).
        // Without this, concurrent requests can both pass the empty-table
        // check below and each insert their own Barista/Cashier/Cleaner rows.
        // Done as plain PHP loops (fetch ids, delete by id) rather than one
        // nested SQL statement, since MySQL/MariaDB can reject a DELETE that
        // subqueries the same table it's deleting from, and that failure
        // would otherwise be swallowed silently by the catch below.
        try {
            $dupeCheck = $pdo->query("SELECT title FROM hrms_positions GROUP BY title HAVING COUNT(*) > 1")->fetchAll();
            foreach ($dupeCheck as $dupe) {
                $idStmt = $pdo->prepare("SELECT id FROM hrms_positions WHERE title = ? ORDER BY id ASC");
                $idStmt->execute([$dupe['title']]);
                $ids = $idStmt->fetchAll(PDO::FETCH_COLUMN);
                array_shift($ids); // keep the first (lowest id), delete the rest
                foreach ($ids as $deleteId) {
                    $pdo->prepare("DELETE FROM hrms_positions WHERE id = ?")->execute([$deleteId]);
                }
            }
        } catch (Exception $e) {}
        try {
            $hasUnique = $pdo->query("SHOW INDEX FROM hrms_positions WHERE Key_name = 'title'")->fetch();
            if (!$hasUnique) {
                $pdo->exec("ALTER TABLE hrms_positions ADD UNIQUE KEY `title` (`title`)");
            }
        } catch (Exception $e) {}
        // Seed the three roles the applicant form already offers, if the
        // table was just created / is still empty. INSERT IGNORE (rather
        // than a COUNT(*) === 0 check) means this is safe even if two
        // requests race here at once, as long as the unique key above is
        // in place.
        $count = (int)$pdo->query("SELECT COUNT(*) FROM hrms_positions")->fetchColumn();
        if ($count === 0) {
            $seed = $pdo->prepare("INSERT IGNORE INTO hrms_positions (title, slots_total, linked_role) VALUES (?, ?, 'staff')");
            $seed->execute(['Barista', 10]);
            $seed->execute(['Cashier', 5]);
            $seed->execute(['Cleaner', 5]);
        }
    } catch (Exception $e) {}
}

function current_user_or_fail() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user'])) response(['success'=>false,'message'=>'Login required'],401);
    return $_SESSION['user'];
}
function require_role_any($roles) {
    $u = current_user_or_fail();
    if (!in_array($u['role'], $roles)) response(['success'=>false,'message'=>'Permission denied'],403);
    return $u;
}
function notify_hrms($pdo, $title, $message, $role=null, $email=null, $userId=null) {
    try { $pdo->prepare("INSERT INTO hrms_notifications (user_id, role, employee_email, title, message) VALUES (?, ?, ?, ?, ?)")->execute([$userId,$role,$email,$title,$message]); } catch (Exception $e) {}
}
function employee_for_user($pdo, $user) {
    if (!empty($user['employee_id'])) {
        $st=$pdo->prepare("SELECT * FROM hrms_employees WHERE id=? LIMIT 1"); $st->execute([$user['employee_id']]); $emp=$st->fetch(); if($emp) return $emp;
    }
    if (!empty($user['email'])) {
        $st=$pdo->prepare("SELECT * FROM hrms_employees WHERE email=? LIMIT 1"); $st->execute([$user['email']]); $emp=$st->fetch(); if($emp) return $emp;
    }
    return null;
}