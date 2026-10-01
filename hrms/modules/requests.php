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
<title>Requests</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
#requestList,#processList{overflow-x:auto;max-width:100%}
#requestList table,#processList table{min-width:960px}
#requestList th,#requestList td,#processList th,#processList td{padding:10px 8px;font-size:12.5px;vertical-align:middle}
#requestList .doc-link,#processList .doc-link{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle}
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
.route-badge{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;background:#eee6da;color:#8a6f4f;margin-left:6px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:var(--muted)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--cream);color:var(--ink);outline:none;font-family:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.form-group textarea{min-height:80px;resize:vertical}
.field-error{color:var(--danger);font-size:12px;margin-top:5px;display:none}
.form-group.has-error input,.form-group.has-error select,.form-group.has-error textarea{border-color:var(--danger)}
.form-group.has-error .field-error{display:block}
.form-group.has-error .dropzone{border-color:var(--danger)}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}.btn-secondary:hover{background:var(--cream)}
.action-btn{padding:6px 12px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;margin-right:6px;font-family:inherit}
.action-btn.approve{background:var(--ok);color:#fff}
.action-btn.reject{background:var(--danger);color:#fff}
.action-btn:hover{opacity:.85}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.alert-success{background:#e2ecdf;color:var(--ok)}
.alert-error{background:#fce4dc;color:var(--danger)}
/* --- Formal letter uploader --- */
.dropzone{border:2px dashed var(--line);border-radius:12px;background:var(--cream);padding:18px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s}
.dropzone:hover,.dropzone.drag{border-color:var(--accent);background:#f4ece1}
.dropzone .dz-icon{font-size:26px;margin-bottom:6px}
.dropzone .dz-main{font-size:14px;font-weight:700;color:var(--ink)}
.dropzone .dz-hint{font-size:12px;color:var(--muted);margin-top:3px}
.file-chip{display:flex;align-items:center;gap:8px;margin-top:10px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 12px;font-size:13px}
.file-chip .fname{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}
.file-chip .fsize{color:var(--muted);font-size:12px}
.file-chip button{border:none;background:#fce4dc;color:var(--danger);border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
/* --- Signature modal --- */
.letter-paper{background:#fffdf8;border:1px solid #e8ddd0;border-radius:10px;padding:20px 24px;font-family:Georgia,serif;font-size:13px;line-height:1.7;color:#22303f;white-space:pre-wrap;max-height:240px;overflow:auto}
.letter-paper.full{max-height:65vh}
.sig-overlay{position:fixed;inset:0;background:rgba(59,35,19,.45);display:flex;align-items:center;justify-content:center;z-index:99;padding:20px}
.sig-box{background:var(--card);border-radius:16px;padding:24px;max-width:560px;width:100%;box-shadow:0 12px 40px rgba(59,35,19,.25)}
.sig-box h3{font-size:18px;font-weight:800;margin-bottom:4px;color:var(--accent)}
.sig-box .sig-sub{color:var(--muted);font-size:13px;margin-bottom:12px}
.sig-as{font-size:13px;margin-bottom:8px;color:var(--ink)}
.sig-as b{color:var(--accent)}
#sigPad{width:100%;height:180px;border:1.5px solid var(--line);border-radius:12px;background:#fff;cursor:crosshair;touch-action:none;display:block}
.sig-hint{font-size:12px;color:var(--muted);margin-top:6px}
.sig-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.doc-link{display:inline-block;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:700;background:#eee6da;color:#8a6f4f;text-decoration:none;margin-right:4px}
.doc-link:hover{background:#e2d5c2}
.doc-link.sig{background:#e2ecdf;color:var(--ok)}
.actions{display:inline-flex;gap:6px;flex-wrap:nowrap;align-items:center;white-space:nowrap}
.actions .action-btn{margin-right:0;min-width:74px}
.sig-grid{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.sig-grid .sig-left{flex:1;min-width:260px}
.cam-box{width:180px;flex:none;text-align:center}
.cam-box video{width:180px;height:135px;object-fit:cover;border-radius:12px;border:1.5px solid var(--line);background:#000;display:block}
.cam-box .cam-label{font-size:11px;color:var(--muted);margin-top:5px;font-weight:700}
.cam-err{color:var(--danger);font-size:12px;margin-top:6px;display:none}
.photo-thumb{width:40px;height:40px;object-fit:cover;border-radius:50%;border:2px solid var(--line);vertical-align:middle;cursor:pointer;background:#fff}
.reg-sig-box{border:1.5px dashed var(--line);border-radius:10px;padding:8px;background:#fff;min-height:74px;display:flex;align-items:center;justify-content:center;font-size:12.5px;color:var(--muted);text-align:center}
.reg-sig-box img{max-height:84px;max-width:100%;object-fit:contain}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Requests</div>
<h1>My Requests</h1>
<p class="sub">Submit and track your requests — every request needs a formal letter and your signature before it is sent</p>

<div class="card">
  <h2 style="font-size:17px;font-weight:800;margin-bottom:16px;color:var(--accent)">Submit New Request</h2>
  <div id="alertBox"></div>
  <form id="requestForm" novalidate>
    <div class="form-group" id="field-type">
      <label>Request Type</label>
      <select id="requestType">
        <option value="General">General Request</option>
        <option value="Leave">Leave Request</option>
      </select>
    </div>
    <div id="leaveFields" style="display:none">
      <div class="form-group">
        <label>Leave Type</label>
        <select id="leaveType">
          <option value="Paid">Paid Leave</option>
          <option value="Unpaid">Unpaid Leave</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" id="field-start">
          <label>Start Date</label>
          <input type="date" id="startDate">
          <div class="field-error">Start date is required.</div>
        </div>
        <div class="form-group" id="field-end">
          <label>End Date</label>
          <input type="date" id="endDate">
          <div class="field-error">End date must be after start date.</div>
        </div>
      </div>
    </div>
    <div class="form-group" id="field-detail">
      <label>Details</label>
      <textarea id="detail" placeholder="Describe your request..."></textarea>
      <div class="field-error">Please enter at least 3 characters.</div>
    </div>
    <div class="form-group" id="field-letter">
      <label>Formal Letter (auto-generated) <span style="color:var(--danger)">*</span></label>
      <div class="letter-paper" id="letterPreview"></div>
      <div style="margin:8px 0 2px">
        <button type="button" class="btn btn-secondary" id="letterViewBtn">👁 View full letter</button>
      </div>
      <p style="color:var(--muted,#7a6055);font-size:12px;margin:6px 0 0">The letter writes itself from your answers above and is saved as a Word document (.docx). When you sign, your signature is placed on the letter above your full name.</p>
    </div>
    
    <div class="form-group">
      <label>Send to</label>
      <select id="routedTo">
        <option value="superadmin">Superadmin</option>
        <option value="hr">HR (Admin)</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary" id="submitBtn">Submit Request → Sign</button>
  </form>
</div>

<div class="card" id="processCard" style="display:none">
  <h2 style="font-size:17px;font-weight:800;margin-bottom:4px;color:var(--accent)">Requests to Process</h2>
  <p class="sub" id="processSub">Pending requests routed to you.</p>
  <div id="processList"><div class="empty">Loading...</div></div>
</div>

<div class="card">
  <h2 style="font-size:17px;font-weight:800;margin-bottom:16px;color:var(--accent)">Request History</h2>
  <div id="requestList"><div class="empty">Loading requests...</div></div>
</div>

<!-- Signature modal: staff must sign before the request is sent -->
<div class="sig-overlay" id="letterModal" style="display:none">
  <div class="sig-box" style="max-width:720px">
    <h3>Formal Letter Preview</h3>
    <div class="sig-sub">This is the letter that will be saved with your request, signature included once you sign.</div>
    <div class="letter-paper full" id="letterPreviewFull"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('letterModal').style.display='none'">Close</button>
    </div>
  </div>
</div>
<div class="sig-overlay" id="sigModal" style="display:none">
  <div class="sig-box">
    <h3>Sign Your Request</h3>
    <p class="sig-sub">Review your attached letter, then draw your signature below. The request is only sent to <b id="sigRoute">Superadmin</b> after you sign.</p>
    <p class="sig-as">Signing as: <b id="sigAs">—</b></p>
    <div class="sig-grid">
      <div class="sig-left">
        <div class="form-group" style="margin-bottom:10px">
          <label>Your registered signature — draw it the same way</label>
          <div class="reg-sig-box" id="regSigBox">Loading…</div>
          <div class="field-error" id="sigMatchError">Your signature does not match your registered signature. Please sign again the same way.</div>
        </div>
        <canvas id="sigPad" width="1000" height="360"></canvas>
        <div class="sig-hint">Draw your signature with your mouse, finger, or stylus.</div>
        <div class="field-error" id="sigError" style="margin-top:8px">Please draw your signature before submitting.</div>
      </div>
      <div class="cam-box">
        <video id="sigCam" autoplay playsinline muted></video>
        <div class="cam-label">📸 Security photo (auto-captured when you sign)</div>
        <div class="cam-err" id="camErr">Camera access is required for signed requests.</div>
      </div>
    </div>
    <div class="sig-actions">
      <button type="button" class="btn btn-primary" id="sigSubmit" disabled>✍️ Sign &amp; Send Request</button>
      <button type="button" class="btn btn-secondary" id="sigClear">Clear Signature</button>
      <button type="button" class="btn btn-secondary" id="sigCancel">Cancel</button>
    </div>
  </div>
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
<script src="../js/letter-gen.js"></script>
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


let CURRENT_ROLE = null;
let CURRENT_USER_NAME = '';
let pendingBody = null;          // validated form fields awaiting signature
let sigStrokes = 0;

function apiFetch(url, opts){
  return fetch(url, opts).then(r=>r.text().then(text=>{
    let data;
    try { data = JSON.parse(text); }
    catch(e){ throw new Error('Server did not return valid JSON (HTTP '+r.status+'). '+text.slice(0,200)); }
    if (!r.ok && !data.success) throw new Error(data.message || ('Request failed (HTTP '+r.status+')'));
    return data;
  }));
}

document.getElementById('requestType').addEventListener('change', function(){
  document.getElementById('leaveFields').style.display = this.value === 'Leave' ? 'block' : 'none';
});

function clearFieldErrors(){
  document.querySelectorAll('.form-group').forEach(f=>f.classList.remove('has-error'));
}

/* ---------- Auto-generated formal letter ---------- */
function fmtLongDate(d){ return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}); }
function gatherLetterData(){
  const name = CURRENT_USER_NAME || 'Staff';
  const role = CURRENT_ROLE || 'staff';
  const isLeave = document.getElementById('requestType').value === 'Leave';
  const routed = document.getElementById('routedTo').value;
  const paras = [];
  if (isLeave) {
    const sv = document.getElementById('startDate').value;
    const ev = document.getElementById('endDate').value;
    const sd = sv ? new Date(sv+'T00:00:00') : null;
    const ed = ev ? new Date(ev+'T00:00:00') : null;
    const days = (sd && ed) ? Math.round((ed-sd)/86400000)+1 : 0;
    const reason = document.getElementById('detail').value.trim();
    paras.push('I, '+name+' ('+role+'), respectfully request a '+document.getElementById('leaveType').value+' leave from '+(sd?fmtLongDate(sd):'[start date]')+' to '+(ed?fmtLongDate(ed):'[end date]')+', covering '+days+' day(s), for the following reason: '+(reason||'[reason]')+'.');
    paras.push('During my absence, I will ensure that my responsibilities are properly turned over so that operations continue smoothly.');
  } else {
    paras.push('I, '+name+' ('+role+'), respectfully submit the following request for your review and approval:');
    paras.push(document.getElementById('detail').value.trim() || '[request details]');
  }
  return {
    dateLabel: fmtLongDate(new Date()),
    addressee: routed === 'hr' ? 'The HR Manager' : 'The Superadmin',
    name: name,
    roleLabel: role,
    subject: isLeave ? ('Leave Request ('+document.getElementById('leaveType').value+')') : 'General Request',
    bodyParas: paras
  };
}
function refreshLetterPreview(){
  const t = LetterGen.text(gatherLetterData());
  document.getElementById('letterPreview').textContent = t;
  const f = document.getElementById('letterPreviewFull'); if (f) f.textContent = t;
}
['requestType','leaveType','startDate','endDate','detail','routedTo'].forEach(id=>{
  const el = document.getElementById(id);
  if (el) { el.addEventListener('input', refreshLetterPreview); el.addEventListener('change', refreshLetterPreview); }
});
document.getElementById('letterViewBtn').addEventListener('click', () => { refreshLetterPreview(); document.getElementById('letterModal').style.display = 'flex'; });

/* ---------- Signature pad ---------- */
const sigCanvas = document.getElementById('sigPad');
const sigCtx = sigCanvas.getContext('2d');
sigCtx.lineWidth = 3; sigCtx.lineCap = 'round'; sigCtx.lineJoin = 'round'; sigCtx.strokeStyle = '#22303f';
let drawing = false;

function sigPos(e){
  const r = sigCanvas.getBoundingClientRect();
  return { x: (e.clientX - r.left) * (sigCanvas.width / r.width), y: (e.clientY - r.top) * (sigCanvas.height / r.height) };
}
sigCanvas.addEventListener('pointerdown', e => {
  drawing = true; sigStrokes++;
  document.getElementById('sigSubmit').disabled = false;
  document.getElementById('sigError').style.display = 'none';
  const p = sigPos(e); sigCtx.beginPath(); sigCtx.moveTo(p.x, p.y);
  sigCanvas.setPointerCapture(e.pointerId);
  e.preventDefault();
});
sigCanvas.addEventListener('pointermove', e => { if (!drawing) return; const p = sigPos(e); sigCtx.lineTo(p.x, p.y); sigCtx.stroke(); e.preventDefault(); });
['pointerup','pointercancel','pointerleave'].forEach(ev => sigCanvas.addEventListener(ev, () => { drawing = false; }));

function sigClear(){
  sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
  sigStrokes = 0;
  document.getElementById('sigSubmit').disabled = true;
}

/* ---------- Signature security: typed name + camera selfie ---------- */
function nameNorm(s){ return (s||'').toLowerCase().replace(/\s+/g,' ').trim(); }
let sigStream = null, camOk = false;
async function startSigCam(){
  camOk = false;
  document.getElementById('camErr').style.display = 'none';
  const vid = document.getElementById('sigCam');
  try {
    sigStream = await navigator.mediaDevices.getUserMedia({video:{width:480,height:360}, audio:false});
    vid.srcObject = sigStream;
    camOk = true;
  } catch(e) {
    document.getElementById('camErr').style.display = 'block';
  }
}
function stopSigCam(){
  if (sigStream) { sigStream.getTracks().forEach(t=>t.stop()); sigStream = null; }
  const vid = document.getElementById('sigCam');
  if (vid) vid.srcObject = null;
  camOk = false;
}
function captureSignerPhoto(){
  const vid = document.getElementById('sigCam');
  if (!camOk || !vid.videoWidth) return '';
  const c = document.createElement('canvas');
  c.width = vid.videoWidth; c.height = vid.videoHeight;
  c.getContext('2d').drawImage(vid, 0, 0, c.width, c.height);
  return c.toDataURL('image/jpeg', 0.85);
}
document.getElementById('signerName')?.remove();
/* ---------- Signature matching vs registered signature ---------- */
let regSigGrid = null;
function inkGridFromCanvas(srcCanvas){
  const w = srcCanvas.width, h = srcCanvas.height;
  const d = srcCanvas.getContext('2d').getImageData(0,0,w,h).data;
  let minx=w, miny=h, maxx=-1, maxy=-1;
  const mask = new Uint8Array(w*h);
  for (let y=0;y<h;y++) for (let x=0;x<w;x++){
    const i=(y*w+x)*4, a=d[i+3], lum=(d[i]+d[i+1]+d[i+2])/3;
    const ink = a>40 && lum<160;
    mask[y*w+x] = ink?1:0;
    if (ink){ if(x<minx)minx=x; if(x>maxx)maxx=x; if(y<miny)miny=y; if(y>maxy)maxy=y; }
  }
  if (maxx<0) return null;
  const S=48, grid=new Uint8Array(S*S), gw=maxx-minx+1, gh=maxy-miny+1;
  for (let gy=0;gy<S;gy++) for (let gx=0;gx<S;gx++){
    const sx=Math.min(maxx,Math.max(minx,minx+Math.floor((gx+0.5)*gw/S)));
    const sy=Math.min(maxy,Math.max(miny,miny+Math.floor((gy+0.5)*gh/S)));
    grid[gy*S+gx]=mask[sy*w+sx];
  }
  return grid;
}
function inkGridFromImage(img){
  const c=document.createElement('canvas');
  c.width=img.naturalWidth||img.width; c.height=img.naturalHeight||img.height;
  const cx=c.getContext('2d'); cx.fillStyle='#fff'; cx.fillRect(0,0,c.width,c.height); cx.drawImage(img,0,0);
  return inkGridFromCanvas(c);
}
function dilateGrid(g,S){
  const out=new Uint8Array(g);
  for (let y=0;y<S;y++) for (let x=0;x<S;x++) if (g[y*S+x]) {
    for (let dy=-1;dy<=1;dy++) for (let dx=-1;dx<=1;dx++){
      const ny=y+dy, nx=x+dx;
      if (ny>=0&&ny<S&&nx>=0&&nx<S) out[ny*S+nx]=1;
    }
  }
  return out;
}
function sigSimilarity(gridA,gridB){
  const S=48;
  if (!gridA||!gridB) return 0;
  const da=dilateGrid(gridA,S), db=dilateGrid(gridB,S);
  let ca=0, cb=0, ha=0, hb=0;
  for (let i=0;i<S*S;i++){ if(gridA[i]){ca++; if(db[i])ha++;} if(gridB[i]){cb++; if(da[i])hb++;} }
  if (!ca||!cb) return 0;
  const p=ha/ca, r=hb/cb;
  return (p+r)>0 ? 2*p*r/(p+r) : 0;
}
async function loadRegSig(){
  const box = document.getElementById('regSigBox');
  regSigGrid = null;
  document.getElementById('sigMatchError').style.display = 'none';
  box.textContent = 'Loading…';
  try {
    const d = await apiFetch('../api/my_signature.php');
    if (d.has && d.path) {
      box.innerHTML = '';
      const img = new Image();
      img.onload = () => { regSigGrid = inkGridFromImage(img); };
      img.onerror = () => { regSigGrid = null; box.textContent = 'Your registered signature file is missing on the server — this signing will re-record it as your official signature.'; };
      img.src = '../' + d.path;
      box.appendChild(img);
    } else {
      box.textContent = 'First-time signing: this signature will be recorded as your official registered signature.';
    }
  } catch(e) { box.textContent = 'Could not load your registered signature.'; }
}
document.getElementById('sigClear').addEventListener('click', sigClear);
document.getElementById('sigCancel').addEventListener('click', () => {
  document.getElementById('sigModal').style.display = 'none';
  pendingBody = null; sigClear(); stopSigCam();
  document.getElementById('submitBtn').disabled = false;
});

/* ---------- Submit flow: validate -> sign -> send ---------- */
document.getElementById('requestForm').addEventListener('submit', async function(e){
  e.preventDefault();
  clearFieldErrors();
  const type = document.getElementById('requestType').value;
  const detail = document.getElementById('detail').value.trim();
  const routed_to = document.getElementById('routedTo').value;

  let valid = true;
  if (detail.length < 3) { document.getElementById('field-detail').classList.add('has-error'); valid = false; }

  const body = { kind: type, detail, routed_to };
  if (type === 'Leave') {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    if (!start) { document.getElementById('field-start').classList.add('has-error'); valid = false; }
    if (!end || (start && end < start)) { document.getElementById('field-end').classList.add('has-error'); valid = false; }
    body.leave_type = document.getElementById('leaveType').value;
    body.start_date = start;
    body.end_date = end;
    body.reason = detail;
  }
  if (!valid) return;

  // confirm the e-sign intent BEFORE the pad is shown
  if (!await swalAsk('Confirm e-sign: you are about to enter your official e-signature for this request. It will be placed on the formal letter and used for signature security. Continue?')) return;
  // Open the signature modal — nothing is sent until the staff signs.
  pendingBody = body;
  document.getElementById('sigRoute').textContent = (routed_to === 'hr') ? 'HR (Admin)' : 'Superadmin';
  document.getElementById('sigAs').textContent = CURRENT_USER_NAME || 'me';
  sigClear();
  loadRegSig();
  document.getElementById('sigModal').style.display = 'flex';
  startSigCam();
});

document.getElementById('sigSubmit').addEventListener('click', async function(){
  if (!pendingBody) return;
  if (sigStrokes === 0) { document.getElementById('sigError').style.display = 'block'; return; }
  // Security pre-check: drawn signature must resemble the registered one.
  if (regSigGrid) {
    const score = sigSimilarity(regSigGrid, inkGridFromCanvas(sigCanvas));
    if (score < 0.45) {
      const err = document.getElementById('sigMatchError');
      err.textContent = 'Your signature matches only ' + Math.round(score*100) + '% of your registered signature. Please sign again the same way.';
      err.style.display = 'block';
      return;
    }
    document.getElementById('sigMatchError').style.display = 'none';
  }
  // Security: capture the selfie from the camera.
  const signerPhoto = captureSignerPhoto();
  if (!signerPhoto) { document.getElementById('camErr').style.display = 'block'; return; }
  this.disabled = true;
  const fd = new FormData();
  for (const [k, v] of Object.entries(pendingBody)) fd.append(k, v);
  const sigJpeg = LetterGen.sigJpegFromCanvas(sigCanvas);
  const pdfBlob = LetterGen.buildDocx(gatherLetterData(), sigJpeg);
  const lname = (CURRENT_USER_NAME || 'staff').trim().replace(/[^A-Za-z0-9]+/g,'_');
  fd.append('letter', pdfBlob, 'Formal_Letter_' + lname + '.docx');
  fd.append('signature', sigCanvas.toDataURL('image/png'));
  fd.append('signer_photo', signerPhoto);
  try {
    const d = await apiFetch('../api/request_submit.php', {method:'POST', credentials:'same-origin', body:fd});
    document.getElementById('sigModal').style.display = 'none';
    stopSigCam();
    showAlert(d.message || 'Request submitted with your signed letter.', 'success');
    document.getElementById('requestForm').reset();
    refreshLetterPreview();
    sigClear();
    pendingBody = null;
    document.getElementById('leaveFields').style.display = 'none';
    loadRequests();
    if (CURRENT_ROLE === 'admin' || CURRENT_ROLE === 'superadmin') loadToProcess();
  } catch(err) {
    showAlert(err.message, 'error');
  } finally {
    this.disabled = false;
    document.getElementById('submitBtn').disabled = false;
  }
});

/* ---------- Lists ---------- */
function docLinks(req){
  let html = '';
  if (req.letter_path) html += `<a class="doc-link" href="../${esc(req.letter_path)}" target="_blank" rel="noopener">📄 Letter</a>`;
  else html = '<span style="color:var(--muted);font-size:12px">—</span>';
  return html;
}
function sigLinks(req){
  let h = req.signature_path ? `<a class="doc-link sig" href="../${esc(req.signature_path)}" target="_blank" rel="noopener">✍️ Signed</a>` : '<span style="color:var(--muted);font-size:12px">—</span>';
  if (req.signer_photo_path) h += ` <img class="photo-thumb" src="../${esc(req.signer_photo_path)}" title="Security photo of ${esc(req.signer_name||'')} at signing" onclick="window.open('../${esc(req.signer_photo_path)}','_blank')">`;
  return h;
}
function photoCell(req){
  if (req.signer_photo_path) return `<img class="photo-thumb" src="../${esc(req.signer_photo_path)}" title="Security photo of ${esc(req.signer_name||req.employee_name||'')} at signing" onclick="window.open('../${esc(req.signer_photo_path)}','_blank')">`;
  return '<span style="color:var(--muted);font-size:12px">—</span>';
}
function routeLabel(req){
  if (req.source === 'leave') return 'HR & Superadmin';
  return (req.routed_to === 'hr') ? 'HR' : 'Superadmin';
}

async function loadRequests(){
  try {
    const d = await apiFetch('../api/requests.php?mine=1');
    const rows = d.data || [];
    if (rows.length === 0) { document.getElementById('requestList').innerHTML = '<div class="empty">No requests yet.</div>'; return; }
    let html = '<table><thead><tr><th>Type</th><th>Detail</th><th>Sent to</th><th>Letter</th><th>Signature</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    const rowHtml = (req) => {
      const badgeClass = 'badge-' + (req.status || 'pending').toLowerCase();
      return `<tr><td>${esc(req.kind)}</td>${detailCell(req.detail, req)}<td><span class="route-badge">${routeLabel(req)}</span></td><td>${docLinks(req)}</td><td>${sigLinks(req)}</td><td>${(req.created_at||'').split(' ')[0]}</td><td><span class="badge ${badgeClass}">${req.status}</span></td></tr>`;
    };
    const pendRows = rows.filter(x => x.status === 'Pending');
    const doneRows = rows.filter(x => x.status !== 'Pending');
    if (pendRows.length) html += '<tr class="list-section"><td colspan="7">⏳ Pending — waiting for action</td></tr>' + pendRows.map(rowHtml).join('');
    if (doneRows.length) html += '<tr class="list-section"><td colspan="7">✅ Approved / Rejected — completed</td></tr>' + doneRows.map(rowHtml).join('');
    html += '</tbody></table>';
    document.getElementById('requestList').innerHTML = html;
  } catch(e) { document.getElementById('requestList').innerHTML = `<div class="empty">Could not load requests. ${esc(e.message)}</div>`; }
}

async function loadToProcess(){
  const card = document.getElementById('processCard');
  card.style.display = 'block';
  document.getElementById('processSub').textContent = CURRENT_ROLE === 'admin'
    ? 'Pending requests staff routed directly to HR (plus pending leaves).'
    : 'Pending requests routed to superadmin (plus pending leaves).';
  try {
    const d = await apiFetch('../api/requests.php?to_process=1');
    const rows = d.data || [];
    if (rows.length === 0) { document.getElementById('processList').innerHTML = '<div class="empty">Nothing pending.</div>'; return; }
    let html = '<table><thead><tr><th>Employee</th><th>Type</th><th>Detail</th><th>Letter</th><th>Signature</th><th>Signer Photo</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
    for (const req of rows) {
      html += `<tr><td>${esc(req.employee_name)}</td><td>${esc(req.kind)}</td>${detailCell(req.detail, req)}<td>${docLinks(req)}</td><td>${sigLinks(req)}</td><td>${photoCell(req)}</td><td>${(req.created_at||'').split(' ')[0]}</td><td><span class="actions"><button class="action-btn approve" onclick="processRequest(${req.id},'approve','${esc(req.source)}')">Approve</button><button class="action-btn reject" onclick="processRequest(${req.id},'reject','${esc(req.source)}')">Reject</button></span></td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('processList').innerHTML = html;
  } catch(e) { document.getElementById('processList').innerHTML = `<div class="empty">Could not load. ${esc(e.message)}</div>`; }
}

async function processRequest(id, action, source){
  if (!await swalAsk(action === 'approve' ? 'Approve this request?' : 'Reject this request?')) return;
  try {
    await apiFetch('../api/requests.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action,id,source})});
    loadToProcess();
    loadRequests();
  } catch(e) { alert(e.message); }
}

function showAlert(msg, type){
  const box = document.getElementById('alertBox');
  box.innerHTML = `<div class="alert alert-${type}">${esc(msg)}</div>`;
  setTimeout(() => box.innerHTML = '', 3500);
}
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}

async function init(){
  try {
    const me = await apiFetch('../api/me.php');
    CURRENT_ROLE = me.user?.role;
    CURRENT_USER_NAME = me.user?.name || me.user?.email || '';
    if (window.LetterGen) refreshLetterPreview();
    if (CURRENT_ROLE === 'admin' || CURRENT_ROLE === 'superadmin') loadToProcess();
  } catch(e) {}
  loadRequests();
}
init();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
