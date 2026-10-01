<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') die('<p style="font-family:sans-serif;text-align:center;padding:40px">Access denied.</p>');
$pdo = conn(); hrms_feature_init($pdo);
header("Content-Type: text/html; charset=utf-8");

function safeCount($pdo, $sql) {
    try { $r = $pdo->query($sql); return $r ? (int)$r->fetchColumn() : 0; } catch (Exception $e) { return 0; }
}

$summary = [
    'pendingRequests'  => safeCount($pdo, "SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Pending' AND routed_to='hr'"),
    'approvedRequests' => safeCount($pdo, "SELECT COUNT(*) FROM hrms_approval_requests WHERE status='Approved'"),
    'employees'        => safeCount($pdo, "SELECT COUNT(*) FROM hrms_employees"),
    'openApplicants'   => safeCount($pdo, "SELECT COUNT(*) FROM hrms_applicants WHERE status NOT IN ('Hired','Rejected')"),
];

// Quick actions: only things this admin can actually act on right now.
$actions = [];

$reqCount = $summary['pendingRequests'];
if ($reqCount > 0) $actions[] = ['icon' => '📄', 'label' => $reqCount . ' request' . ($reqCount === 1 ? '' : 's') . ' to process', 'page' => 'requests'];

$finalNoOffer = safeCount($pdo, "SELECT COUNT(*) FROM hrms_applicants WHERE stage='Final Interview' AND status='Pending' AND offer_published_at IS NULL");
if ($finalNoOffer > 0) $actions[] = ['icon' => '🧑‍💼', 'label' => $finalNoOffer . ' applicant' . ($finalNoOffer === 1 ? '' : 's') . ' at Final Interview — needs an offer', 'page' => 'applicants'];

$readyToHire = safeCount($pdo, "SELECT COUNT(*) FROM hrms_applicants WHERE offer_published_at IS NOT NULL AND status='Pending'");
if ($readyToHire > 0) $actions[] = ['icon' => '✅', 'label' => $readyToHire . ' applicant' . ($readyToHire === 1 ? '' : 's') . ' with a published offer — ready to hire', 'page' => 'applicants'];

// Activity feed: group consecutive same-employee/same-type same-day entries
// so ten "Timed in" rows collapse into one line with a count, instead of
// flooding the feed.
$rawLogs = [];
try {
    $rawLogs = $pdo->query("SELECT employee_name, type, detail, created_at FROM hrms_audit_logs ORDER BY created_at DESC LIMIT 40")->fetchAll();
} catch (Exception $e) {}

$grouped = [];
foreach ($rawLogs as $log) {
    $key = $log['employee_name'] . '|' . $log['type'] . '|' . date('Y-m-d', strtotime($log['created_at']));
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['employee' => $log['employee_name'], 'type' => $log['type'], 'detail' => $log['detail'], 'latest' => $log['created_at'], 'count' => 0];
    }
    $grouped[$key]['count']++;
}
usort($grouped, function($a, $b) { return strtotime($b['latest']) - strtotime($a['latest']); });
$feed = array_slice($grouped, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:14px 16px;box-shadow:var(--shadow)}
.stat .label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.stat .value{font-size:22px;font-weight:800;margin-top:4px}
.ok{color:var(--ok)}.warn{color:var(--warn)}.danger{color:var(--danger)}.muted{color:var(--muted)}
.two-col{display:grid;grid-template-columns:1fr 1.4fr;gap:14px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
.card h2{font-size:14px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:12px}
.action-item{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:var(--cream);cursor:pointer;font-size:13.5px;font-family:inherit;color:var(--ink);margin-bottom:8px;transition:background .15s}
.action-item:last-child{margin-bottom:0}
.action-item:hover{background:#fff;border-color:var(--accent)}
.action-item .icon{font-size:16px;flex-shrink:0}
.action-item .arrow{margin-left:auto;color:var(--muted)}
.empty-actions{color:var(--muted);font-size:13px;padding:8px 0}
.feed-row{display:flex;justify-content:space-between;align-items:baseline;padding:10px 0;border-top:1px solid var(--line);gap:12px}
.feed-row:first-child{border-top:0}
.feed-row .who{font-size:13.5px}
.feed-row .who b{color:var(--ink)}
.feed-row .count-badge{display:inline-block;background:var(--line);color:var(--muted);font-size:10.5px;font-weight:700;border-radius:999px;padding:1px 7px;margin-left:6px}
.feed-row .when{font-size:11.5px;color:var(--muted);white-space:nowrap}
.empty-feed{color:var(--muted);font-size:13px;padding:20px 0;text-align:center}
@media(max-width:800px){.stat-row{grid-template-columns:1fr 1fr}.two-col{grid-template-columns:1fr}}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Admin Dashboard</div>
<h1>Dashboard Summary</h1>
<p class="sub">Quick overview of requests, employees, and applicants.</p>

<div class="stat-row">
  <div class="stat"><div class="label">Pending Requests</div><div class="value warn"><?=$summary['pendingRequests']?></div></div>
  <div class="stat"><div class="label">Approved</div><div class="value ok"><?=$summary['approvedRequests']?></div></div>
  <div class="stat"><div class="label">Employees</div><div class="value"><?=$summary['employees']?></div></div>
  <div class="stat"><div class="label">Open Applicants</div><div class="value"><?=$summary['openApplicants']?></div></div>
</div>

<div class="two-col">
  <div class="card">
    <h2>Needs your attention</h2>
    <?php if (empty($actions)): ?>
      <div class="empty-actions">Nothing needs action right now.</div>
    <?php else: foreach ($actions as $a): ?>
      <button class="action-item" onclick="goTo('<?= htmlspecialchars($a['page']) ?>')">
        <span class="icon"><?= $a['icon'] ?></span>
        <span><?= htmlspecialchars($a['label']) ?></span>
        <span class="arrow">›</span>
      </button>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <h2>Activity feed</h2>
    <?php if (empty($feed)): ?>
      <div class="empty-feed">No activity yet.</div>
    <?php else: foreach ($feed as $f): ?>
      <div class="feed-row">
        <span class="who"><b><?= htmlspecialchars($f['employee']) ?></b> — <?= htmlspecialchars($f['detail']) ?><?php if ($f['count'] > 1): ?><span class="count-badge">×<?= $f['count'] ?></span><?php endif; ?></span>
        <span class="when"><?= date('M d, g:i A', strtotime($f['latest'])) ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
function goTo(page){
  if (window.parent && typeof window.parent.openPage === 'function') {
    window.parent.openPage(page);
  }
}
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>