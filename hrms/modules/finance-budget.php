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
<title>Budget</title>
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
.form-grid{display:grid;grid-template-columns:1fr 1fr 1.4fr auto;gap:14px;align-items:end}
.field label{display:block;font-size:12.5px;font-weight:700;color:var(--muted);margin-bottom:6px}
.field input{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:9px;font-size:14px;background:var(--cream);color:var(--ink);font-family:inherit;outline:none}
.field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-small{padding:6px 12px;font-size:12.5px;border-radius:8px;background:var(--card);color:var(--danger);border:1px solid var(--line)}
.btn-small:hover{background:#fce4dc;border-color:var(--danger)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.badge-ok{background:#e2ecdf;color:var(--ok)}
.badge-warn{background:#fff4e0;color:var(--warn)}
.badge-danger{background:#fce4dc;color:var(--danger)}
.badge-muted{background:#eee;color:var(--muted)}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;display:none}
.alert-success{background:#e2ecdf;color:var(--ok)}
.alert-error{background:#fce4dc;color:var(--danger)}
.bar-wrap{background:var(--cream);border:1px solid var(--line);border-radius:999px;height:10px;width:140px;overflow:hidden}
.bar{height:100%;background:var(--ok);border-radius:999px;transition:width .3s}
.bar.warn{background:var(--warn)}
.bar.danger{background:var(--danger)}
@media(max-width:800px){.form-grid{grid-template-columns:1fr}}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Finance · Budget</div>
<h1>Monthly Budget</h1>
<p class="sub">Set the payroll budget for each month. "Used" is the net payroll Finance has already released for that month.</p>

<div class="card">
  <h2>Set / update budget</h2>
  <div class="desc">Pick a month and the total payroll budget for it. Saving again for the same month updates the amount.</div>
  <div class="alert" id="alert"></div>
  <div class="form-grid">
    <div class="field">
      <label>Month</label>
      <input type="month" id="month">
    </div>
    <div class="field">
      <label>Budget total (₱)</label>
      <input type="number" id="total" min="0" step="0.01" placeholder="e.g. 60000">
    </div>
    <div class="field">
      <label>Note (optional)</label>
      <input type="text" id="note" placeholder="e.g. September payroll budget">
    </div>
    <button class="btn btn-primary" id="saveBtn" onclick="saveBudget()">Save budget</button>
  </div>
</div>

<div class="card">
  <h2>Budget overview</h2>
  <div class="desc">Pending = net payroll still waiting for your approval in that month.</div>
  <div id="budgetList"><div class="empty">Loading budget...</div></div>
</div>

<script src="../../alerts.js"></script>
<script>
function money(n){ return '₱' + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2}); }
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}

function showAlert(msg, type){
  const box = document.getElementById('alert');
  box.className = 'alert alert-' + type;
  box.textContent = msg;
  box.style.display = 'block';
  setTimeout(()=>{ box.style.display='none'; }, 4000);
}

async function loadBudget(){
  try {
    const r = await fetch('../api/budget.php', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('budgetList').innerHTML = '<div class="empty">Could not load budget: ' + esc(d.message||'') + '</div>'; return; }
    const rows = d.data || [];
    if (!rows.length) { document.getElementById('budgetList').innerHTML = '<div class="empty">No budgets yet. Set one above.</div>'; return; }
    let html = '<table><thead><tr><th>Month</th><th>Budget</th><th>Used (released)</th><th>Pending</th><th>Remaining</th><th>Usage</th><th>Status</th><th></th></tr></thead><tbody>';
    for (const b of rows) {
      const hasBudget = b.total !== null && b.total !== undefined;
      const used = Number(b.used||0), pending = Number(b.pending||0), total = Number(b.total||0);
      const remaining = hasBudget ? total - used : null;
      const pct = hasBudget && total > 0 ? Math.min(100, Math.round(used/total*100)) : 0;
      let barCls = '', status = '';
      if (!hasBudget) {
        barCls = ''; status = '<span class="badge badge-muted">No budget set</span>';
      } else if (remaining < 0) {
        barCls = 'danger'; status = '<span class="badge badge-danger">Over budget</span>';
      } else if ((used + pending) > total) {
        barCls = 'warn'; status = '<span class="badge badge-warn">Will exceed if pending approved</span>';
      } else {
        barCls = ''; status = '<span class="badge badge-ok">On track</span>';
      }
      html += `<tr>
        <td><b>${esc(b.label)}</b>${b.note ? `<br><small style="color:var(--muted)">${esc(b.note)}</small>` : ''}</td>
        <td>${hasBudget ? money(b.total) : '—'}</td>
        <td>${money(used)}</td>
        <td>${money(pending)}</td>
        <td>${remaining !== null ? `<b style="color:${remaining < 0 ? 'var(--danger)' : 'var(--ink)'}">${money(remaining)}</b>` : '—'}</td>
        <td><div class="bar-wrap"><div class="bar ${barCls}" style="width:${pct}%"></div></div><small style="color:var(--muted)">${pct}%</small></td>
        <td>${status}</td>
        <td>${hasBudget ? `<button class="btn-small" onclick="removeBudget('${b.month}')">Remove</button>` : ''}</td>
      </tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('budgetList').innerHTML = html;
  } catch(e) {
    document.getElementById('budgetList').innerHTML = '<div class="empty">Could not load budget.</div>';
  }
}

async function saveBudget(){
  const month = document.getElementById('month').value;
  const total = document.getElementById('total').value;
  const note = document.getElementById('note').value.trim();
  if (!month) { showAlert('Please pick a month.', 'error'); return; }
  if (total === '' || Number(total) < 0) { showAlert('Please enter a valid budget total.', 'error'); return; }
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  try {
    const r = await fetch('../api/budget.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({action:'set', month, total, note})});
    const d = await r.json();
    if (d.success) { showAlert(d.message || 'Budget saved.', 'success'); document.getElementById('note').value = ''; }
    else showAlert(d.message || 'Failed to save budget.', 'error');
  } catch(e) { showAlert('Could not save budget.', 'error'); }
  finally { btn.disabled = false; loadBudget(); }
}

async function removeBudget(month){
  if (!await swalAsk('Remove the budget for ' + month + '? Payroll records are not affected.')) return;
  try {
    const r = await fetch('../api/budget.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({action:'delete', month})});
    const d = await r.json();
    showAlert(d.message || (d.success ? 'Budget removed.' : 'Failed to remove budget.'), d.success ? 'success' : 'error');
    loadBudget();
  } catch(e) { showAlert('Could not remove budget.', 'error'); }
}

// Default the month picker to the current month (local time).
(function(){
  const now = new Date();
  document.getElementById('month').value = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
})();
loadBudget();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
