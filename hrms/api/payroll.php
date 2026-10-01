<?php
// ===== HRMS PAYROLL API (with Finance approval flow) =====
// Flow:
//   1. Superadmin (or Admin) submits a payroll release  -> status "Pending Finance"
//   2. Finance reviews in the Finance module:
//        - approve -> status "Released"  (employee can now see the payslip)
//        - reject  -> status "Rejected"  (goes back to superadmin with a comment)
//   3. Superadmin can modify a rejected payroll and resubmit it (action=update)
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
$pdo = conn();
hrms_feature_init($pdo);
$u = require_login();
$method = $_SERVER["REQUEST_METHOD"];

// "September 2026 (1st half: 1–15)"  ->  "September 2026"
function base_period($period) {
    return trim(preg_replace('/\s*\(.*\)\s*$/', '', (string)$period));
}

// Bucket released / pending payroll amounts by 'YYYY-MM' — CASH BASIS:
// a release consumes the budget of the month the money actually went out
// (released_at = when Finance approved it), and a pending submission sits in
// the queue of the month it was submitted. So approving a payroll ALWAYS
// decreases the current month's remaining budget immediately, even when the
// payroll period itself is a future month (e.g. Oct 1st half paid in Sept).
// The payroll period month is only a fallback when timestamps are missing.
function payroll_usage_by_month($pdo) {
    $released = [];
    $pending = [];
    try {
        $rows = $pdo->query("SELECT payroll_period, net_pay, payroll_status, released_at, submitted_at, created_at FROM hrms_payroll WHERE payroll_period IS NOT NULL")->fetchAll();
    } catch (Exception $e) {
        return [$released, $pending];
    }
    foreach ($rows as $r) {
        $amt = (float)$r['net_pay'];
        $status = $r['payroll_status'];
        if ($status === 'Released') {
            $when = $r['released_at'] ?: ($r['created_at'] ?: base_period($r['payroll_period']));
        } elseif ($status === 'Pending Finance') {
            $when = $r['submitted_at'] ?: ($r['created_at'] ?: base_period($r['payroll_period']));
        } else {
            continue;
        }
        $ts = strtotime((string)$when);
        if ($ts === false) $ts = strtotime(base_period($r['payroll_period']));
        if ($ts === false) continue;
        $ym = date('Y-m', $ts);
        if ($status === 'Released') {
            $released[$ym] = round(($released[$ym] ?? 0) + $amt, 2);
        } else {
            $pending[$ym] = round(($pending[$ym] ?? 0) + $amt, 2);
        }
    }
    return [$released, $pending];
}

// Snapshot of one month's budget vs payroll usage.
function budget_snapshot($pdo, $ym, $released, $pending) {
    $b = null;
    try {
        $st = $pdo->prepare("SELECT * FROM hrms_budget WHERE month = ? LIMIT 1");
        $st->execute([$ym]);
        $b = $st->fetch();
    } catch (Exception $e) {}
    $used = $released[$ym] ?? 0.0;
    if (!$b) {
        return ['month' => $ym, 'label' => date('F Y', strtotime($ym . '-01')), 'total' => null, 'used' => $used, 'pending' => $pending[$ym] ?? 0.0, 'remaining' => null];
    }
    $total = (float)$b['total'];
    return ['month' => $ym, 'label' => date('F Y', strtotime($ym . '-01')), 'total' => $total, 'used' => $used, 'pending' => $pending[$ym] ?? 0.0, 'remaining' => round($total - $used, 2)];
}

