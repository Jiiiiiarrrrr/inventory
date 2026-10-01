<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user'])) die('<p style="font-family:sans-serif;text-align:center;padding:40px">Please log in.</p>');
$pdo = conn(); hrms_feature_init($pdo);
header("Content-Type: text/html; charset=utf-8");
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}.btn-secondary:hover{background:var(--cream)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
.note{display:flex;gap:14px;align-items:flex-start;padding:14px 6px;border-bottom:1px solid var(--line)}
.note:last-child{border-bottom:none}
.note .dot{width:10px;height:10px;border-radius:50%;background:var(--line);margin-top:6px;flex:none}
.note.unread .dot{background:var(--warn)}
.note.unread{background:#fffaf0}
.note .body{flex:1}
.note .title{font-weight:800;font-size:14px;margin-bottom:2px}
.note .msg{font-size:13.5px;color:var(--ink)}
.note .when{font-size:12px;color:var(--muted);margin-top:4px}
.note .mark{border:none;background:#eee6da;color:#8a6f4f;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;flex:none}
.note .mark:hover{background:#e2d5c2}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Notifications</div>
<h1>Notifications</h1>
<p class="sub">Messages addressed to you or your role</p>

<div class="controls">
  <button class="btn btn-primary" onclick="loadNotifications()">Refresh</button>
  <button class="btn btn-secondary" onclick="markAllRead()">Mark all as read</button>
</div>

<div class="card">
  <div id="noteList"><div class="empty">Loading notifications...</div></div>
</div>

<script src="../../alerts.js"></script>
<script>
async function loadNotifications(){
  try {
    const r = await fetch('../api/notifications.php', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('noteList').innerHTML = '<div class="empty">Could not load notifications.</div>'; return; }
    const rows = d.data || [];
    if (rows.length === 0) { document.getElementById('noteList').innerHTML = '<div class="empty">No notifications yet.</div>'; return; }
    let html = '';
    for (const n of rows) {
      const unread = Number(n.is_read) === 0;
      html += `<div class="note ${unread ? 'unread' : ''}">
        <span class="dot"></span>
        <div class="body">
          <div class="title">${esc(n.title)}</div>
          <div class="msg">${esc(n.message)}</div>
          <div class="when">${esc(n.created_at || '')}</div>
        </div>
        ${unread ? `<button class="mark" onclick="markRead(${n.id})">Mark read</button>` : ''}
      </div>`;
    }
    document.getElementById('noteList').innerHTML = html;
    if (window.parent && window.parent.loadBadges) { try { window.parent.loadBadges(); } catch(e) {} }
  } catch(e) { document.getElementById('noteList').innerHTML = '<div class="empty">Could not load notifications.</div>'; }
}
async function markRead(id){
  try {
    await fetch('../api/notifications.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'mark_read',id})});
    loadNotifications();
  } catch(e) { alert('Failed to update notification.'); }
}
async function markAllRead(){
  try {
    await fetch('../api/notifications.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'mark_all_read'})});
    loadNotifications();
  } catch(e) { alert('Failed to update notifications.'); }
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
loadNotifications();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
