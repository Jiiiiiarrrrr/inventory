<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user'])) die('<p style="font-family:sans-serif;text-align:center;padding:40px">Please log in.</p>');
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
<title>Archive</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.controls input,.controls select{padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--card);color:var(--ink);outline:none;font-family:inherit}
.controls input:focus,.controls select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.controls input[type=text]{flex:1;min-width:200px}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}.btn-secondary:hover{background:var(--cream)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.badge-archived{background:#f0f0f0;color:#777}
.badge-restored{background:#e2ecdf;color:var(--ok)}
.action-btn{padding:6px 12px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.action-btn.restore{background:var(--ok);color:#fff}
.action-btn:hover{opacity:.85}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">System</div>
<h1>Archive</h1>
<p class="sub">Archived employees, applicants, and items</p>

<div class="controls">
  <input type="text" id="search" placeholder="Search archived items...">
  <select id="typeFilter">
    <option value="">All Types</option>
    <option value="employee">Employees</option>
    <option value="applicant">Applicants</option>
    <option value="item">Items</option>
  </select>
  <button class="btn btn-primary" onclick="loadArchive()">Refresh</button>
  <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
</div>

<div class="card">
  <div id="archiveList"><div class="empty">Loading archive...</div></div>
</div>

<script src="../../alerts.js"></script>
<script>
async function loadArchive(){
  const search = document.getElementById('search').value.toLowerCase();
  const type = document.getElementById('typeFilter').value;
  try {
    const r = await fetch('../api/archive.php', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('archiveList').innerHTML = '<div class="empty">Could not load archive.</div>'; return; }
    const rows = (d.data || []).filter(a => {
      if (search && !(a.name || '').toLowerCase().includes(search)) return false;
      if (type && (a.item_type || '').toLowerCase() !== type) return false;
      return true;
    });
    if (rows.length === 0) { document.getElementById('archiveList').innerHTML = '<div class="empty">No archived items found.</div>'; return; }
    let html = '<table><thead><tr><th>Type</th><th>Name</th><th>Email</th><th>Role</th><th>Reason</th><th>Archived</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    for (const a of rows) {
      const badge = a.restored_at ? '<span class="badge badge-restored">Restored</span>' : '<span class="badge badge-archived">Archived</span>';
      const action = a.restored_at ? '—' : `<button class="action-btn restore" onclick="restoreItem(${a.id})">Restore</button>`;
      html += `<tr><td>${esc(a.item_type)}</td><td>${esc(a.name)}</td><td>${esc(a.email || '—')}</td><td>${esc(a.role || '—')}</td><td>${esc((a.reason||'').substring(0,40))}</td><td>${(a.removed_at||'').split(' ')[0]}</td><td>${badge}</td><td>${action}</td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('archiveList').innerHTML = html;
  } catch(e) { document.getElementById('archiveList').innerHTML = '<div class="empty">Could not load archive.</div>'; }
}
async function restoreItem(id){
  if (!await swalAsk('Restore this item?')) return;
  try {
    await fetch('../api/archive.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'restore',id})});
    loadArchive();
  } catch(e) { alert('Failed to restore.'); }
}
function clearFilters(){
  document.getElementById('search').value = '';
  document.getElementById('typeFilter').value = '';
  loadArchive();
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
document.getElementById('search').addEventListener('input', loadArchive);
document.getElementById('typeFilter').addEventListener('change', loadArchive);
loadArchive();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
