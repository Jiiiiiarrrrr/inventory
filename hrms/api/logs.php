<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);
$u = require_login();
$method = $_SERVER["REQUEST_METHOD"];

if ($method === 'GET') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $empName = $_GET['employee'] ?? '';

    $sql = "SELECT * FROM hrms_audit_logs WHERE 1=1";
    $params = [];

    if ($from) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $from;
    }
    if ($to) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $to;
    }
    if ($empName) {
        $sql .= " AND employee_name LIKE ?";
        $params[] = "%{$empName}%";
    }
    $sql .= " ORDER BY created_at DESC LIMIT 500";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    response(['success' => true, 'data' => $st->fetchAll()]);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