// Contract salary with self-healing fallback: hires made by older versions
// of the hire flow kept the salary only on the published offer
// (hrms_applicants.offer_salary) and left hrms_employees.monthly_salary at 0.
// When that happens, pull the offer salary and fix the employee row for good.
function contract_gross($pdo, $emp) {
    $gross = (float)($emp['monthly_salary'] ?? 0);
    if ($gross <= 0 && !empty($emp['email'])) {
        try {
            $st = $pdo->prepare("SELECT offer_salary FROM hrms_applicants WHERE email = ? AND status = 'Hired' AND offer_salary > 0 ORDER BY offer_published_at DESC LIMIT 1");
            $st->execute([$emp['email']]);
            $row = $st->fetch();
            if ($row) {
                $gross = (float)$row['offer_salary'];
                $pdo->prepare("UPDATE hrms_employees SET monthly_salary = ? WHERE id = ?")->execute([$gross, $emp['id']]);
            }
        } catch (Exception $e) {}
    }
    return $gross;
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // Contract salary lookup (self-healing: falls back to the published
    // offer of hired applicants when monthly_salary was never copied)
    if ($action === 'rate') {
        $empId = intval($_GET['employee_id'] ?? 0);
        if ($empId <= 0) response(['success' => false, 'message' => 'employee_id is required'], 400);
        $st = $pdo->prepare("SELECT id, name, email, monthly_salary FROM hrms_employees WHERE id = ? LIMIT 1");
        $st->execute([$empId]);
        $emp = $st->fetch();
        if (!$emp) response(['success' => false, 'message' => 'Employee not found'], 404);
        response(['success' => true, 'name' => $emp['name'], 'monthly_gross' => contract_gross($pdo, $emp)]);
    }

    // Finance queue (also used for the sidebar badge)
    if ($action === 'queue') {
        if (!in_array($u['role'], ['finance', 'superadmin', 'admin'], true)) {
            response(['success' => false, 'message' => 'Permission denied'], 403);
        }
        try {
            $rows = $pdo->query("SELECT * FROM hrms_payroll ORDER BY FIELD(payroll_status, 'Pending Finance', 'Rejected', 'Released', 'Draft'), created_at DESC")->fetchAll();
        } catch (Exception $e) {
            $rows = $pdo->query("SELECT * FROM hrms_payroll ORDER BY created_at DESC")->fetchAll();
        }
        $pendingCount = 0;
        $pendingNet = 0.0;
        foreach ($rows as $r) {
            if (($r['payroll_status'] ?? '') === 'Pending Finance') {
                $pendingCount++;
                $pendingNet += (float)$r['net_pay'];
            }
        }
        list($released, $pendingMap) = payroll_usage_by_month($pdo);
        response([
            'success' => true,
            'count' => $pendingCount,
            'pending_net' => round($pendingNet, 2),
            'budget' => budget_snapshot($pdo, date('Y-m'), $released, $pendingMap),
            'data' => $rows
        ]);
    }

    // Default list (superadmin payroll records)
    $empId = $_GET['employee_id'] ?? '';
    $period = $_GET['period'] ?? '';
    $sql = "SELECT * FROM hrms_payroll WHERE 1=1";
    $params = [];
    if ($empId) { $sql .= " AND employee_id = ?"; $params[] = (int)$empId; }
    if ($period) { $sql .= " AND payroll_period = ?"; $params[] = $period; }
    $sql .= " ORDER BY created_at DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    response(['success' => true, 'data' => $st->fetchAll()]);
}

