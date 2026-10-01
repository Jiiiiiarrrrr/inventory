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
<title>Requests</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.controls select,.controls input{padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--card);color:var(--ink);outline:none;font-family:inherit}
.controls select:focus,.controls input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}.btn-secondary:hover{background:var(--cream)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
#requestList{overflow-x:auto;max-width:100%}
#requestList table{min-width:1040px}
#requestList th,#requestList td{padding:10px 8px;font-size:12.5px;vertical-align:middle}
#requestList .doc-link{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.list-section td{background:#3b2313;color:#faf6f0;font-weight:900;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;padding:8px 12px;text-align:left}
.badge-pending{background:#fff4e0;color:var(--warn)}
.badge-approved{background:#e2ecdf;color:var(--ok)}
.badge-rejected{background:#fce4dc;color:var(--danger)}
.badge-cancelled{background:#f0f0f0;color:#777}
.action-btn{padding:6px 12px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;margin-right:6px;font-family:inherit}
.action-btn.approve{background:var(--ok);color:#fff}
.action-btn.reject{background:var(--danger);color:#fff}
.action-btn:hover{opacity:.85}
.doc-link{display:inline-block;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:700;background:#eee6da;color:#8a6f4f;text-decoration:none;margin-right:4px}
.doc-link:hover{background:#e2d5c2}
.doc-link.sig{background:#e2ecdf;color:var(--ok)}
.sig-thumb{width:70px;height:34px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:6px;vertical-align:middle;cursor:pointer}
.photo-thumb{width:44px;height:44px;object-fit:cover;border-radius:50%;border:2px solid var(--line);vertical-align:middle;cursor:pointer;background:#fff}
.actions{display:inline-flex;gap:6px;flex-wrap:nowrap;align-items:center;white-space:nowrap}
.actions .action-btn{margin-right:0;min-width:74px}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Superadmin · Requests</div>
<h1>Request Management</h1>
<p class="sub">Review employee requests — open the formal letter and signature before approving</p>

<div class="controls">
  <select id="statusFilter">
    <option value="">All Status</option>
    <option value="Pending">Pending</option>
    <option value="Approved">Approved</option>
    <option value="Rejected">Rejected</option>
    <option value="Cancelled">Cancelled</option>
  </select>
  <button class="btn btn-primary" onclick="loadRequests()">Refresh</button>
</div>

<div class="card">
  <div id="requestList"><div class="empty">Loading requests...</div></div>
</div>

<style>
  .detail-link{display:inline-block;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;background:none;border:none;padding:0;font:inherit;font-size:13px;color:#a9714a;font-weight:700;cursor:pointer;text-decoration:underline;text-decoration-color:rgba(169,113,74,.4);text-align:left}
  .detail-link:hover{color:#7a4c2c;text-decoration-color:#7a4c2c}
  .detail-overlay{position:fixed;inset:0;background:rgba(40,26,18,.55);display:none;align-items:center;justify-content:center;z-index:95;padding:20px}
  .detail-card{background:#fff;border-radius:16px;max-width:680px;width:100%;box-shadow:0 24px 70px rgba(0,0,0,.35);overflow:hidden}
  .detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 24px 14px;border-bottom:1px solid #e8ddd0;background:#faf6f0}
  .detail-eyebrow{font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#a9714a;margin-bottom:4px}
  .detail-head h2{margin:0;font-size:19px;color:#3b2313}
  .detail-x{border:none;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#7a6055;padding:4px 8px;border-radius:8px}
  .detail-x:hover{background:#f0e7da;color:#3b2313}
  .detail-chips{display:flex;flex-wrap:wrap;gap:8px;padding:14px 24px 0}
  .detail-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:800;padding:5px 11px;border-radius:999px;background:#f5ede3;color:#5c4632;border:1px solid #e8ddd0}
  .detail-content{padding:14px 24px 4px}
  .detail-label{font-size:11px;font-weight:900;letter-spacing:.09em;text-transform:uppercase;color:#7a6055;margin-bottom:6px}
  .detail-body{white-space:pre-wrap;background:#faf6f0;border:1px solid #e8ddd0;border-radius:10px;padding:14px 16px;font-size:14px;line-height:1.65;color:#3b2313;max-height:46vh;overflow:auto}
  .detail-rows{border:1px solid #e8ddd0;border-radius:10px;overflow:hidden;background:#fff}
  .detail-row{display:grid;grid-template-columns:150px 1fr;border-top:1px solid #f0e7da}
  .detail-row:first-child{border-top:none}
  .detail-key{background:#faf6f0;padding:10px 14px;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#7a6055}
  .detail-val{padding:10px 14px;font-size:14px;line-height:1.55;color:#3b2313;white-space:pre-wrap}
  .detail-foot{display:flex;justify-content:flex-end;padding:16px 24px 20px}
  .detail-close{font:inherit;font-weight:800;font-size:13.5px;padding:9px 22px;border-radius:8px;border:1px solid #a9714a;background:#a9714a;color:#fff;cursor:pointer}
  .detail-close:hover{opacity:.92}
</style>
<div class="detail-overlay" id="detailOverlay">
  <div class="detail-card">
    <div class="detail-head">
      <div>
        <div class="detail-eyebrow">Request detail</div>
        <h2 id="detailTitle">—</h2>
      </div>
      <button type="button" class="detail-x" id="detailCloseX" title="Close">×</button>
    </div>
    <div class="detail-chips" id="detailChips"></div>
    <div class="detail-content">
      <div class="detail-label">Request information</div>
      <div id="detailBody"></div>
    </div>
    <div class="detail-foot"><button type="button" class="detail-close" id="detailCloseBtn">Close</button></div>
  </div>
</div>

<script src="../../alerts.js"></script>
<script>
/* Clickable request detail: the row shows a one-line ellipsized link;
   clicking it opens a structured modal with the full request text. */
const DETAIL_STORE = {};
let detailSeq = 0;
function detailCell(detail, info){
  const t = (detail || '').trim();
  const short = t.length > 48 ? t.substring(0,48) + '\u2026' : (t || '\u2014');
  const seq = ++detailSeq;
  DETAIL_STORE[seq] = { full: t || '(no detail)', info: info || {} };
  return `<td><button type="button" class="detail-link" title="View full detail" onclick="showDetail(${seq})">${esc(short)}</button></td>`;
}
function statusChipStyle(st){
  const m = { Pending:'background:#fff4e0;color:#96690a;border-color:#e8ddd0', Approved:'background:#e2ecdf;color:#4f7a4a;border-color:#b9d4b4', Rejected:'background:#fce4dc;color:#a23232;border-color:#e5b8ab', Cancelled:'background:#f0f0f0;color:#777;border-color:#ddd' };
  return m[st] || '';
}
function showDetail(seq){
  const d = DETAIL_STORE[seq]; if (!d) return;
  const i = d.info || {};
  const full = d.full;
  const kind = i.kind || '';
  const date = String(i.created_at || i.date || '').split(' ')[0];
  document.getElementById('detailTitle').textContent = kind ? kind + ' request' : 'Request';
  const chips = [];
  const emp = i.employee_name || i.emp;
  if (emp) chips.push('<span class="detail-chip">\ud83d\udc64 ' + esc(emp) + '</span>');
  if (kind) chips.push('<span class="detail-chip">\ud83d\udcc2 ' + esc(kind) + '</span>');
  if (date) chips.push('<span class="detail-chip">\ud83d\udcc5 ' + esc(date) + '</span>');
  if (i.status) chips.push('<span class="detail-chip" style="' + statusChipStyle(i.status) + '">' + esc(i.status) + '</span>');
  document.getElementById('detailChips').innerHTML = chips.join('');
  // One row per input field, depending on request type.
  const rows = [];
  const add = (k, v) => { if (v !== undefined && v !== null && String(v).trim() !== '') rows.push('<div class="detail-row"><div class="detail-key">' + esc(k) + '</div><div class="detail-val">' + esc(v) + '</div></div>'); };
  const kl = kind.toLowerCase();
  if (i.source === 'leave' || kl === 'leave') {
    add('Leave type', i.leave_type);
    add('Start date', i.start_date);
    add('End date', i.end_date);
    add('Days', i.days);
    add('Reason', i.reason || full);
  } else if (kl === 'schedule change') {
    const m = /:\s*(.+?)\s+from\s+(.+?)\s+to\s+(.+?)\.\s*(?:Admin comment:\s*(.*))?$/.exec(full || '');
    if (m) { add('Work days', m[1]); add('Shift', m[2] + ' \u2013 ' + m[3]); if (m[4]) add('Admin comment', m[4]); }
    else add('Detail', full);
  } else {
    add('Detail', full);
  }
  add('Formal letter', i.letter_name || '');
  add('Submitted', date);
  add('Status', i.status);
  document.getElementById('detailBody').innerHTML = '<div class="detail-rows">' + rows.join('') + '</div>';
  document.getElementById('detailOverlay').style.display = 'flex';
}
function closeDetail(){ document.getElementById('detailOverlay').style.display = 'none'; }
document.getElementById('detailCloseBtn').addEventListener('click', closeDetail);
document.getElementById('detailCloseX').addEventListener('click', closeDetail);
document.getElementById('detailOverlay').addEventListener('click', e => { if (e.target.id === 'detailOverlay') closeDetail(); });


function docCell(req){
  if (req.letter_path) return `<a class="doc-link" href="../${esc(req.letter_path)}" target="_blank" rel="noopener">📄 ${esc(req.letter_name || 'Letter')}</a>`;
  return '<span style="color:var(--muted);font-size:12px">—</span>';
}
function sigCell(req){
  if (req.signature_path) return `<img class="sig-thumb" src="../${esc(req.signature_path)}" title="Signed by ${esc(req.signed_by||'')} on ${esc(req.signed_at||'')}" onclick="window.open('../${esc(req.signature_path)}','_blank')">`;
  return '<span style="color:var(--muted);font-size:12px">—</span>';
}
function photoCell(req){
  if (req.signer_photo_path) return `<img class="photo-thumb" src="../${esc(req.signer_photo_path)}" title="Security photo of ${esc(req.signer_name||req.employee_name||'')} at signing" onclick="window.open('../${esc(req.signer_photo_path)}','_blank')">`;
  return '<span style="color:var(--muted);font-size:12px">—</span>';
}
async function loadRequests(){
  const status = document.getElementById('statusFilter').value;
  try {
    let url = '../api/requests.php';
    if (status) url += '?status=' + encodeURIComponent(status);
    const r = await fetch(url, {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('requestList').innerHTML = '<div class="empty">Could not load requests.</div>'; return; }
    const rows = d.data || [];
    if (rows.length === 0) { document.getElementById('requestList').innerHTML = '<div class="empty">No requests found.</div>'; return; }
    let html = '<table><thead><tr><th>Employee</th><th>Type</th><th>Detail</th><th>Formal Letter</th><th>Signature</th><th>Signer Photo</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    const rowHtml = (req) => {
      const badgeClass = 'badge-' + (req.status || 'pending').toLowerCase();
      const actions = req.status === 'Pending' ? `<span class="actions"><button class="action-btn approve" onclick="approveRequest(${req.id},'${esc(req.source)}')">Approve</button><button class="action-btn reject" onclick="rejectRequest(${req.id},'${esc(req.source)}')">Reject</button></span>` : '—';
      return `<tr><td>${esc(req.employee_name)}</td><td>${esc(req.kind)}</td>${detailCell(req.detail, req)}<td>${docCell(req)}</td><td>${sigCell(req)}</td><td>${photoCell(req)}</td><td>${(req.created_at||'').split(' ')[0]}</td><td><span class="badge ${badgeClass}">${req.status}</span></td><td>${actions}</td></tr>`;
    };
    const pendRows = rows.filter(x => x.status === 'Pending');
    const doneRows = rows.filter(x => x.status !== 'Pending');
    if (pendRows.length) html += '<tr class="list-section"><td colspan="9">⏳ Pending — waiting for action</td></tr>' + pendRows.map(rowHtml).join('');
    if (doneRows.length) html += '<tr class="list-section"><td colspan="9">✅ Approved / Rejected — completed</td></tr>' + doneRows.map(rowHtml).join('');
    html += '</tbody></table>';
    document.getElementById('requestList').innerHTML = html;
  } catch(e) { document.getElementById('requestList').innerHTML = '<div class="empty">Could not load requests.</div>'; }
}
async function approveRequest(id, source){
  try {
    await fetch('../api/requests.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'approve',id,source})});
    loadRequests();
  } catch(e) { alert('Failed to approve.'); }
}
async function rejectRequest(id, source){
  try {
    await fetch('../api/requests.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'reject',id,source})});
    loadRequests();
  } catch(e) { alert('Failed to reject.'); }
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
document.getElementById('statusFilter').addEventListener('change', loadRequests);
loadRequests();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
