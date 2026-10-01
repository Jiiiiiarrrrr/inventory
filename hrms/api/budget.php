<?php
// ===== HRMS BUDGET API (Finance role only) =====
// Finance manages the monthly payroll budget.
//  GET               -> budget rows with payroll usage (released + pending) per month
//  POST action=set   -> {month: 'YYYY-MM', total: 12345, note: 'optional'}  (upsert)
//  POST action=delete-> {month: 'YYYY-MM'}
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Finance role required.']);
    exit;
}

$pdo = conn();
hrms_feature_init($pdo);
$u = $_SESSION['user'];
$method = $_SERVER['REQUEST_METHOD'];

function base_period($period) {
    return trim(preg_replace('/\s*\(.*\)\s*$/', '', (string)$period));
}

function payroll_usage_by_month($pdo) {
    $released = [];
    $pending = [];
    try {
        $rows = $pdo->query("SELECT payroll_period, net_pay, payroll_status FROM hrms_payroll WHERE payroll_period IS NOT NULL")->fetchAll();
    } catch (Exception $e) {
        return [$released, $pending];
    }
    foreach ($rows as $r) {
        $ts = strtotime(base_period($r['payroll_period']));
        if ($ts === false) continue;
        $ym = date('Y-m', $ts);
        $amt = (float)$r['net_pay'];
        if ($r['payroll_status'] === 'Released') {
            $released[$ym] = round(($released[$ym] ?? 0) + $amt, 2);
        } elseif ($r['payroll_status'] === 'Pending Finance') {
            $pending[$ym] = round(($pending[$ym] ?? 0) + $amt, 2);
        }
    }
    return [$released, $pending];
}

if ($method === 'GET') {
    list($released, $pending) = payroll_usage_by_month($pdo);

    try {
        $rows = $pdo->query("SELECT * FROM hrms_budget ORDER BY month DESC LIMIT 24")->fetchAll();
    } catch (Exception $e) {
        $rows = [];
    }

    $data = [];
    foreach ($rows as $b) {
        $ym = $b['month'];
        $used = $released[$ym] ?? 0.0;
        $total = (float)$b['total'];
        $data[] = [
            'month' => $ym,
            'label' => date('F Y', strtotime($ym . '-01')),
            'total' => $total,
            'note' => $b['note'] ?? null,
            'used' => $used,
            'pending' => $pending[$ym] ?? 0.0,
            'remaining' => round($total - $used, 2)
        ];
    }

    // Always include the current month, even if no budget row exists yet.
    $cur = date('Y-m');
    $hasCur = false;
    foreach ($rows as $b) { if ($b['month'] === $cur) { $hasCur = true; break; } }
    if (!$hasCur) {
        $curUsed = $released[$cur] ?? 0.0;
        $data = array_merge([[
            'month' => $cur,
            'label' => date('F Y', strtotime($cur . '-01')),
            'total' => null,
            'note' => null,
            'used' => $curUsed,
            'pending' => $pending[$cur] ?? 0.0,
            'remaining' => null
        ]], $data);
    }

    response(['success' => true, 'current_month' => $cur, 'data' => $data]);
}

if ($method === 'POST') {
    $d = getBody();
    $action = $d['action'] ?? 'set';

    if ($action === 'set') {
        $month = trim((string)($d['month'] ?? ''));
        $total = $d['total'] ?? 0;
        $note = trim((string)($d['note'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) response(['success' => false, 'message' => 'Please pick a valid month.'], 400);
        if (!is_numeric($total) || floatval($total) < 0) response(['success' => false, 'message' => 'Budget total cannot be negative.'], 400);
        $total = round(floatval($total), 2);

        $st = $pdo->prepare("INSERT INTO hrms_budget (month, total, note) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE total = VALUES(total), note = VALUES(note)");
        $st->execute([$month, $total, $note !== '' ? $note : NULL]);

        addLog($u['name'] ?? 'Finance', 'Budget Updated', "Set {$month} payroll budget to ₱" . number_format($total, 2));
        response(['success' => true, 'message' => 'Budget saved for ' . date('F Y', strtotime($month . '-01')) . '.']);
    }

    if ($action === 'delete') {
        $month = trim((string)($d['month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) response(['success' => false, 'message' => 'Please pick a valid month.'], 400);
        $st = $pdo->prepare("DELETE FROM hrms_budget WHERE month = ?");
        $st->execute([$month]);
        addLog($u['name'] ?? 'Finance', 'Budget Removed', "Removed {$month} payroll budget");
        response(['success' => true, 'message' => 'Budget removed.']);
    }

    response(['success' => false, 'message' => 'Unknown action'], 400);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