if ($method === 'POST') {
    $d = getBody();
    $action = $d['action'] ?? 'create';

    // ---- 1) Superadmin submits a payroll release (now goes to Finance first) ----
    if ($action === 'create') {
        if (!in_array($u['role'], ['admin', 'superadmin'])) response(['success' => false, 'message' => 'Permission denied'], 403);

        $empId = intval($d['employee_id'] ?? 0);
        $month = trim($d['month'] ?? '');
        $periodType = in_array($d['period_type'] ?? '', ['full', 'first_half', 'second_half'], true) ? $d['period_type'] : 'full';
        $otherDeductions = non_negative_money($d['other_deductions'] ?? 0, 'Other deductions');

        if ($empId <= 0) response(['success' => false, 'message' => 'Please select an employee'], 400);
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) response(['success' => false, 'message' => 'Please pick a valid month'], 400);

        $st = $pdo->prepare("SELECT id, name, email, monthly_salary FROM hrms_employees WHERE id = ? LIMIT 1");
        $st->execute([$empId]);
        $emp = $st->fetch();
        if (!$emp) response(['success' => false, 'message' => 'Employee not found'], 404);

        $monthlyGross = contract_gross($pdo, $emp);
        if ($monthlyGross <= 0) response(['success' => false, 'message' => 'This employee has no contract salary on file. Set their monthly salary first.'], 400);

        $fraction = $periodType === 'full' ? 1 : 0.5;
        $gross = round($monthlyGross * $fraction, 2);

        $monthName = date('F Y', strtotime($month . '-01'));
        $suffix = $periodType === 'first_half' ? ' (1st half: 1–15)' : ($periodType === 'second_half' ? ' (2nd half: 16–end)' : '');
        $period = $monthName . $suffix;

        $dup = $pdo->prepare("SELECT id FROM hrms_payroll WHERE employee_id = ? AND payroll_period = ? LIMIT 1");
        $dup->execute([$empId, $period]);
        if ($dup->fetch()) response(['success' => false, 'message' => 'A payroll record for this employee and period already exists.'], 409);

        $calc = compute_payroll($gross, $otherDeductions);

        $ins = $pdo->prepare("INSERT INTO hrms_payroll
            (employee_id, employee_name, period, payroll_period, gross, sss, philhealth, pagibig, other_deductions, deductions, net_pay, payroll_status, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Finance', 'Draft', NOW())");
        $ins->execute([
            $empId, $emp['name'], $period, $period,
            $calc['gross'], $calc['sss'], $calc['philhealth'], $calc['pagibig'],
            $calc['other_deductions'], $calc['deductions'], $calc['net_pay']
        ]);

        notify_user($pdo, 'Payroll Pending Approval', "Payroll for {$emp['name']} ({$period}) was submitted and is awaiting your approval. Net: ₱" . number_format($calc['net_pay'], 2), 'finance');
        addLog($u['name'] ?? 'Superadmin', 'Payroll Submitted', "Submitted payroll for {$emp['name']} ({$period}) to Finance — net ₱" . number_format($calc['net_pay'], 2));

        response(['success' => true, 'data' => $calc, 'period' => $period, 'message' => 'Payroll submitted to Finance for approval.']);
    }

    // ---- 2) Superadmin modifies a REJECTED payroll and resubmits it ----
    if ($action === 'update') {
        if ($u['role'] !== 'superadmin') response(['success' => false, 'message' => 'Only the superadmin can modify a rejected payroll release.'], 403);

        $id = intval($d['id'] ?? 0);
        if ($id <= 0) response(['success' => false, 'message' => 'id is required'], 400);

        $st = $pdo->prepare("SELECT * FROM hrms_payroll WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) response(['success' => false, 'message' => 'Payroll record not found'], 404);
        if (($row['payroll_status'] ?? '') !== 'Rejected') {
            response(['success' => false, 'message' => 'Only payroll rejected by Finance can be modified and resubmitted.'], 409);
        }

        $otherDeductions = non_negative_money($d['other_deductions'] ?? $row['other_deductions'] ?? 0, 'Other deductions');
        $calc = compute_payroll($row['gross'], $otherDeductions);

        $upd = $pdo->prepare("UPDATE hrms_payroll SET
            sss = ?, philhealth = ?, pagibig = ?, other_deductions = ?, deductions = ?, net_pay = ?,
            payroll_status = 'Pending Finance', status = 'Draft',
            finance_comment = NULL, finance_acted_by = NULL, finance_acted_at = NULL,
            released_at = NULL, submitted_at = NOW()
            WHERE id = ?");
        $upd->execute([
            $calc['sss'], $calc['philhealth'], $calc['pagibig'],
            $calc['other_deductions'], $calc['deductions'], $calc['net_pay'], $id
        ]);

        notify_user($pdo, 'Payroll Resubmitted', "Payroll for {$row['employee_name']} ({$row['payroll_period']}) was fixed and resubmitted by the superadmin. New net: ₱" . number_format($calc['net_pay'], 2), 'finance');
        addLog($u['name'] ?? 'Superadmin', 'Payroll Resubmitted', "Resubmitted rejected payroll for {$row['employee_name']} ({$row['payroll_period']}) to Finance — net ₱" . number_format($calc['net_pay'], 2));

        response(['success' => true, 'message' => 'Payroll fixed and resubmitted to Finance.', 'period' => $row['payroll_period']]);
    }

    // ---- 3) Finance approves -> released to the employee ----
    if ($action === 'approve') {
        if ($u['role'] !== 'finance') response(['success' => false, 'message' => 'Only Finance can approve payroll.'], 403);

        $id = intval($d['id'] ?? 0);
        if ($id <= 0) response(['success' => false, 'message' => 'id is required'], 400);

        $st = $pdo->prepare("SELECT * FROM hrms_payroll WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) response(['success' => false, 'message' => 'Payroll record not found'], 404);
        if (($row['payroll_status'] ?? '') !== 'Pending Finance') {
            response(['success' => false, 'message' => 'This payroll is no longer pending approval.'], 409);
        }

        $note = trim((string)($d['comment'] ?? ''));
        $upd = $pdo->prepare("UPDATE hrms_payroll SET
            payroll_status = 'Released', status = 'Released', released_at = NOW(),
            finance_acted_by = ?, finance_acted_at = NOW(), finance_comment = ?
            WHERE id = ?");
        $upd->execute([$u['name'] ?? 'Finance', $note !== '' ? $note : NULL, $id]);

        $empEmail = null;
        try {
            $est = $pdo->prepare("SELECT email FROM hrms_employees WHERE id = ? LIMIT 1");
            $est->execute([$row['employee_id']]);
            $er = $est->fetch();
            if ($er) $empEmail = $er['email'];
        } catch (Exception $e) {}

        notify_user($pdo, 'Payroll Released', "Your payroll for {$row['payroll_period']} was approved by Finance and is now released. Net pay: ₱" . number_format($row['net_pay'], 2), null, $empEmail);
        addLog($u['name'] ?? 'Finance', 'Payroll Approved', "Finance approved payroll for {$row['employee_name']} ({$row['payroll_period']}) — net ₱" . number_format($row['net_pay'], 2));

        response(['success' => true, 'message' => 'Payroll approved and released to the employee.']);
    }

    // ---- 4) Finance rejects -> back to superadmin with a comment ----
    if ($action === 'reject') {
        if ($u['role'] !== 'finance') response(['success' => false, 'message' => 'Only Finance can reject payroll.'], 403);

        $id = intval($d['id'] ?? 0);
        $comment = trim((string)($d['comment'] ?? ''));
        if ($id <= 0) response(['success' => false, 'message' => 'id is required'], 400);
        if ($comment === '') response(['success' => false, 'message' => 'Please enter a reason so the superadmin can fix the payroll.'], 400);

        $st = $pdo->prepare("SELECT * FROM hrms_payroll WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) response(['success' => false, 'message' => 'Payroll record not found'], 404);
        if (($row['payroll_status'] ?? '') !== 'Pending Finance') {
            response(['success' => false, 'message' => 'This payroll is no longer pending approval.'], 409);
        }

        $upd = $pdo->prepare("UPDATE hrms_payroll SET
            payroll_status = 'Rejected', status = 'Draft',
            finance_acted_by = ?, finance_acted_at = NOW(), finance_comment = ?
            WHERE id = ?");
        $upd->execute([$u['name'] ?? 'Finance', $comment, $id]);

        notify_user($pdo, 'Payroll Rejected by Finance', "Finance rejected the payroll for {$row['employee_name']} ({$row['payroll_period']}). Reason: {$comment}", 'superadmin');
        addLog($u['name'] ?? 'Finance', 'Payroll Rejected', "Finance rejected payroll for {$row['employee_name']} ({$row['payroll_period']}) — {$comment}");

        response(['success' => true, 'message' => 'Payroll rejected. The superadmin has been notified.']);
    }

    response(['success' => false, 'message' => 'Unknown action'], 400);
}

response(['success' => false, 'message' => 'Method not allowed'], 405);
