<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'finance') die('<p style="font-family:sans-serif;text-align:center;padding:40px">Access denied.</p>');
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
<title>Payroll Approvals</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:18px}
.card h2{font-size:17px;font-weight:800;margin-bottom:4px}
.card .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
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
.badge-muted{background:#eee;color:var(--muted)}
.btn{padding:9px 16px;border:none;border-radius:10px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-ok{background:var(--ok);color:#fff}.btn-ok:hover{background:#41693f}
.btn-danger{background:#fff;color:var(--danger);border:1.5px solid var(--danger)}.btn-danger:hover{background:#fce4dc}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn:disabled{opacity:.6;cursor:not-allowed}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.alert-info{background:#eef3ec;color:var(--ink);border:1px solid var(--line)}
.alert-warn{background:#fff4e0;color:var(--warn)}
.alert-error{background:#fce4dc;color:var(--danger)}
.comment-note{color:var(--danger);font-size:12.5px;margin-top:4px}
.controls{display:flex;gap:12px;align-items:center;margin-bottom:20px}

/* Reject modal */
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:100;align-items:center;justify-content:center;padding:20px}
.modal-backdrop.show{display:flex}
.modal{background:var(--card);border-radius:var(--radius);max-width:440px;width:100%;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:16px;margin-bottom:6px}
.modal p{font-size:13.5px;color:var(--muted);margin-bottom:12px}
.modal textarea{width:100%;min-height:90px;padding:10px 12px;border:1.5px solid var(--line);border-radius:9px;font:inherit;font-size:14px;background:var(--cream);color:var(--ink);resize:vertical;outline:none}
.modal textarea:focus{border-color:var(--danger);box-shadow:0 0 0 3px rgba(168,73,47,.1)}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../final-operations.css">
</head>
<body>
<div class="eyebrow">Finance · Payroll Approvals</div>
<h1>Payroll Releases</h1>
<p class="sub">The superadmin submits payroll releases here. Approve to release the payslip to the employee, or reject with a reason to send it back for fixing.</p>

<div id="budgetBanner"></div>
<div id="msgAlert" style="display:none"></div>

<div class="controls">
  <button class="btn btn-primary" onclick="loadQueue()">Refresh</button>
</div>

<div class="card">
  <h2>Awaiting your approval</h2>
  <div class="desc">These releases are NOT visible to the employee yet.</div>
  <div id="pendingList"><div class="empty">Loading...</div></div>
</div>

<div class="card">
  <h2>Decision history</h2>
  <div class="desc">Releases you have approved (released to the employee) or rejected (sent back to the superadmin).</div>
  <div id="historyList"><div class="empty">Loading...</div></div>
</div>

<!-- Reject modal -->
<div class="modal-backdrop" id="rejectBackdrop">
  <div class="modal">
    <h3>Reject payroll release</h3>
    <p id="rejectTarget"></p>
    <textarea id="rejectComment" placeholder="Explain what needs to be fixed (required). The superadmin will see this reason."></textarea>
    <div class="modal-actions">
      <button class="btn btn-danger" style="background:var(--card)" onclick="closeReject()">Cancel</button>
      <button class="btn btn-danger" id="confirmRejectBtn" onclick="confirmReject()">Reject &amp; notify superadmin</button>
    </div>
  </div>
</div>

<script src="../../alerts.js"></script>
<script>
let QUEUED = [];
let REJECTING = null;

function money(n){ return '₱' + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2}); }
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
function shortDate(t){ return t ? new Date(String(t).replace(' ','T')).toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'}) + ' ' + new Date(String(t).replace(' ','T')).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'}) : '—'; }

function showMsg(msg, type){
  const box = document.getElementById('msgAlert');
  box.className = 'alert alert-' + type;
  box.textContent = msg;
  box.style.display = 'block';
  setTimeout(()=>{ box.style.display='none'; }, 5000);
}

async function loadQueue(){
  try {
    const r = await fetch('../api/payroll.php?action=queue', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { showMsg(d.message || 'Could not load payroll queue.', 'error'); return; }
    QUEUED = d.data || [];

    // Budget banner
    const b = d.budget || {};
    const banner = document.getElementById('budgetBanner');
    if (b.total !== null && b.total !== undefined) {
      const over = b.remaining < 0;
      banner.innerHTML = `<div class="alert ${over ? 'alert-warn' : 'alert-info'}">
        <b>${esc(b.label)} budget:</b> ${money(b.total)} total · ${money(b.used)} released ·
        <b style="color:${over ? 'var(--danger)' : 'var(--ok)'}">${money(b.remaining)}</b> remaining ·
        ${money(b.pending)} pending in queue${over ? ' — <b>currently over budget</b>' : ''}
      </div>`;
    } else {
      banner.innerHTML = `<div class="alert alert-warn">No budget set for <b>${esc(b.label || 'this month')}</b> yet — set one in the Budget page to track spending.</div>`;
    }

    // Pending
    const pending = QUEUED.filter(p => p.payroll_status === 'Pending Finance');
    const pEl = document.getElementById('pendingList');
    if (!pending.length) {
      pEl.innerHTML = '<div class="empty">No payroll releases waiting for approval. 🎉</div>';
    } else {
      let html = '<table><thead><tr><th>Employee</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Submitted</th><th>Action</th></tr></thead><tbody>';
      for (const p of pending) {
        html += `<tr>
          <td><b>${esc(p.employee_name)}</b></td>
          <td>${esc(p.payroll_period || p.period || '—')}</td>
          <td>${money(p.gross)}</td>
          <td>${money(p.deductions)}</td>
          <td><b>${money(p.net_pay)}</b></td>
          <td>${shortDate(p.submitted_at || p.created_at)}</td>
          <td>
            <button class="btn btn-ok" onclick="approvePayroll(${p.id})">Approve</button>
            <button class="btn btn-danger" onclick="openReject(${p.id})">Reject</button>
          </td>
        </tr>`;
      }
      html += '</tbody></table>';
      pEl.innerHTML = html;
    }

    // History
    const history = QUEUED.filter(p => p.payroll_status === 'Released' || p.payroll_status === 'Rejected')
                          .sort((a,b) => new Date((b.finance_acted_at||b.created_at||0)) - new Date((a.finance_acted_at||a.created_at||0)))
                          .slice(0, 20);
    const hEl = document.getElementById('historyList');
    if (!history.length) {
      hEl.innerHTML = '<div class="empty">No decisions made yet.</div>';
    } else {
      let html = '<table><thead><tr><th>Employee</th><th>Period</th><th>Net Pay</th><th>Decision</th><th>Reason / Note</th><th>Decided</th></tr></thead><tbody>';
      for (const p of history) {
        const released = p.payroll_status === 'Released';
        html += `<tr>
          <td><b>${esc(p.employee_name)}</b></td>
          <td>${esc(p.payroll_period || p.period || '—')}</td>
          <td><b>${money(p.net_pay)}</b></td>
          <td>${released ? '<span class="badge badge-ok">Approved · Released</span>' : '<span class="badge badge-danger">Rejected</span>'}</td>
          <td>${p.finance_comment ? esc(p.finance_comment) : '—'}</td>
          <td>${shortDate(p.finance_acted_at)}${p.finance_acted_by ? ` <small style="color:var(--muted)">(${esc(p.finance_acted_by)})</small>` : ''}</td>
        </tr>`;
      }
      html += '</tbody></table>';
      hEl.innerHTML = html;
    }
  } catch(e) {
    document.getElementById('pendingList').innerHTML = '<div class="empty">Could not load payroll queue.</div>';
  }
}

async function approvePayroll(id){
  const p = QUEUED.find(x => x.id === id);
  const name = p ? p.employee_name : 'this employee';
  const period = p ? (p.payroll_period || '') : '';
  if (!await swalAsk(`Approve payroll for ${name} (${period})?\n\nThe employee will be able to see the payslip right away.`)) return;
  try {
    const r = await fetch('../api/payroll.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({action:'approve', id})});
    const d = await r.json();
    showMsg(d.message || (d.success ? 'Payroll approved.' : 'Failed'), d.success ? 'info' : 'error');
    loadQueue();
  } catch(e) { showMsg('Could not approve payroll.', 'error'); }
}

function openReject(id){
  const p = QUEUED.find(x => x.id === id);
  if (!p) return;
  REJECTING = id;
  document.getElementById('rejectTarget').textContent = `${p.employee_name} — ${p.payroll_period || p.period || ''} (net ${money(p.net_pay)})`;
  document.getElementById('rejectComment').value = '';
  document.getElementById('rejectBackdrop').classList.add('show');
  document.getElementById('rejectComment').focus();
}
function closeReject(){
  REJECTING = null;
  document.getElementById('rejectBackdrop').classList.remove('show');
}
async function confirmReject(){
  const comment = document.getElementById('rejectComment').value.trim();
  if (!comment) { showMsg('Please enter a reason for the rejection.', 'error'); return; }
  if (REJECTING === null) return;
  const btn = document.getElementById('confirmRejectBtn');
  btn.disabled = true;
  try {
    const r = await fetch('../api/payroll.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({action:'reject', id:REJECTING, comment})});
    const d = await r.json();
    closeReject();
    showMsg(d.message || (d.success ? 'Payroll rejected.' : 'Failed'), d.success ? 'info' : 'error');
    loadQueue();
  } catch(e) { showMsg('Could not reject payroll.', 'error'); }
  finally { btn.disabled = false; }
}

document.getElementById('rejectBackdrop').addEventListener('click', function(e){ if (e.target === this) closeReject(); });

loadQueue();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../final-operations.js" defer></script>
</body>
</html>
