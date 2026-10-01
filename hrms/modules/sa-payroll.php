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
<title>Payroll</title>
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
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed}
.btn-small{padding:7px 13px;font-size:12.5px;border-radius:8px;background:var(--card);color:var(--accent);border:1.5px solid var(--accent)}
.btn-small:hover{background:rgba(169,113,74,.08)}
.btn-ghost{padding:7px 13px;font-size:12.5px;border-radius:8px;background:var(--card);color:var(--muted);border:1px solid var(--line)}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:18px}
.card h2{font-size:17px;font-weight:800;margin-bottom:4px}
.card .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:top}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.badge-ok{background:#e2ecdf;color:var(--ok)}
.badge-warn{background:#fff4e0;color:var(--warn)}
.badge-danger{background:#fce4dc;color:var(--danger)}
.badge-muted{background:#eee;color:var(--muted)}
.finance-note{color:var(--danger);font-size:12.5px;margin-top:5px;max-width:320px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:2px}
.field label{display:block;font-size:12.5px;font-weight:700;color:var(--muted);margin-bottom:6px}
.field input,.field select{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:9px;font-size:14px;background:var(--cream);color:var(--ink);font-family:inherit}
.field input:read-only{background:#eee6da;color:var(--muted);cursor:not-allowed}
.field .hint{font-size:11.5px;color:var(--muted);margin-top:5px}
.field-error{color:var(--danger);font-size:11.5px;margin-top:5px;display:none}
.field.has-error input,.field.has-error select{border-color:var(--danger)}
.field.has-error .field-error{display:block}
.calc-box{background:var(--cream);border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-top:16px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.calc-box div span{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;font-weight:700}
.calc-box div b{font-size:16px}
.calc-box .net b{color:var(--ok)}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.alert-success{background:#e2ecdf;color:var(--ok)}
.alert-error{background:#fce4dc;color:var(--danger)}
.resubmit-box{background:#fff8f0;border:1.5px solid var(--warn);border-radius:10px;padding:14px 16px;margin-top:14px;display:none}
.resubmit-box.show{display:block}
.resubmit-box h3{font-size:14px;margin-bottom:8px}
.resubmit-fields{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:end}
@media(max-width:700px){.form-grid,.calc-box,.resubmit-fields{grid-template-columns:1fr}}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../final-operations.css">
</head>
<body>
<div class="eyebrow">Superadmin · Payroll</div>
<h1>Payroll Records</h1>
<p class="sub">Gross is pulled automatically from each employee's contract, prorated for the selected period. Releases are sent to <b>Finance for approval</b> — the employee only sees the payslip after Finance approves. Rejected releases come back here for you to fix and resubmit.</p>

<div class="card">
  <h2>Release Payroll</h2>
  <div class="desc">Choose an employee, a month, and whether this is the full month or a 15-day cutoff. Submitting sends it to Finance.</div>
  <div id="createAlert"></div>
  <div class="form-grid">
    <div class="field" id="field-employee">
      <label>Employee</label>
      <select id="payEmployee"><option value="">— Select employee —</option></select>
      <div class="field-error">Please select an employee.</div>
    </div>
    <div class="field" id="field-month">
      <label>Month</label>
      <input type="month" id="payMonth">
      <div class="field-error">Please pick a month.</div>
    </div>
    <div class="field">
      <label>Pay Period</label>
      <select id="payPeriodType">
        <option value="full">Full month</option>
        <option value="first_half">1st half (1–15)</option>
        <option value="second_half">2nd half (16–end)</option>
      </select>
    </div>
    <div class="field">
      <label>Contract Salary (Gross for this period)</label>
      <input type="text" id="payGross" readonly placeholder="Select an employee first">
      <div class="hint">Pulled from the employee's contract, halved automatically for a 15-day cutoff.</div>
    </div>
    <div class="field">
      <label>Other Deductions (₱)</label>
      <input type="number" id="payOtherDeductions" min="0" step="0.01" value="0">
      <div class="hint">Any extra deduction beyond standard SSS / PhilHealth / Pag-IBIG (e.g. cash advance, tardiness).</div>
    </div>
  </div>
  <div class="calc-box" id="calcBox" style="display:none">
    <div><span>SSS</span><b id="calcSss">—</b></div>
    <div><span>PhilHealth</span><b id="calcPhil">—</b></div>
    <div><span>Pag-IBIG</span><b id="calcPagibig">—</b></div>
    <div class="net"><span>Net Pay</span><b id="calcNet">—</b></div>
  </div>
  <div style="margin-top:16px">
    <button class="btn btn-primary" id="releaseBtn" onclick="releasePayroll()">Submit to Finance</button>
  </div>
</div>

<div class="controls">
  <input type="text" id="search" placeholder="Search employee...">
  <button class="btn btn-primary" onclick="loadPayroll()">Refresh</button>
</div>

<div class="card">
  <div id="payrollList"><div class="empty">Loading payroll records...</div></div>
</div>

<script>
const API_BASE = '../api';
let EMPLOYEES = [];
let monthlyGross = 0;
let PAYROLL = [];

function money(n){ return '₱'+Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2}); }

function apiFetch(url, opts){
  return fetch(url, opts).then(r=>r.text().then(text=>{
    let data;
    try { data = JSON.parse(text); }
    catch(e){ throw new Error('Server did not return valid JSON (HTTP '+r.status+'). '+text.slice(0,200)); }
    if (!r.ok && !data.success) throw new Error(data.message || ('Request failed (HTTP '+r.status+')'));
    return data;
  }));
}

function showCreateAlert(msg, type){
  const box = document.getElementById('createAlert');
  box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
  setTimeout(()=>box.innerHTML='', 5000);
}
function clearFieldErrors(){
  document.getElementById('field-employee').classList.remove('has-error');
  document.getElementById('field-month').classList.remove('has-error');
}

async function loadEmployeesForPayroll(){
  try {
    const d = await apiFetch(`${API_BASE}/employees.php`);
    EMPLOYEES = d.data || [];
    const sel = document.getElementById('payEmployee');
    sel.innerHTML = '<option value="">— Select employee —</option>' + EMPLOYEES.map(e=>`<option value="${e.id}">${esc(e.name)} (${esc(e.role||'Staff')})</option>`).join('');
  } catch(e) { showCreateAlert('Could not load employees: '+esc(e.message), 'error'); }
}

document.getElementById('payEmployee').addEventListener('change', loadContractGross);
document.getElementById('payPeriodType').addEventListener('change', updateCalcPreview);
document.getElementById('payOtherDeductions').addEventListener('input', updateCalcPreview);

async function loadContractGross(){
  const id = document.getElementById('payEmployee').value;
  const grossField = document.getElementById('payGross');
  document.getElementById('calcBox').style.display = 'none';
  if (!id) { grossField.value=''; monthlyGross=0; return; }
  grossField.value = 'Loading...';
  try {
    const d = await apiFetch(`${API_BASE}/payroll.php?action=rate&employee_id=${id}`);
    monthlyGross = Number(d.monthly_gross || 0);
    updateCalcPreview();
  } catch(e) { grossField.value = ''; showCreateAlert('Could not load contract salary: '+esc(e.message), 'error'); }
}

function updateCalcPreview(){
  const grossField = document.getElementById('payGross');
  if (monthlyGross <= 0) {
    grossField.value = document.getElementById('payEmployee').value ? 'No contract salary on file' : '';
    document.getElementById('calcBox').style.display='none';
    return;
  }
  const periodType = document.getElementById('payPeriodType').value;
  const gross = periodType === 'full' ? monthlyGross : monthlyGross / 2;
  grossField.value = money(gross);

  const other = Number(document.getElementById('payOtherDeductions').value || 0);
  const sss = Math.round(gross * 0.05 * 100)/100;
  const phil = Math.round(gross * 0.025 * 100)/100;
  const pagibig = Math.round(Math.min(gross,10000) * 0.02 * 100)/100;
  const net = Math.round((gross - (sss+phil+pagibig+other)) * 100)/100;
  document.getElementById('calcSss').textContent = money(sss);
  document.getElementById('calcPhil').textContent = money(phil);
  document.getElementById('calcPagibig').textContent = money(pagibig);
  document.getElementById('calcNet').textContent = money(net);
  document.getElementById('calcBox').style.display = 'grid';
}

async function releasePayroll(){
  clearFieldErrors();
  const employee_id = document.getElementById('payEmployee').value;
  const month = document.getElementById('payMonth').value;
  const period_type = document.getElementById('payPeriodType').value;
  const other_deductions = document.getElementById('payOtherDeductions').value || 0;

  let valid = true;
  if (!employee_id) { document.getElementById('field-employee').classList.add('has-error'); valid = false; }
  if (!month) { document.getElementById('field-month').classList.add('has-error'); valid = false; }
  if (!valid) { showCreateAlert('Please complete the required fields.', 'error'); return; }
  if (monthlyGross <= 0) { showCreateAlert('This employee has no contract salary on file.', 'error'); return; }

  document.getElementById('releaseBtn').disabled = true;
  try {
    const d = await apiFetch(`${API_BASE}/payroll.php`, {
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
      body: JSON.stringify({ action:'create', employee_id, month, period_type, other_deductions })
    });
    showCreateAlert(`Payroll for ${esc(d.period)} submitted to Finance for approval.`, 'success');
    document.getElementById('payOtherDeductions').value = 0;
    document.getElementById('payMonth').value = '';
    document.getElementById('calcBox').style.display = 'none';
    loadPayroll();
  } catch(e) { showCreateAlert(esc(e.message), 'error'); }
  finally { document.getElementById('releaseBtn').disabled = false; }
}

function statusBadge(p){
  const s = p.payroll_status || p.status || 'Draft';
  if (s === 'Released') return '<span class="badge badge-ok">Released</span>';
  if (s === 'Pending Finance') return '<span class="badge badge-warn">Pending Finance</span>';
  if (s === 'Rejected') return '<span class="badge badge-danger">Rejected by Finance</span>';
  return '<span class="badge badge-muted">' + esc(s) + '</span>';
}

async function loadPayroll(){
  const search = document.getElementById('search').value.toLowerCase();
  try {
    const d = await apiFetch(`${API_BASE}/payroll.php`);
    PAYROLL = d.data || [];
    const rows = PAYROLL.filter(p => !search || (p.employee_name || '').toLowerCase().includes(search));
    if (rows.length === 0) { document.getElementById('payrollList').innerHTML = '<div class="empty">No payroll records found.</div>'; return; }
    let html = '<table><thead><tr><th>Employee</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    for (const p of rows) {
      const rejected = (p.payroll_status || '') === 'Rejected';
      const action = rejected
        ? `<button class="btn-small" onclick="openResubmit(${p.id})">Fix &amp; resubmit</button>`
        : '';
      const note = rejected && p.finance_comment
        ? `<div class="finance-note">💬 Finance: ${esc(p.finance_comment)}${p.finance_acted_at ? ' <small>(' + esc(String(p.finance_acted_at).replace('T',' ').substring(0,16)) + ')</small>' : ''}</div>`
        : '';
      html += `<tr><td>${esc(p.employee_name)}</td><td>${esc(p.period || p.payroll_period || '—')}${note}</td><td>${money(p.gross)}</td><td>${money(p.deductions)}</td><td><b>${money(p.net_pay)}</b></td><td>${statusBadge(p)}</td><td>${action}</td></tr>`;
    }
    html += '</tbody></table>';

    // Resubmit box (shown only when "Fix & resubmit" is clicked)
    html += `
      <div class="resubmit-box" id="resubmitBox">
        <h3 id="resubmitTitle">Fix &amp; resubmit payroll</h3>
        <div class="resubmit-fields">
          <div class="field">
            <label>Other Deductions (₱) — adjust if that's what Finance flagged</label>
            <input type="number" id="resubmitOther" min="0" step="0.01" value="0">
          </div>
          <button class="btn btn-primary" id="resubmitBtn" onclick="doResubmit()">Resubmit to Finance</button>
          <button class="btn-ghost" onclick="closeResubmit()">Cancel</button>
        </div>
        <div id="resubmitAlert"></div>
      </div>`;

    document.getElementById('payrollList').innerHTML = html;
  } catch(e) { document.getElementById('payrollList').innerHTML = `<div class="empty">Could not load payroll. ${esc(e.message)}</div>`; }
}

let RESUBMIT_ID = null;
function openResubmit(id){
  const p = PAYROLL.find(x => x.id === id);
  if (!p) return;
  RESUBMIT_ID = id;
  const box = document.getElementById('resubmitBox');
  document.getElementById('resubmitTitle').textContent = `Fix & resubmit — ${p.employee_name} (${p.payroll_period || p.period || ''}) · net ${money(p.net_pay)}`;
  document.getElementById('resubmitOther').value = Number(p.other_deductions || 0);
  document.getElementById('resubmitAlert').innerHTML = '';
  box.classList.add('show');
  box.scrollIntoView({behavior:'smooth', block:'nearest'});
}
function closeResubmit(){
  RESUBMIT_ID = null;
  const box = document.getElementById('resubmitBox');
  if (box) box.classList.remove('show');
}
async function doResubmit(){
  if (RESUBMIT_ID === null) return;
  const other = document.getElementById('resubmitOther').value || 0;
  const btn = document.getElementById('resubmitBtn');
  btn.disabled = true;
  try {
    const d = await apiFetch(`${API_BASE}/payroll.php`, {
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
      body: JSON.stringify({ action:'update', id:RESUBMIT_ID, other_deductions: other })
    });
    document.getElementById('resubmitAlert').innerHTML = `<div class="alert alert-success" style="margin-top:10px">${esc(d.message || 'Resubmitted.')}</div>`;
    closeResubmit();
    loadPayroll();
  } catch(e) {
    document.getElementById('resubmitAlert').innerHTML = `<div class="alert alert-error" style="margin-top:10px">${esc(e.message)}</div>`;
  } finally { btn.disabled = false; }
}

function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
document.getElementById('search').addEventListener('input', loadPayroll);
loadEmployeesForPayroll();
loadPayroll();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../final-operations.js" defer></script>
</body>
</html>
