<?php session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule Management — HRMS Superadmin</title>
<style>
  :root {
    --espresso:#3b2313; --cream:#faf6f0; --cream-soft:#f5ede3; --tan:#e8ddd0;
    --gold:#a9714a; --ink:#3b2313; --ink-soft:#7a6055; --line:#e8ddd0;
    --surface:#fff; --bg:#faf6f0; --accent:#a9714a; --accent-soft:#ecd9b5;
    --danger:#a8492f; --danger-soft:#f6e3dc; --ok:#4f7a4a; --ok-soft:#e2ecdf;
  }
  * { box-sizing:border-box; }
  body { margin:0; font-family:Georgia,"Iowan Old Style",-apple-system,BlinkMacSystemFont,"Segoe UI",serif; background:var(--bg); color:var(--ink); }
  .shell { max-width:1100px; margin:0 auto; padding:0 0 64px; }
  .page-body { padding:0 24px; }
  header.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
  header.page-head h1 { font-size:22px; margin:0 0 4px; }
  header.page-head p { margin:0; color:var(--ink-soft); font-size:13.5px; }
  .eyebrow { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); margin-bottom:6px; }
  .btn { font:inherit; font-size:13.5px; font-weight:800; padding:9px 18px; border-radius:7px; border:1px solid var(--accent); background:var(--accent); color:#fff; cursor:pointer; }
  .btn:hover { opacity:.92; }
  .btn.ghost { background:transparent; color:var(--ink-soft); border-color:var(--line); font-weight:600; }
  .btn.ok { background:var(--ok); border-color:var(--ok); }
  .btn.danger { background:var(--danger); border-color:var(--danger); }
  .btn:disabled { opacity:.5; cursor:not-allowed; }
  .layout { display:grid; grid-template-columns:260px 1fr; gap:20px; align-items:start; }
  .employee-panel { background:var(--surface); border:1px solid var(--line); border-radius:10px; overflow:hidden; box-shadow:0 1px 2px rgba(59,35,19,.06); }
  .employee-panel .panel-head { padding:12px 14px; border-bottom:1px solid var(--line); background:var(--tan); }
  .filter-bar { display:flex; flex-wrap:wrap; gap:6px; padding:10px 12px; border-bottom:1px solid var(--line); background:var(--cream-soft); }
  .filter-chip { font:inherit; font-size:12px; font-weight:800; padding:6px 10px; border-radius:999px; border:1px solid var(--line); background:var(--surface); color:var(--ink-soft); cursor:pointer; }
  .filter-chip:hover { background:var(--bg); }
  .filter-chip.active { background:var(--accent); border-color:var(--accent); color:#fff; }
  .employee-panel input { width:100%; font:inherit; font-size:13px; padding:7px 9px; border:1px solid var(--line); border-radius:6px; background:var(--surface); }
  .employee-item { padding:12px 14px; border-bottom:1px solid var(--line); cursor:pointer; font-size:13.5px; }
  .employee-item:hover { background:var(--bg); }
  .employee-item.active { background:var(--accent-soft); font-weight:800; }
  .employee-item .role { display:block; font-size:11.5px; color:var(--ink-soft); font-weight:400; margin-top:2px; }
  .schedule-panel { background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:20px; min-height:320px; box-shadow:0 1px 2px rgba(59,35,19,.06); min-width:0; }
  .placeholder { text-align:center; padding:80px 20px; color:var(--ink-soft); }
  .schedule-preview-card { border:1px solid var(--line); border-radius:14px; overflow:hidden; background:var(--surface); margin:14px 0 18px; box-shadow:0 8px 24px rgba(59,35,19,.08); }
  .preview-days { background:var(--espresso); color:var(--cream); padding:13px 16px; font-weight:900; letter-spacing:.02em; display:flex; justify-content:space-between; gap:12px; align-items:center; }
  .preview-days small { color:var(--tan); font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
  .preview-body { display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--line); }
  .preview-time { background:var(--cream-soft); padding:18px 16px; }
  .preview-time label { display:block; font-size:11px; font-weight:900; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px; }
  .preview-time strong { font-size:22px; color:var(--ink); }
  .note { background:#fff8ea; border:1px solid var(--line); color:var(--accent); padding:10px 12px; border-radius:10px; font-size:13px; margin:0 0 14px; line-height:1.45; }
  .locked-box { background:var(--ok-soft); border:1px solid #b9d4b4; color:#3f6c3b; padding:11px 12px; border-radius:10px; font-size:13px; font-weight:800; margin:0 0 14px; }
  .rejected-box { background:var(--danger-soft); border:1px solid #e0b3a4; color:var(--danger); padding:11px 12px; border-radius:10px; font-size:13px; margin:0 0 14px; line-height:1.5; }
  .rejected-box b { display:block; margin-bottom:4px; }
  .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:900; margin-left:8px; }
  .status-badge.approved { background:var(--ok-soft); color:var(--ok); }
  .status-badge.pending { background:#fff8ea; color:var(--accent); border:1px solid var(--line); }
  .status-badge.rejected { background:var(--danger-soft); color:var(--danger); }
  .form-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; flex-wrap:wrap; }
  .overview-wrap { overflow:auto; border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:100%; }
  .overview-table { min-width:820px; }
  .overview-table td, .overview-table th { padding:8px 7px; font-size:12.5px; }
  .overview-table { width:100%; border-collapse:collapse; font-size:13px; }
  .overview-table th { background:#4f8a35; color:#fff; padding:10px 9px; text-align:center; border:1px solid #91c47a; font-size:12px; letter-spacing:.03em; text-transform:uppercase; }
  .overview-table th:first-child { text-align:left; min-width:190px; }
  .overview-table td { padding:9px; border:1px solid #b9dca9; text-align:center; background:#fbfff8; }
  .overview-table td:first-child { text-align:left; font-weight:800; background:#fff; }
  .overview-table .off { color:var(--ink-soft); font-weight:700; background:#f3f8ef; }
  .overview-table .shift { color:var(--ink); font-weight:800; background:#fff; }
  .overview-table .approved-cell { background:#e2ecdf; color:#4f7a4a; font-weight:900; }
  .overview-table .pending-cell { background:#fff8ea; color:var(--accent); font-weight:900; }
  .overview-table .rejected-cell { background:#f6e3dc; color:var(--danger); font-weight:900; }
  .overview-head { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
  .overview-head h3 { margin:0; }
  .overview-head p { margin:4px 0 0; color:var(--ink-soft); font-size:13px; }
  .toast { position:fixed; bottom:24px; right:24px; background:var(--espresso); color:var(--cream); padding:12px 18px; border-radius:9px; font-size:13.5px; font-weight:800; box-shadow:0 8px 24px rgba(59,35,19,.3); display:none; z-index:60; max-width:420px; }
  .toast.show { display:block; }
  .toast.error { background:var(--danger); }
  .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(59,35,19,.35); z-index:100; align-items:center; justify-content:center; padding:20px; }
  .modal-backdrop.show { display:flex; }
  .modal { background:#fff; border-radius:14px; padding:24px; width:100%; max-width:420px; }
  .modal h2 { font-size:18px; margin-bottom:4px; }
  .modal p.sub { color:var(--ink-soft); font-size:13px; margin:0 0 16px; }
  .modal label { display:block; font-size:12px; font-weight:700; color:var(--ink-soft); margin:0 0 6px; }
  .modal textarea { width:100%; font:inherit; font-size:14px; padding:10px 12px; border:1.5px solid var(--line); border-radius:8px; background:var(--bg); min-height:90px; resize:vertical; }
  .modal-actions { display:flex; gap:10px; margin-top:18px; }
  .modal-actions .btn { flex:1; }
  @media (max-width:720px){ .layout{ grid-template-columns:1fr; } .preview-body{ grid-template-columns:1fr; } }
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="shell">
  <div class="page-body">
    <header class="page-head">
      <div>
        <div class="eyebrow">Superadmin · SA-5</div>
        <h1>Schedule Management</h1>
        <p>View and manage employee shift schedules directly.</p>
      </div>
    </header>
    <div class="layout">
      <div class="employee-panel">
        <div class="panel-head"><input type="text" id="employeeSearch" placeholder="Search employee…"></div>
        <div class="filter-bar" id="scheduleFilterBar">
          <button class="filter-chip active" data-filter="all">All</button>
          <button class="filter-chip" data-filter="none">No schedule</button>
          <button class="filter-chip" data-filter="pending">Pending</button>
          <button class="filter-chip" data-filter="approved">Approved</button>
          <button class="filter-chip" data-filter="rejected">Rejected</button>
        </div>
        <div id="employeeList"></div>
      </div>
      <div class="schedule-panel" id="schedulePanel"><div class="placeholder"><p>Select an employee to view or manage their schedule.</p></div></div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="rejectModal">
  <div class="modal">
    <h2>Reject schedule</h2>
    <p class="sub">Explain why so the admin knows what to change.</p>
    <label for="rejectComment">Comment <span style="color:var(--danger)">required</span></label>
    <textarea id="rejectComment" placeholder="e.g. Please confirm coverage for the Saturday shift before resubmitting."></textarea>
    <div class="modal-actions">
      <button class="btn ghost" id="rejectCancelBtn">Cancel</button>
      <button class="btn danger" id="rejectConfirmBtn">Reject schedule</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
const API_BASE = '../api';
let EMPLOYEES = [];
let SCHEDULES = {};
let selectedEmployeeId = null;
let rejectTargetId = null;
let scheduleFilter = 'all';
const FILTER_LABELS = { all:'All', none:'No schedule', pending:'Pending', approved:'Approved', rejected:'Rejected' };
function empScheduleStatus(id){ const s = SCHEDULES[Number(id)]; return s ? String(s.approval_status || s.status || 'Pending').toLowerCase() : 'none'; }
function filterEmployees(list){ if (scheduleFilter === 'all') return list; return list.filter(e => empScheduleStatus(e.id) === scheduleFilter); }
function updateFilterCounts(){
  const counts = { all: EMPLOYEES.length, none:0, pending:0, approved:0, rejected:0 };
  EMPLOYEES.forEach(e => { const st = empScheduleStatus(e.id); counts[st] = (counts[st] || 0) + 1; });
  document.querySelectorAll('#scheduleFilterBar .filter-chip').forEach(b => {
    const f = b.dataset.filter;
    b.classList.toggle('active', f === scheduleFilter);
    b.textContent = FILTER_LABELS[f] + ' (' + (counts[f] || 0) + ')';
  });
}
document.getElementById('scheduleFilterBar').addEventListener('click', e => {
  const b = e.target.closest('.filter-chip'); if (!b) return;
  scheduleFilter = b.dataset.filter;
  updateFilterCounts(); loadEmployeeList(); renderOverview();
});
const DAY_LIST = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function apiFetch(url, opts){
  return fetch(url, opts).then(r=>r.text().then(text=>{
    let data;
    try { data = JSON.parse(text); }
    catch(e){ throw new Error('Server did not return valid JSON (HTTP '+r.status+'). '+text.slice(0,200)); }
    if (!r.ok && !data.success) throw new Error(data.message || ('Request failed (HTTP '+r.status+')'));
    return data;
  }));
}

function fetchEmployees(){ return apiFetch(`${API_BASE}/employees.php`).then(d=>{ EMPLOYEES = d.data || []; return EMPLOYEES; }); }
function fetchSchedule(employeeId){ return apiFetch(`${API_BASE}/schedules.php?employee_id=${employeeId}`).then(d=>{ const schedule = d.data || null; if (schedule) SCHEDULES[employeeId] = schedule; return schedule; }); }
function fetchAllSchedules(){ return apiFetch(`${API_BASE}/schedules.php`).then(d=>{ SCHEDULES = {}; (d.data||[]).forEach(s=>{ SCHEDULES[Number(s.employee_id)] = s; }); return SCHEDULES; }); }

function approveSchedule(employeeId){
  return apiFetch(`${API_BASE}/schedules.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'approve', employee_id: employeeId }) });
}
function rejectSchedule(employeeId, comment){
  return apiFetch(`${API_BASE}/schedules.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'reject', employee_id: employeeId, comment }) });
}

function escapeHtml(str){ const div=document.createElement('div'); div.textContent=str ?? ''; return div.innerHTML; }
function employeeName(id){ const e=EMPLOYEES.find(x=>Number(x.id)===Number(id)); return e ? e.name : ''; }
function parseDays(days){ if(!days) return []; if(days==='Mon–Fri') return ['Mon','Tue','Wed','Thu','Fri']; if(days==='Mon–Sat') return ['Mon','Tue','Wed','Thu','Fri','Sat']; if(days==='Tue–Sat') return ['Tue','Wed','Thu','Fri','Sat']; if(days==='Weekends') return ['Sat','Sun']; return days.split(',').map(d=>d.trim()).filter(Boolean); }
function formatTime(time){
  if(!time) return '--:--';
  const [h,m]=time.split(':').map(Number); const d=new Date(); d.setHours(h||0,m||0,0,0);
  return d.toLocaleTimeString([], {hour:'numeric', minute:'2-digit'});
}
function schedulePreviewHtml(schedule){
  return `<div class="schedule-preview-card">
    <div class="preview-days"><span>${escapeHtml(schedule.days || 'Select days')}</span><small>Preview for superadmin</small></div>
    <div class="preview-body">
      <div class="preview-time"><label>Start time</label><strong>${escapeHtml(formatTime(schedule.start))}</strong></div>
      <div class="preview-time"><label>End time</label><strong>${escapeHtml(formatTime(schedule.end))}</strong></div>
    </div>
  </div>`;
}
function scheduleStatus(schedule){ return schedule ? (schedule.approval_status || 'Pending') : 'No schedule'; }
function cellForDay(schedule, day){
  if(!schedule) return '<td class="off">OFF</td>';
  const days = parseDays(schedule.days || '');
  if(!days.includes(day)) return '<td class="off">OFF</td>';
  return `<td class="shift">${escapeHtml(formatTime(schedule.start))}<br><span style="font-weight:600;color:var(--ink-soft);">to</span><br>${escapeHtml(formatTime(schedule.end))}</td>`;
}
function statusCellClass(status){
  const s = String(status).toLowerCase();
  if (s === 'approved') return 'approved-cell';
  if (s === 'rejected') return 'rejected-cell';
  if (s === 'pending') return 'pending-cell';
  return 'off';
}

function renderOverview(){
  const panel = document.getElementById('schedulePanel');
  const rows = filterEmployees(EMPLOYEES).map(e=>{
    const s = SCHEDULES[Number(e.id)] || null;
    const status = scheduleStatus(s);
    return `<tr data-emp-row="${e.id}"><td>${escapeHtml(e.name)}<br><span style="font-weight:500;color:var(--ink-soft);font-size:12px;">${escapeHtml(e.role||'Staff')}</span></td>${DAY_LIST.map(d=>cellForDay(s,d)).join('')}<td class="${statusCellClass(status)}">${escapeHtml(status)}</td></tr>`;
  }).join('');
  panel.innerHTML = `<div class="overview-head"><div><h3>${scheduleFilter==='all'?'All Staff':FILTER_LABELS[scheduleFilter]} Schedule Overview</h3><p>Click any staff row on the left to review, approve, or reject their schedule.</p></div><button class="btn ghost" id="refreshOverviewBtn">Refresh</button></div><div class="overview-wrap"><table class="overview-table"><thead><tr><th>Employee</th>${DAY_LIST.map(d=>`<th>${d}</th>`).join('')}<th>Status</th></tr></thead><tbody>${rows || '<tr><td colspan="9">No employees in this filter.</td></tr>'}</tbody></table></div>`;
  document.getElementById('refreshOverviewBtn').addEventListener('click', loadInitial);
  panel.querySelectorAll('[data-emp-row]').forEach(row=>row.addEventListener('click',()=>selectEmployee(Number(row.dataset.empRow))));
}

function loadInitial(){
  fetchEmployees()
    .then(()=>fetchAllSchedules())
    .then(()=>{ updateFilterCounts(); loadEmployeeList(); renderOverview(); })
    .catch(err=>{
      document.getElementById('schedulePanel').innerHTML = `<div class="placeholder"><p style="color:var(--danger)">Could not load schedules.<br><small>${escapeHtml(err.message)}</small></p></div>`;
      showToast(err.message, true);
    });
}

function renderEmployeeList(employees){
  employees = filterEmployees(employees || EMPLOYEES);
  const list = document.getElementById('employeeList');
  if(!employees.length){ list.innerHTML = '<div class="employee-item" style="cursor:default;color:var(--ink-soft);">No employees found</div>'; return; }
  list.innerHTML = employees.map(e=>`<div class="employee-item ${Number(e.id)===Number(selectedEmployeeId)?'active':''}" data-emp="${e.id}">${escapeHtml(e.name)}<span class="role">${escapeHtml(e.role)}</span></div>`).join('');
  list.querySelectorAll('[data-emp]').forEach(item=>item.addEventListener('click',()=>selectEmployee(Number(item.dataset.emp))));
}
function loadEmployeeList(){
  const search = document.getElementById('employeeSearch').value.trim().toLowerCase();
  fetchEmployees().then(employees=>renderEmployeeList(!search ? employees : employees.filter(e=>e.name.toLowerCase().includes(search))));
}
document.getElementById('employeeSearch').addEventListener('input', loadEmployeeList);

function selectEmployee(id){
  selectedEmployeeId = id;
  loadEmployeeList();
  fetchSchedule(id)
    .then(schedule=>renderScheduleView(id, schedule))
    .catch(err=>{
      document.getElementById('schedulePanel').innerHTML = `<div class="placeholder"><p style="color:var(--danger)">Could not load this employee's schedule.<br><small>${escapeHtml(err.message)}</small></p></div>`;
      showToast(err.message, true);
    });
}

function renderScheduleView(id, schedule){
  const panel = document.getElementById('schedulePanel');
  if (!schedule){
    panel.innerHTML = `<div class="placeholder"><p>${escapeHtml(employeeName(id))} has no schedule submitted yet.</p></div>`;
    return;
  }
  const status = (schedule.approval_status || 'Pending');
  const s = status.toLowerCase();
  const badgeClass = s === 'approved' ? 'approved' : (s === 'rejected' ? 'rejected' : 'pending');

  let statusBlockHtml = '';
  if (s === 'approved') {
    statusBlockHtml = '<div class="locked-box">Approved. This schedule is locked and cannot be edited by the admin anymore.</div>';
  } else if (s === 'rejected') {
    statusBlockHtml = `<div class="rejected-box"><b>Rejected — waiting for the admin to resubmit</b>${escapeHtml(schedule.rejection_comment || 'No comment was given.')}</div>`;
  } else {
    statusBlockHtml = '<div class="note">This schedule is waiting for superadmin approval.</div>';
  }

  let actionsHtml = '';
  if (s === 'pending') {
    actionsHtml = `<button class="btn danger" id="rejectBtn">Reject</button><button class="btn ok" id="approveBtn">Approve</button>`;
  }

  panel.innerHTML = `
    <h3 style="margin-top:0;">${escapeHtml(employeeName(id))}'s current shift <span class="status-badge ${badgeClass}">${escapeHtml(status)}</span></h3>
    ${schedulePreviewHtml(schedule)}
    ${statusBlockHtml}
    ${schedule.approval_comment ? `<div class="note"><b>Admin's comment:</b> ${escapeHtml(schedule.approval_comment)}</div>` : ''}
    <div class="form-actions">${actionsHtml}</div>`;

  if (s === 'pending') {
    document.getElementById('approveBtn').addEventListener('click', ()=>{
      document.getElementById('approveBtn').disabled = true;
      approveSchedule(id)
        .then(()=>{ showToast('Schedule approved.'); selectEmployee(id); loadInitial(); })
        .catch(err=>{ showToast(err.message, true); document.getElementById('approveBtn').disabled = false; });
    });
    document.getElementById('rejectBtn').addEventListener('click', ()=>openRejectModal(id));
  }
}

function openRejectModal(id){
  rejectTargetId = id;
  document.getElementById('rejectComment').value = '';
  document.getElementById('rejectModal').classList.add('show');
  document.getElementById('rejectComment').focus();
}
function closeRejectModal(){
  document.getElementById('rejectModal').classList.remove('show');
  rejectTargetId = null;
}
document.getElementById('rejectCancelBtn').addEventListener('click', closeRejectModal);
document.getElementById('rejectModal').addEventListener('click', (e)=>{ if (e.target.id === 'rejectModal') closeRejectModal(); });
document.getElementById('rejectConfirmBtn').addEventListener('click', ()=>{
  const comment = document.getElementById('rejectComment').value.trim();
  if (!comment) { showToast('A comment is required to reject a schedule.', true); return; }
  const id = rejectTargetId;
  document.getElementById('rejectConfirmBtn').disabled = true;
  rejectSchedule(id, comment)
    .then(()=>{ closeRejectModal(); showToast('Schedule rejected.'); selectEmployee(id); loadInitial(); })
    .catch(err=>{ showToast(err.message, true); })
    .finally(()=>{ document.getElementById('rejectConfirmBtn').disabled = false; });
});

let toastTimer;
function showToast(msg, isError){
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className = 'toast show' + (isError ? ' error' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>toast.classList.remove('show'), 4500);
}

loadInitial();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>