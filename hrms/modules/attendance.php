<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user'])) die('<p style="font-family:sans-serif;text-align:center;padding:40px;color:#7a6055">Please log in.</p>');
$pdo = conn(); hrms_feature_init($pdo);
header("Content-Type: text/html; charset=utf-8");
$role = $_SESSION['user']['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.controls input{padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--card);color:var(--ink);outline:none;font-family:inherit}
.controls input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.controls input[type=text]{flex:1;min-width:200px}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-dark)}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}
.btn-secondary:hover{background:var(--cream)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.badge-ok{background:#e2ecdf;color:var(--ok)}
.badge-warn{background:#fff4e0;color:var(--warn)}
.badge-danger{background:#fce4dc;color:var(--danger)}
.thumb{width:56px;height:42px;object-fit:cover;border-radius:6px;border:1px solid var(--line);display:block}
.no-photo{color:var(--muted);font-size:12px;font-style:italic}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>

<div class="eyebrow">Attendance</div>
<h1>Time In / Time Out Records</h1>
<p class="sub">Attendance log for all employees, including captured photo.</p>

<div class="controls">
  <input type="text" id="search" placeholder="Search employee...">
  <input type="date" id="dateFrom">
  <input type="date" id="dateTo">
  <button class="btn btn-primary" onclick="loadAttendance()">Refresh</button>
  <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
</div>

<div class="card">
  <div id="attendanceList"><div class="empty">Loading attendance records...</div></div>
</div>

<script>
const API_BASE = '../api';

function apiFetch(url, opts){
  return fetch(url, opts).then(r=>r.text().then(text=>{
    let data;
    try { data = JSON.parse(text); }
    catch(e){ throw new Error('Server did not return valid JSON (HTTP '+r.status+'). '+text.slice(0,200)); }
    if (!r.ok && !data.success) throw new Error(data.message || ('Request failed (HTTP '+r.status+')'));
    return data;
  }));
}

async function loadAttendance(){
  const search = document.getElementById('search').value.toLowerCase();
  const from = document.getElementById('dateFrom').value;
  const to = document.getElementById('dateTo').value;

  try {
    let url = `${API_BASE}/attendance.php`;
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const qs = params.toString();
    if (qs) url += '?' + qs;

    const d = await apiFetch(url);
    const rows = (d.data || []).filter(r => !search || (r.employee_name || '').toLowerCase().includes(search));

    if (rows.length === 0) {
      document.getElementById('attendanceList').innerHTML = '<div class="empty">No attendance records found.</div>';
      return;
    }

    let html = '<table><thead><tr><th>Employee</th><th>Date</th><th>Type</th><th>Time</th><th>Photo</th></tr></thead><tbody>';
    for (const r of rows) {
      const date = (r.created_at || '').split(' ')[0];
      const time = (r.created_at || '').split(' ')[1] || '';
      const badge = r.type === 'time_in' ? '<span class="badge badge-ok">Time In</span>' : '<span class="badge badge-warn">Time Out</span>';
      const photo = r.photo_path
        ? `<a href="../${esc(r.photo_path)}" target="_blank" rel="noopener"><img class="thumb" src="../${esc(r.photo_path)}" alt="Attendance photo" onerror="this.closest('a').outerHTML='<span class=&quot;no-photo&quot;>Photo missing</span>'"></a>`
        : '<span class="no-photo">No photo</span>';
      html += `<tr><td>${esc(r.employee_name || 'Unknown')}</td><td>${date}</td><td>${badge}</td><td>${time}</td><td>${photo}</td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('attendanceList').innerHTML = html;
  } catch(e) {
    document.getElementById('attendanceList').innerHTML = `<div class="empty">Could not load attendance. ${esc(e.message)}</div>`;
  }
}

function clearFilters(){
  document.getElementById('search').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  loadAttendance();
}

function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}

loadAttendance();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>