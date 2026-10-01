<?php
require_once __DIR__ . "/common.php";
$pdo = conn();
$method = $_SERVER["REQUEST_METHOD"];

function ensureScheduleApprovalColumns($pdo) {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM hrms_schedules LIKE 'approval_status'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE hrms_schedules ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'Pending'");
    } catch (Exception $e) {}
    try {
        $col = $pdo->query("SHOW COLUMNS FROM hrms_schedules LIKE 'approval_comment'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE hrms_schedules ADD approval_comment TEXT NULL");
    } catch (Exception $e) {}
    try {
        $col = $pdo->query("SHOW COLUMNS FROM hrms_schedules LIKE 'rejection_comment'")->fetch();
        if (!$col) $pdo->exec("ALTER TABLE hrms_schedules ADD rejection_comment TEXT NULL");
    } catch (Exception $e) {}
}
ensureScheduleApprovalColumns($pdo);

if ($method === "GET") {
    $employeeId = intval($_GET["employee_id"] ?? 0);
    if ($employeeId > 0) {
        $stmt = $pdo->prepare("SELECT employee_id, days, start, end, approval_status, approval_comment, rejection_comment FROM hrms_schedules WHERE employee_id = ? LIMIT 1");
        $stmt->execute([$employeeId]);
        response(["success" => true, "data" => $stmt->fetch() ?: null]);
    }
    $stmt = $pdo->query("SELECT employee_id, days, start, end, approval_status, approval_comment, rejection_comment FROM hrms_schedules ORDER BY employee_id ASC");
    response(["success" => true, "data" => $stmt->fetchAll()]);
}

$data = getBody();

if ($method === "POST" || $method === "PUT") {
    $action = $data["action"] ?? "save";

    // ---- Superadmin: approve a pending schedule ----
    if ($action === "approve") {
        $employeeId = intval($data["employee_id"] ?? 0);
        if ($employeeId <= 0) response(["success" => false, "message" => "employee_id is required"], 400);

        $find = $pdo->prepare("SELECT id, employee_id FROM hrms_schedules WHERE employee_id = ? LIMIT 1");
        $find->execute([$employeeId]);
        $sched = $find->fetch();
        if (!$sched) response(["success" => false, "message" => "Schedule not found"], 404);

        $pdo->prepare("UPDATE hrms_schedules SET approval_status = 'Approved', rejection_comment = NULL WHERE employee_id = ?")->execute([$employeeId]);

        $emp = $pdo->prepare("SELECT name FROM hrms_employees WHERE id = ?");
        $emp->execute([$employeeId]);
        $empName = $emp->fetchColumn() ?: ("Employee #" . $employeeId);

        addLog($empName, "Schedule Approved", "Superadmin approved schedule for " . $empName);
        response(["success" => true, "approval_status" => "Approved"]);
    }

    // ---- Superadmin: reject a pending schedule (comment required) ----
    if ($action === "reject") {
        $employeeId = intval($data["employee_id"] ?? 0);
        $comment = trim($data["comment"] ?? "");
        if ($employeeId <= 0) response(["success" => false, "message" => "employee_id is required"], 400);
        if ($comment === "") response(["success" => false, "message" => "A comment is required to reject a schedule"], 400);

        $find = $pdo->prepare("SELECT id, employee_id FROM hrms_schedules WHERE employee_id = ? LIMIT 1");
        $find->execute([$employeeId]);
        $sched = $find->fetch();
        if (!$sched) response(["success" => false, "message" => "Schedule not found"], 404);

        $pdo->prepare("UPDATE hrms_schedules SET approval_status = 'Rejected', rejection_comment = ? WHERE employee_id = ?")->execute([$comment, $employeeId]);

        $emp = $pdo->prepare("SELECT name FROM hrms_employees WHERE id = ?");
        $emp->execute([$employeeId]);
        $empName = $emp->fetchColumn() ?: ("Employee #" . $employeeId);

        addLog($empName, "Schedule Rejected", "Superadmin rejected schedule for " . $empName . ": " . $comment);
        response(["success" => true, "approval_status" => "Rejected"]);
    }

    // ---- Admin: create/edit a shift, sends it back to Pending for approval ----
    $employeeId = intval($data["employee_id"] ?? 0);
    $days = trim($data["days"] ?? "");
    $start = trim($data["start"] ?? "");
    $end = trim($data["end"] ?? "");
    $approvalComment = trim($data["approval_comment"] ?? "");

    if ($employeeId <= 0 || $days === "" || $start === "" || $end === "") {
        response(["success" => false, "message" => "All fields are required"], 400);
    }
    if ($approvalComment === "") {
        response(["success" => false, "message" => "Comment is required for superadmin approval"], 400);
    }

    $find = $pdo->prepare("SELECT id, name, role FROM hrms_employees WHERE id = ?");
    $find->execute([$employeeId]);
    $employee = $find->fetch();
    if (!$employee) response(["success" => false, "message" => "Employee not found"], 404);

    $check = $pdo->prepare("SELECT id, approval_status FROM hrms_schedules WHERE employee_id = ? LIMIT 1");
    $check->execute([$employeeId]);
    $existing = $check->fetch();

    if ($existing && strtolower($existing["approval_status"] ?? "") === "approved") {
        response(["success" => false, "message" => "This schedule is already approved and cannot be edited."], 403);
    }

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE hrms_schedules SET days = ?, start = ?, end = ?, approval_status = 'Pending', approval_comment = ?, rejection_comment = NULL WHERE employee_id = ?");
        $stmt->execute([$days, $start, $end, $approvalComment, $employeeId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO hrms_schedules (employee_id, days, start, end, approval_status, approval_comment) VALUES (?, ?, ?, ?, 'Pending', ?)");
        $stmt->execute([$employeeId, $days, $start, $end, $approvalComment]);
    }

    addLog($employee["name"], "Schedule Update", "Sent schedule for superadmin approval: " . $employee["name"] . " - " . $days . " " . $start . "-" . $end);

    $detail = "Schedule approval request for " . $employee["name"] . " (" . ($employee["role"] ?? "Staff") . "): " . $days . " from " . $start . " to " . $end . ". Admin comment: " . $approvalComment;
    $stmt = $pdo->prepare("INSERT INTO hrms_approval_requests (employee_id, kind, employee_name, detail, status) VALUES (?, 'Schedule Change', ?, ?, 'Pending')");
    $stmt->execute([$employeeId, $employee["name"], $detail]);

    response(["success" => true, "approval_status" => "Pending"]);
}

if ($method === "DELETE") {
    $employeeId = intval($data["employee_id"] ?? 0);
    if ($employeeId <= 0) response(["success" => false, "message" => "Employee ID is required"], 400);
    $stmt = $pdo->prepare("DELETE FROM hrms_schedules WHERE employee_id = ? AND approval_status <> 'Approved'");
    $stmt->execute([$employeeId]);
    addLog("Employee #" . $employeeId, "Schedule Update", "Deleted schedule");
    response(["success" => true]);
}

response(["success" => false, "message" => "Method not allowed"], 405);