<?php
require_once __DIR__ . '/../api/common.php';
require_once __DIR__ . '/../api/feature_init.php';
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') die('<p style="font-family:sans-serif;text-align:center;padding:40px">Access denied.</p>');
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
<title>Applicants</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--blue:#2f6690;--blue-bg:#ddeaf5;--violet:#6d4a90;--violet-bg:#eee6f8;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
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
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
.empty{text-align:center;color:var(--muted);padding:40px 20px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:top}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--cream)}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.badge-pending{background:#fff4e0;color:var(--warn)}
.badge-accepted{background:#e2ecdf;color:var(--ok)}
.badge-initial{background:var(--blue-bg);color:var(--blue)}
.badge-actual{background:var(--violet-bg);color:var(--violet)}
.badge-final{background:#ecd9b5;color:var(--accent)}
.badge-hired{background:#e2ecdf;color:var(--ok)}
.badge-rejected{background:#fce4dc;color:var(--danger)}
.mode-badge{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;background:#f0f0f0;color:#666;margin-left:6px}
.action-btn{padding:6px 12px;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;margin-right:6px;margin-bottom:4px;font-family:inherit}
.action-btn.accept{background:var(--ok);color:#fff}
.action-btn.advance{background:var(--blue);color:#fff}
.action-btn.offer{background:var(--accent);color:#fff}
.action-btn.hire{background:var(--ok);color:#fff}
.action-btn.reject{background:var(--danger);color:#fff}
.action-btn:hover{opacity:.85}
.action-btn:disabled{opacity:.5;cursor:not-allowed}
.stage-track{font-size:11px;color:var(--muted);margin-top:4px}
.offer-sent{font-size:11px;color:var(--ok);font-weight:700;display:block;margin-top:4px}
.cv-link{display:inline-flex;align-items:center;gap:5px;color:var(--accent);font-weight:700;font-size:13px;text-decoration:none;padding:5px 10px;border:1px solid var(--line);border-radius:8px;background:var(--cream);white-space:nowrap}
.cv-link:hover{background:#fff;border-color:var(--accent)}
.cv-missing{color:var(--muted);font-size:12px;font-style:italic}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(59,35,19,.35);z-index:100;align-items:center;justify-content:center}
.modal-backdrop.show{display:flex}
.modal{background:#fff;border-radius:14px;padding:24px;width:100%;max-width:420px;max-height:90vh;overflow:auto}
.modal h2{font-size:18px;margin-bottom:4px}
.modal .sub{margin-bottom:16px}
.modal label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin:12px 0 4px}
.modal input,.modal textarea{width:100%;padding:9px 12px;border:1.5px solid var(--line);border-radius:8px;font-size:14px;font-family:inherit;background:var(--cream)}
.modal textarea{min-height:60px;resize:vertical}
.modal input[type=file]{padding:7px 10px;background:#fff}
.modal-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.modal-actions{display:flex;gap:10px;margin-top:18px}
.modal-actions .btn{flex:1}
.file-hint{font-size:11.5px;color:var(--muted);margin-top:4px}
.profile-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px 20px;font-size:13px}
.profile-grid .pg-item{min-width:0}
.profile-grid .pg-label{display:flex;align-items:center;gap:5px;font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;font-weight:700;margin-bottom:3px}
.profile-grid .pg-label .pg-icon{font-size:12px}
.profile-grid .pg-value{color:var(--ink);font-weight:600;word-break:break-word;line-height:1.4}
.profile-grid .pg-value.empty{color:var(--muted);font-weight:400;font-style:italic}
.profile-full{grid-column:1 / -1}
.detail-btn{padding:7px 14px;border:1.5px solid var(--line);border-radius:8px;background:var(--card);color:var(--accent);font-weight:700;font-size:12.5px;cursor:pointer;font-family:inherit}
.detail-btn:hover{background:var(--cream);border-color:var(--accent)}
.detail-modal .modal{max-width:680px;padding:0;overflow:hidden;display:flex;flex-direction:column;max-height:90vh}
.detail-modal .modal h2{margin-bottom:0;font-size:19px}
.detail-head{display:flex;align-items:center;gap:14px;padding:22px 26px;background:linear-gradient(135deg,var(--cream) 0%,#f3e6d8 100%);border-bottom:1px solid var(--line);flex-shrink:0}
.detail-avatar{width:52px;height:52px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;flex-shrink:0;box-shadow:0 2px 8px rgba(169,113,74,.3)}
.detail-head-info{flex:1;min-width:0}
.detail-head-info .sub{margin-bottom:0;font-size:12.5px;display:flex;align-items:center;gap:5px}
.detail-body{padding:22px 26px;overflow-y:auto}
.detail-section{background:var(--cream);border-radius:12px;padding:16px 18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.detail-section-label{font-size:11px;font-weight:800;color:var(--accent);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.detail-actions-section{background:#fff;border:1.5px dashed var(--line);border-radius:12px;padding:16px 18px}
.detail-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.detail-actions .action-btn{margin:0;padding:8px 16px;font-size:12.5px}
#offerModal{z-index:110}
.detail-close-row{display:flex;justify-content:flex-end;padding:16px 26px;border-top:1px solid var(--line);flex-shrink:0}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Admin · People</div>
<h1>Applicant Management</h1>
<p class="sub">Review applicants and move them through the pipeline: Accepted → Initial → Actual → Final Interview.</p>

<div class="controls">
  <input type="text" id="search" placeholder="Search applicants...">
  <select id="statusFilter">
    <option value="">All Status</option>
    <option value="Pending">Pending</option>
    <option value="Hired">Hired</option>
    <option value="Rejected">Rejected</option>
  </select>
  <button class="btn btn-primary" onclick="loadApplicants()">Refresh</button>
  <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
</div>

<div class="card">
  <div id="applicantList"><div class="empty">Loading applicants...</div></div>
</div>

<div class="modal-backdrop" id="offerModal">
  <div class="modal">
    <h2>Publish Offer</h2>
    <p class="sub" style="color:var(--muted);font-size:13px">This applicant passed the Final Interview. Fill in the offer so it shows automatically in their applicant portal.</p>
    <input type="hidden" id="offerApplicantId">
    <label>Department *</label>
    <input id="offerDepartment" required placeholder="e.g. Cafe Operations">
    <label>Monthly Salary (₱) *</label>
    <input id="offerSalary" required type="number" min="0.01" max="1000000000" step="0.01">
    <p class="file-hint">Limit: ₱1,000,000,000 (1 billion) maximum per month.</p>
    <label>Notes to applicant (optional)</label>
    <textarea id="offerNotes" placeholder="e.g. Please bring 2 valid IDs on your first day."></textarea>
    <label>Contract document (PDF, DOC, or DOCX) *</label>
    <input id="offerContract" type="file" accept=".pdf,.doc,.docx">
    <p class="file-hint" id="offerContractHint">Required — the employment contract the applicant will download from their status page (max 8MB).</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeOfferModal()">Cancel</button>
      <button class="btn btn-primary" onclick="submitOffer()">Publish Offer</button>
    </div>
  </div>
</div>

<div class="modal-backdrop detail-modal" id="detailModal">
  <div class="modal">
    <div class="detail-head">
      <div class="detail-avatar" id="detailAvatar">?</div>
      <div class="detail-head-info">
        <h2 id="detailName">Applicant</h2>
        <p class="sub" id="detailEmail">✉️</p>
      </div>
      <span class="badge" id="detailBadge"></span>
    </div>

    <div class="detail-body">
      <div class="detail-section">
        <div class="detail-section-label">📋 Application</div>
        <div class="profile-grid" id="detailAppGrid"></div>
      </div>

      <div class="detail-section">
        <div class="detail-section-label">👤 Applicant Profile</div>
        <div class="profile-grid" id="detailProfileGrid"></div>
      </div>

      <div class="detail-actions-section">
        <div class="detail-section-label" style="color:var(--muted);margin-bottom:10px">⚡ Actions</div>
        <div class="detail-actions" id="detailActions"></div>
      </div>
    </div>

    <div class="detail-close-row">
      <button class="btn btn-secondary" onclick="closeDetailModal()">Close</button>
    </div>
  </div>
</div>

<script src="../../alerts.js"></script>
<script>
const STAGES = ['Pending','Accepted','Initial Interview','Actual Interview','Final Interview'];

function stageBadgeClass(stage, status){
  if (status === 'Hired') return 'badge-hired';
  if (status === 'Rejected') return 'badge-rejected';
  if (stage === 'Accepted') return 'badge-accepted';
  if (stage === 'Initial Interview') return 'badge-initial';
  if (stage === 'Actual Interview') return 'badge-actual';
  if (stage === 'Final Interview') return 'badge-final';
  return 'badge-pending';
}
function stageLabel(stage, status){
  if (status === 'Hired') return 'Hired';
  if (status === 'Rejected') return 'Rejected';
  return stage || 'Pending';
}
function nextStage(stage){
  const i = STAGES.indexOf(stage || 'Pending');
  if (i === -1 || i === STAGES.length - 1) return null;
  return STAGES[i+1];
}

let APPLICANT_ROWS = [];
async function loadApplicants(){
  const search = document.getElementById('search').value.toLowerCase();
  const status = document.getElementById('statusFilter').value;
  try {
    const r = await fetch('../api/applicants.php', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.success) { document.getElementById('applicantList').innerHTML = '<div class="empty">Could not load applicants.</div>'; return; }
    APPLICANT_ROWS = (d.data || []);
    const rows = (d.data || []).filter(a => {
      if (search && !(a.name || '').toLowerCase().includes(search) && !(a.email || '').toLowerCase().includes(search)) return false;
      if (status && (a.status || '') !== status) return false;
      return true;
    });
    if (rows.length === 0) { document.getElementById('applicantList').innerHTML = '<div class="empty">No applicants found.</div>'; return; }
    let html = '<table><thead><tr><th>Applicant</th><th>Position</th><th>Interview Mode</th><th>Resume</th><th>Stage</th><th>Applied</th><th>Detail</th></tr></thead><tbody>';
    for (const a of rows) {
      const badgeClass = stageBadgeClass(a.stage, a.status);
      const label = stageLabel(a.stage, a.status);
      const modeBadge = a.interview_mode ? `<span class="mode-badge">${esc(a.interview_mode)}</span>` : '';
      const cvCell = a.cv_path
        ? `<a class="cv-link" href="../${esc(a.cv_path)}" target="_blank" rel="noopener">📄 View CV</a>`
        : '<span class="cv-missing">No file</span>';
      const displayName = (a.first_name && a.last_name) ? `${esc(a.first_name)} ${esc(a.last_name)}` : esc(a.name);
      const rowId = `row-${a.id}`;
      html += `<tr id="${rowId}"><td>${displayName}<br><small style="color:var(--muted)">${esc(a.email)}</small></td><td>${esc(a.position)}</td><td>${modeBadge || '—'}</td><td>${cvCell}</td><td><span class="badge ${badgeClass}">${esc(label)}</span></td><td>${(a.created_at||'').split(' ')[0]}</td><td><button class="detail-btn" onclick="openDetailModal(${a.id})">Detail</button></td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('applicantList').innerHTML = html;
  } catch(e) { document.getElementById('applicantList').innerHTML = '<div class="empty">Could not load applicants.</div>'; }
}

function pgField(label, value, icon){
  const v = (value === null || value === undefined || value === '') ? null : String(value);
  const iconHtml = icon ? `<span class="pg-icon">${icon}</span>` : '';
  return `<div class="pg-item"><div class="pg-label">${iconHtml}${esc(label)}</div><div class="pg-value${v?'':' empty'}">${v?esc(v):'Not provided'}</div></div>`;
}
function pgFieldFull(label, value, icon){
  const v = (value === null || value === undefined || value === '') ? null : String(value);
  const iconHtml = icon ? `<span class="pg-icon">${icon}</span>` : '';
  return `<div class="pg-item profile-full"><div class="pg-label">${iconHtml}${esc(label)}</div><div class="pg-value${v?'':' empty'}">${v?esc(v):'Not provided'}</div></div>`;
}
function initialsOf(a){
  const displayName = (a.first_name && a.last_name) ? `${a.first_name} ${a.last_name}` : (a.name || '');
  const parts = displayName.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length-1][0] : '')).toUpperCase();
}
function fmtDate(d){
  if (!d) return null;
  const parts = String(d).split(' ')[0];
  return parts || null;
}
function calcAgeFromDob(dob){
  if (!dob) return null;
  const b = new Date(dob + (dob.length <= 10 ? 'T00:00:00' : ''));
  if (isNaN(b.getTime())) return null;
  const today = new Date();
  let age = today.getFullYear() - b.getFullYear();
  const m = today.getMonth() - b.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < b.getDate())) age--;
  return age;
}
function renderAppGrid(a){
  const resumeHtml = a.cv_path
    ? `<a class="cv-link" href="../${esc(a.cv_path)}" target="_blank" rel="noopener">📄 View CV</a>`
    : null;
  return `
    ${pgField('Position', a.position, '💼')}
    ${pgField('Interview mode', a.interview_mode, a.interview_mode === 'Online' ? '💻' : '🚶')}
    ${pgField('Applied on', fmtDate(a.created_at), '📅')}
    <div class="pg-item"><div class="pg-label"><span class="pg-icon">📎</span>Resume</div><div class="pg-value${resumeHtml?'':' empty'}">${resumeHtml || 'Not provided'}</div></div>
  `;
}
function renderProfileGrid(a){
  const age = calcAgeFromDob(a.birthdate);
  const birthdateLabel = a.birthdate ? `${fmtDate(a.birthdate)}${age!==null?` (${age} yrs old)`:''}` : null;
  return `
    ${pgField('Contact number', a.contact_number, '📞')}
    ${pgField('Birthdate', birthdateLabel, '🎂')}
    ${pgField('Gender', a.gender, '⚧')}
    ${pgField('Civil status', a.civil_status, '💍')}
    ${pgField('Preferred start date', fmtDate(a.preferred_start_date), '🗓️')}
    ${pgField('Heard about us via', a.referral_source, '📣')}
    ${pgFieldFull('Address', a.address, '📍')}
    ${pgFieldFull('Message to HR', a.notes, '💬')}
  `;
}

// Shared by both the table (previously) and the Detail modal (now) so the
// available actions for an applicant's current stage are computed exactly
// one way, in one place.
function buildActionsHtml(a){
  const currentStage = a.stage || 'Pending';
  const upcoming = nextStage(currentStage);
  if (a.status !== 'Pending') return '<span class="cv-missing">✅ No further actions — this application is closed.</span>';

  let advanceBtn = '';
  if (upcoming === 'Accepted') {
    advanceBtn = `<button class="action-btn accept" onclick="acceptApplicant(${a.id})">✅ Accept for Interview</button>`;
  } else if (upcoming) {
    advanceBtn = `<button class="action-btn advance" onclick="advanceApplicant(${a.id})">➡️ Move to ${esc(upcoming)}</button>`;
  }
  let offerBtn = '', hireBtn = '', offerStatusHtml = '';
  if (currentStage === 'Final Interview') {
    const offerSent = !!a.offer_published_at;
    const esigned = !!(a.registered_signature_path);
    offerBtn = `<button class="action-btn offer" onclick="openOfferModal(${a.id})">📝 ${offerSent ? 'Edit Offer' : 'Publish Offer'}</button>`;
    hireBtn = `<button class="action-btn hire" ${offerSent && esigned ? '' : (offerSent ? 'disabled title="Applicant must e-sign the offer first"' : 'disabled title="Publish the offer first"')} onclick="hireApplicant(${a.id})">🎉 Hire</button>`;
    if (offerSent) {
      offerStatusHtml += '<span class="offer-sent">✓ Offer published to applicant</span>';
      offerStatusHtml += esigned
        ? '<span class="offer-sent" style="background:#e2f0e8;color:#256b4d">✍️ E-signed by applicant</span>'
        : '<span class="offer-sent" style="background:#fff8ea;color:#96690a">⏳ Waiting for applicant e-sign</span>';
    }
  }
  const rejectBtn = `<button class="action-btn reject" onclick="rejectApplicant(${a.id})">✕ Reject</button>`;
  return advanceBtn + offerBtn + hireBtn + rejectBtn + offerStatusHtml;
}

function openDetailModal(id){
  const a = APPLICANT_ROWS.find(x => Number(x.id) === Number(id));
  if (!a) return;
  DETAIL_MODAL_APPLICANT_ID = id;
  const displayName = (a.first_name && a.last_name) ? `${a.first_name} ${a.last_name}` : (a.name || '');
  document.getElementById('detailAvatar').textContent = initialsOf(a);
  document.getElementById('detailName').textContent = displayName;
  document.getElementById('detailEmail').innerHTML = `✉️ ${esc(a.email || '')}`;
  document.getElementById('detailBadge').className = `badge ${stageBadgeClass(a.stage, a.status)}`;
  document.getElementById('detailBadge').textContent = stageLabel(a.stage, a.status);
  document.getElementById('detailAppGrid').innerHTML = renderAppGrid(a);
  document.getElementById('detailProfileGrid').innerHTML = renderProfileGrid(a);
  document.getElementById('detailActions').innerHTML = buildActionsHtml(a);
  document.getElementById('detailModal').classList.add('show');
}
function closeDetailModal(){
  document.getElementById('detailModal').classList.remove('show');
  DETAIL_MODAL_APPLICANT_ID = null;
}
let DETAIL_MODAL_APPLICANT_ID = null;

// Called after any pipeline action succeeds. Reloads the table, then — if
// the Detail modal was open for this applicant and they're still in the
// active pipeline — refreshes the modal in place with the new stage/actions
// instead of leaving it showing stale data. Hiring/rejecting moves them out
// of the actionable flow, so the modal closes in that case instead.
async function refreshAfterAction(id, keepOpen){
  await loadApplicants();
  if (DETAIL_MODAL_APPLICANT_ID === id && keepOpen) {
    openDetailModal(id);
  } else if (DETAIL_MODAL_APPLICANT_ID === id) {
    closeDetailModal();
  }
}
async function acceptApplicant(id){
  if (!await swalAsk('Accept this applicant for interview?')) return;
  try {
    const r = await fetch('../api/applicants.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'accept',id})});
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Failed to accept.'); return; }
    refreshAfterAction(id, true);
  } catch(e) { alert('Failed to accept.'); }
}
async function advanceApplicant(id){
  if (!await swalAsk('Move this applicant to the next interview stage?')) return;
  try {
    const r = await fetch('../api/applicants.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'advance',id})});
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Failed to advance.'); return; }
    refreshAfterAction(id, true);
  } catch(e) { alert('Failed to advance.'); }
}
async function hireApplicant(id){
  if (!await swalAsk('Hire this applicant? This creates their employee record and staff login.')) return;
  try {
    const r = await fetch('../api/applicants.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'hire',id})});
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Failed to hire.'); return; }
    refreshAfterAction(id, false);
  } catch(e) { alert('Failed to hire.'); }
}
function openOfferModal(id){
  document.getElementById('offerApplicantId').value = id;
  document.getElementById('offerDepartment').value = '';
  document.getElementById('offerSalary').value = '';
  document.getElementById('offerNotes').value = '';
  document.getElementById('offerContract').value = '';
  const rec = APPLICANT_ROWS.find(x => Number(x.id) === Number(id));
  window.OFFER_HAS_CONTRACT = !!(rec && rec.contract_path);
  document.getElementById('offerContractHint').textContent = window.OFFER_HAS_CONTRACT
    ? 'A contract is already on file for this applicant — attach a new file only if you want to replace it.'
    : 'Required — the employment contract the applicant will download from their status page (max 8MB).';
  document.getElementById('offerModal').classList.add('show');
}
function closeOfferModal(){
  document.getElementById('offerModal').classList.remove('show');
}
async function submitOffer(){
  const id = parseInt(document.getElementById('offerApplicantId').value, 10);
  // Generalized required-field check (any [required] input in the modal).
  if (!swalValidateRequired(document.getElementById('offerModal'))) return;
  const offer_department = document.getElementById('offerDepartment').value.trim();
  const offer_salary = document.getElementById('offerSalary').value;
  const offer_notes = document.getElementById('offerNotes').value.trim();
  const contractFile = document.getElementById('offerContract').files[0];

  const salaryNum = parseFloat(offer_salary);
  if (!(salaryNum > 0)) { swalAlert('Enter a valid monthly salary.', 'error'); return; }
  if (salaryNum > 1000000000) { swalAlert('Monthly salary cannot exceed ₱1,000,000,000 (1 billion).', 'error'); return; }
  if (!contractFile && !window.OFFER_HAS_CONTRACT) {
    swalAlert('Please attach the contract document (PDF, DOC, or DOCX) — it is required to publish an offer.', 'error');
    return;
  }

  const fd = new FormData();
  fd.append('action', 'publish_offer');
  fd.append('id', id);
  fd.append('offer_department', offer_department);
  fd.append('offer_salary', offer_salary);
  fd.append('offer_notes', offer_notes);
  if (contractFile) fd.append('contract', contractFile);

  try {
    const r = await fetch('../api/applicants.php', {method:'POST', credentials:'same-origin', body: fd});
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Failed to publish offer.'); return; }
    closeOfferModal();
    refreshAfterAction(id, true);
  } catch(e) { alert('Failed to publish offer.'); }
}
async function rejectApplicant(id){
  if (!await swalAsk('Reject this applicant?')) return;
  try {
    const r = await fetch('../api/applicants.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'reject',id})});
    const d = await r.json();
    if (!d.success) { alert(d.message || 'Failed to reject.'); return; }
    refreshAfterAction(id, false);
  } catch(e) { alert('Failed to reject.'); }
}
function clearFilters(){ document.getElementById('search').value=''; document.getElementById('statusFilter').value=''; loadApplicants(); }
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
document.getElementById('search').addEventListener('input', loadApplicants);
document.getElementById('statusFilter').addEventListener('change', loadApplicants);
loadApplicants();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>