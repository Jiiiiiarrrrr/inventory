<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') die('<p style="font-family:sans-serif;text-align:center;padding:40px">Access denied.</p>');
$pdo = conn(); hrms_feature_init($pdo);
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.controls input{padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--card);color:var(--ink);outline:none;font-family:inherit;flex:1;min-width:200px}
.controls input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-dark)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.badge-ok{background:#e2ecdf;color:var(--ok)}
.badge-warn{background:#fff4e0;color:var(--warn)}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Superadmin · Employees</div>
<h1>Employee Management</h1>
<p class="sub">View and manage all employees</p>

<div class="controls">
  <input type="text" id="search" placeholder="Search by name or email...">
</div>

<div class="card">
  <div id="employeeList"><div class="empty">Loading employees...</div></div>
</div>

<script>
async function loadEmployees(){
  const search = document.getElementById('search').value.toLowerCase();
  try {
    const r = await fetch('../api/employees.php', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('employeeList').innerHTML = '<div class="empty">Could not load employees.</div>'; return; }
    const rows = (d.data || []).filter(e => {
      if (search && !(e.name || '').toLowerCase().includes(search) && !(e.email || '').toLowerCase().includes(search)) return false;
      return true;
    });
    if (rows.length === 0) { document.getElementById('employeeList').innerHTML = '<div class="empty">No employees found.</div>'; return; }
    let html = '<table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr></thead><tbody>';
    for (const e of rows) {
      const statusBadge = (e.status || 'active') === 'active' ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-warn">Inactive</span>';
      html += `<tr><td>${esc(e.name)}</td><td>${esc(e.email)}</td><td>${esc(e.role || 'Staff')}</td><td>${esc(e.department || '—')}</td><td>${statusBadge}</td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('employeeList').innerHTML = html;
  } catch(e) { document.getElementById('employeeList').innerHTML = '<div class="empty">Could not load employees.</div>'; }
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
document.getElementById('search').addEventListener('input', loadEmployees);
loadEmployees();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
