<?php session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule Management — HRMS Admin</title>
<style>
  :root {
    --espresso:#3b2313; --cream:#faf6f0; --cream-soft:#f5ede3; --tan:#e8ddd0;
    --gold:#a9714a; --ink:#3b2313; --ink-soft:#7a6055; --line:#e8ddd0;
    --surface:#fff; --bg:#faf6f0; --accent:#a9714a; --accent-soft:#ecd9b5;
    --danger:#a8492f; --danger-soft:#f6e3dc;
  }
  * { box-sizing:border-box; }
  body { margin:0; font-family:Georgia,"Iowan Old Style",-apple-system,BlinkMacSystemFont,"Segoe UI",serif; background:var(--bg); color:var(--ink); }
  .shell { max-width:1100px; margin:0 auto; padding:0 0 64px; }
  .topnav { background:var(--espresso); color:var(--cream); display:flex; align-items:center; justify-content:space-between; padding:14px 24px; margin-bottom:28px; border-radius:0 0 10px 10px; }
  .page-body { padding:0 24px; }
  header.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
  header.page-head h1 { font-size:22px; margin:0 0 4px; }
  header.page-head p { margin:0; color:var(--ink-soft); font-size:13.5px; }
  .eyebrow { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); margin-bottom:6px; }
  .btn { font:inherit; font-size:13.5px; font-weight:800; padding:9px 18px; border-radius:7px; border:1px solid var(--accent); background:var(--accent); color:#fff; cursor:pointer; }
  .btn:hover { opacity:.92; }
  .btn.ghost { background:transparent; color:var(--ink-soft); border-color:var(--line); font-weight:600; }
  .layout { display:grid; grid-template-columns:260px 1fr; gap:20px; align-items:start; }
  .employee-panel { background:var(--surface); border:1px solid var(--line); border-radius:10px; overflow:hidden; box-shadow:0 1px 2px rgba(59,35,19,.06); }
  .employee-panel .panel-head { padding:12px 14px; border-bottom:1px solid var(--line); background:var(--tan); }
  .employee-panel input { width:100%; font:inherit; font-size:13px; padding:7px 9px; border:1px solid var(--line); border-radius:6px; background:var(--surface); }
  .employee-item { padding:12px 14px; border-bottom:1px solid var(--line); cursor:pointer; font-size:13.5px; }
  .employee-item:hover { background:var(--bg); }
  .employee-item.active { background:var(--accent-soft); font-weight:800; }
  .employee-item .role { display:block; font-size:11.5px; color:var(--ink-soft); font-weight:400; margin-top:2px; }
  .schedule-panel { background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:20px; min-height:320px; box-shadow:0 1px 2px rgba(59,35,19,.06); min-width:0; }
  .placeholder { text-align:center; padding:80px 20px; color:var(--ink-soft); }
  .field { margin-bottom:14px; }
  .field label { display:block; font-size:12.5px; font-weight:800; margin-bottom:6px; color:var(--ink); }
  .field select, .field textarea { width:100%; font:inherit; font-size:13.5px; padding:9px 10px; border:1px solid var(--line); border-radius:7px; background:var(--bg); color:var(--ink); }
  .field textarea { resize:vertical; min-height:70px; }
  .field .error-msg { display:none; color:var(--danger); font-size:12px; margin-top:4px; }
  .field.has-error select, .field.has-error textarea, .day-box.has-error { border-color:var(--danger); }
  .field.has-error .error-msg, .day-field.has-error .error-msg { display:block; }
  .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .day-box { border:1px solid var(--line); border-radius:10px; background:var(--bg); padding:12px; display:grid; grid-template-columns:repeat(7, minmax(70px, 1fr)); gap:8px; }
  .day-chip { display:flex; align-items:center; justify-content:center; gap:6px; padding:9px 7px; border:1px solid var(--line); border-radius:999px; background:var(--surface); cursor:pointer; font-size:13px; font-weight:700; }
  .day-chip:has(input:checked) { background:var(--accent); border-color:var(--accent); color:#fff; }
  .day-chip input { margin:0; }
  .note { background:#fff8ea; border:1px solid var(--line); color:var(--accent); padding:10px 12px; border-radius:10px; font-size:13px; margin:0 0 14px; line-height:1.45; }
  .approval-required { background:var(--cream-soft); border:1px solid var(--line); border-radius:10px; padding:11px 12px; margin:14px 0; font-size:13px; font-weight:800; color:var(--accent); }
  .schedule-preview-card { border:1px solid var(--line); border-radius:14px; overflow:hidden; background:var(--surface); margin:14px 0 18px; box-shadow:0 8px 24px rgba(59,35,19,.08); }
  .preview-days { background:var(--espresso); color:var(--cream); padding:13px 16px; font-weight:900; letter-spacing:.02em; display:flex; justify-content:space-between; gap:12px; align-items:center; }
  .preview-days small { color:var(--tan); font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
  .preview-body { display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--line); }
  .preview-time { background:var(--cream-soft); padding:18px 16px; }
  .preview-time label { display:block; font-size:11px; font-weight:900; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px; }
  .preview-time strong { font-size:22px; color:var(--ink); }
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
  .overview-head { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
  .overview-head h3 { margin:0; }
  .overview-head p { margin:4px 0 0; color:var(--ink-soft); font-size:13px; }
  .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:900; margin-left:8px; }
  .status-badge.approved { background:#e2ecdf; color:#4f7a4a; }
  .status-badge.pending { background:#fff8ea; color:var(--accent); border:1px solid var(--line); }
  .status-badge.rejected { background:var(--danger-soft); color:var(--danger); border:1px solid var(--danger); }
  .locked-box { background:#e2ecdf; border:1px solid #b9d4b4; color:#3f6c3b; padding:11px 12px; border-radius:10px; font-size:13px; font-weight:800; margin:0 0 14px; }
  .toast { position:fixed; bottom:24px; right:24px; background:var(--espresso); color:var(--cream); padding:12px 18px; border-radius:9px; font-size:13.5px; font-weight:800; box-shadow:0 8px 24px rgba(59,35,19,.3); display:none; z-index:60; }
  .toast.show { display:block; }
  .toast.error { background:var(--danger); }
  .filter-bar { display:flex; flex-wrap:wrap; gap:6px; padding:10px 12px; border-bottom:1px solid var(--line); background:var(--cream-soft); }
  .filter-chip { font:inherit; font-size:12px; font-weight:800; padding:6px 10px; border-radius:999px; border:1px solid var(--line); background:var(--surface); color:var(--ink-soft); cursor:pointer; }
  .filter-chip:hover { background:var(--bg); }
  .filter-chip.active { background:var(--accent); border-color:var(--accent); color:#fff; }
  @media (max-width:900px){ .day-box{ grid-template-columns:repeat(3,1fr); } }
  @media (max-width:720px){ .layout{ grid-template-columns:1fr; } .field-row,.preview-body{ grid-template-columns:1fr; } }
</style>
<style>
  .topnav { display:none !important; }
  .shell { max-width:none !important; padding-top:24px !important; }
  body { min-height:100vh; }
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="shell">
  <div class="topnav"></div>
  <div class="page-body">
    <header class="page-head">
      <div>
        <div class="eyebrow">Admin · AD-5</div>
        <h1>Schedule Management</h1>
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
<div class="toast" id="toast"></div>
<script>
const API_BASE = '/INVENTORY/hrms/api';
let EMPLOYEES = [];
let SCHEDULES = {};
let selectedEmployeeId = null;
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

function fetchEmployees(){ return fetch(`${API_BASE}/employees.php`).then(r=>r.json()).then(data=>{ EMPLOYEES=data.data||[]; return EMPLOYEES; }); }
function fetchSchedule(employeeId){ return fetch(`${API_BASE}/schedules.php?employee_id=${employeeId}`).then(r=>r.json()).then(data=>{ const schedule=data.data||null; if(schedule) SCHEDULES[employeeId]=schedule; return schedule; }); }
function fetchAllSchedules(){ return fetch(`${API_BASE}/schedules.php`).then(r=>r.json()).then(data=>{ SCHEDULES={}; (data.data||[]).forEach(s=>{ SCHEDULES[Number(s.employee_id)] = s; }); return SCHEDULES; }); }
function saveScheduleToServer(employeeId, shift, approvalComment){
  return fetch(`${API_BASE}/schedules.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ employee_id:employeeId, days:shift.days, start:shift.start, end:shift.end, approval_comment:approvalComment }) })
    .then(r=>r.json()).then(data=>{ if(!data.success) throw new Error(data.message||'Save failed'); SCHEDULES[employeeId]=shift; return shift; });
}
function escapeHtml(str){ const div=document.createElement('div'); div.textContent=str ?? ''; return div.innerHTML; }
function employeeName(id){ const e=EMPLOYEES.find(x=>Number(x.id)===Number(id)); return e ? e.name : ''; }
function employeeRole(id){ const e=EMPLOYEES.find(x=>Number(x.id)===Number(id)); return e ? e.role : ''; }
function parseDays(days){ if(!days) return []; if(days==='Mon–Fri') return ['Mon','Tue','Wed','Thu','Fri']; if(days==='Mon–Sat') return ['Mon','Tue','Wed','Thu','Fri','Sat']; if(days==='Tue–Sat') return ['Tue','Wed','Thu','Fri','Sat']; if(days==='Weekends') return ['Sat','Sun']; return days.split(',').map(d=>d.trim()).filter(Boolean); }
function daysToText(days){ return days.length ? days.join(', ') : 'Select days'; }
function hourlyOptions(selected=''){
  let html='<option value="">Select hour...</option>';
  for(let h=0; h<24; h++){
    const value=String(h).padStart(2,'0') + ':00';
    const hour12 = h % 12 || 12;
    const ampm = h < 12 ? 'AM' : 'PM';
    html += `<option value="${value}" ${value===selected?'selected':''}>${hour12}:00 ${ampm}</option>`;
  }
  return html;
}
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
function selectedDays(){ return [...document.querySelectorAll('input[name="workday"]:checked')].map(x=>x.value); }
function scheduleStatus(schedule){ return schedule ? (schedule.approval_status || schedule.status || 'Pending') : 'No schedule'; }
function cellForDay(schedule, day){
  if(!schedule) return '<td class="off">OFF</td>';
  const days = parseDays(schedule.days || '');
  if(!days.includes(day)) return '<td class="off">OFF</td>';
  return `<td class="shift">${escapeHtml(formatTime(schedule.start))}<br><span style="font-weight:600;color:var(--ink-soft);">to</span><br>${escapeHtml(formatTime(schedule.end))}</td>`;
}
function renderOverview(){
  const panel=document.getElementById('schedulePanel');
  const shown = filterEmployees(EMPLOYEES);
  const rows = shown.map(e=>{
    const s = SCHEDULES[Number(e.id)] || null;
    const status = scheduleStatus(s);
    const approved = String(status).toLowerCase()==='approved';
    const pending = s && !approved;
    return `<tr data-emp-row="${e.id}"><td>${escapeHtml(e.name)}<br><span style="font-weight:500;color:var(--ink-soft);font-size:12px;">${escapeHtml(e.role||'Staff')}</span></td>${DAY_LIST.map(d=>cellForDay(s,d)).join('')}<td class="${approved?'approved-cell':pending?'pending-cell':'off'}">${escapeHtml(status)}</td></tr>`;
  }).join('');
  panel.innerHTML = `<div class="overview-head"><div><h3>${scheduleFilter==='all'?'All Staff':FILTER_LABELS[scheduleFilter]} Schedule Overview</h3><p>Click any staff row on the left to assign or view details. This table helps you see all schedules faster.</p></div><button class="btn ghost" id="refreshOverviewBtn">Refresh</button></div><div class="overview-wrap"><table class="overview-table"><thead><tr><th>Employee</th>${DAY_LIST.map(d=>`<th>${d}</th>`).join('')}<th>Status</th></tr></thead><tbody>${rows || '<tr><td colspan="9">No employees in this filter.</td></tr>'}</tbody></table></div>`;
  document.getElementById('refreshOverviewBtn').addEventListener('click', loadInitial);
  panel.querySelectorAll('[data-emp-row]').forEach(row=>row.addEventListener('click',()=>selectEmployee(Number(row.dataset.empRow))));
}
function loadInitial(){ fetchEmployees().then(()=>fetchAllSchedules()).then(()=>{ updateFilterCounts(); loadEmployeeList(); renderOverview(); }); }
function renderEmployeeList(employees){
  employees = filterEmployees(employees || EMPLOYEES);
  const list=document.getElementById('employeeList');
  if(!employees.length){ list.innerHTML='<div class="employee-item" style="cursor:default;color:var(--ink-soft);">No employees found</div>'; return; }
  list.innerHTML=employees.map(e=>`<div class="employee-item ${Number(e.id)===Number(selectedEmployeeId)?'active':''}" data-emp="${e.id}">${escapeHtml(e.name)}<span class="role">${escapeHtml(e.role)}</span></div>`).join('');
  list.querySelectorAll('[data-emp]').forEach(item=>item.addEventListener('click',()=>selectEmployee(Number(item.dataset.emp))));
}
function loadEmployeeList(){ const search=document.getElementById('employeeSearch').value.trim().toLowerCase(); fetchEmployees().then(employees=>renderEmployeeList(!search?employees:employees.filter(e=>e.name.toLowerCase().includes(search)))); }
document.getElementById('employeeSearch').addEventListener('input', loadEmployeeList);
function selectEmployee(id){ selectedEmployeeId=id; loadEmployeeList(); fetchSchedule(id).then(schedule=>{ if(schedule) renderScheduleView(id,schedule); else renderForm(id,null); }); }
function renderScheduleView(id,schedule){
  const status = schedule.approval_status || schedule.status || 'Pending';
  const statusLower = String(status).toLowerCase();
  const approved = statusLower === 'approved';
  const rejected = statusLower === 'rejected';

  let badgeClass, badgeLabel, noteHtml;
  if (approved) {
    badgeClass = 'approved'; badgeLabel = 'Approved';
    noteHtml = '<div class="locked-box">Approved by superadmin. This schedule is locked and cannot be edited anymore.</div>';
  } else if (rejected) {
    badgeClass = 'rejected'; badgeLabel = 'Rejected';
    const comment = escapeHtml(schedule.rejection_comment || '');
    noteHtml = `<div class="note" style="background:var(--danger-soft);color:var(--danger);border-color:var(--danger);"><strong>Rejected — waiting for the admin to resubmit</strong>${comment ? '<br>'+comment : ''}</div>`
      + (schedule.approval_comment ? `<div class="note">Admin's comment: ${escapeHtml(schedule.approval_comment)}</div>` : '');
  } else {
    badgeClass = 'pending'; badgeLabel = 'Pending approval';
    noteHtml = '<div class="note">This schedule is waiting for superadmin approval. Changes will still require a comment.</div>';
  }

  document.getElementById('schedulePanel').innerHTML=`
    <h3 style="margin-top:0;">${escapeHtml(employeeName(id))}'s current shift <span class="status-badge ${badgeClass}">${badgeLabel}</span></h3>
    ${schedulePreviewHtml(schedule)}
    ${noteHtml}
    <div class="form-actions">${approved ? '' : '<button class="btn ghost" id="editScheduleBtn">Edit schedule</button>'}</div>`;
  if(!approved) document.getElementById('editScheduleBtn').addEventListener('click',()=>renderForm(id,schedule));
}
function renderForm(id, existing){
  const selected = parseDays(existing?.days || '');
  const s = existing || { days:'', start:'', end:'' };
  document.getElementById('schedulePanel').innerHTML=`
    <h3 style="margin-top:0;">${existing?'Edit':'Create'} shift — ${escapeHtml(employeeName(id))}</h3>
    <div class="field day-field" id="field-days">
      <label>Working days <span style="color:var(--ink-soft);font-weight:600;">(choose any number of days)</span></label>
      <div class="day-box" id="dayBox">
        ${DAY_LIST.map(day=>`<label class="day-chip"><input type="checkbox" name="workday" value="${day}" ${selected.includes(day)?'checked':''}> ${day}</label>`).join('')}
      </div>
      <div class="error-msg">Please choose at least one working day.</div>
    </div>
    <div class="field-row">
      <div class="field" id="field-start"><label for="input-start">Start time <span style="color:var(--ink-soft);font-weight:600;">(per hour)</span></label><select id="input-start">${hourlyOptions(s.start)}</select><div class="error-msg">This field is required.</div></div>
      <div class="field" id="field-end"><label for="input-end">End time <span style="color:var(--ink-soft);font-weight:600;">(per hour)</span></label><select id="input-end">${hourlyOptions(s.end)}</select><div class="error-msg">This field is required.</div></div>
    </div>
    <div id="liveSchedulePreview">${schedulePreviewHtml(s)}</div>
    <div class="approval-required">Approval required: every schedule assignment will be sent automatically to the superadmin.</div>
    <div class="note" id="commentHelp">If you choose less than 5 days, explain why so the superadmin will not be confused.</div>
    <div class="field" id="field-comment"><label for="input-comment">Comment for superadmin <span style="color:var(--danger);">required</span></label><textarea id="input-comment" placeholder="Example: Maria will only work Mon, Wed, Fri this week because..."></textarea><div class="error-msg">A comment is required for superadmin approval.</div></div>
    <div class="form-actions"><button class="btn ghost" id="cancelFormBtn">Cancel</button><button class="btn" id="saveScheduleBtn">Send schedule for approval</button></div>`;
  document.getElementById('cancelFormBtn').addEventListener('click',()=>{ if(existing) renderScheduleView(id,existing); else renderOverview(); });
  function updateLivePreview(){
    const days = selectedDays();
    const draft = { days: daysToText(days), start: document.getElementById('input-start').value, end: document.getElementById('input-end').value };
    document.getElementById('liveSchedulePreview').innerHTML = schedulePreviewHtml(draft);
    document.getElementById('commentHelp').textContent = days.length < 5 ? 'You selected fewer than 5 days. Please explain the reason clearly for superadmin approval.' : 'Please explain the schedule assignment for superadmin approval.';
  }
  document.querySelectorAll('input[name="workday"]').forEach(el=>el.addEventListener('change', updateLivePreview));
  ['input-start','input-end'].forEach(id=>document.getElementById(id).addEventListener('change', updateLivePreview));
  updateLivePreview();
  document.getElementById('saveScheduleBtn').addEventListener('click',()=>{
    const daysArr=selectedDays(); const days=daysToText(daysArr); const start=document.getElementById('input-start').value; const end=document.getElementById('input-end').value; const comment=document.getElementById('input-comment').value.trim();
    let valid=true;
    document.getElementById('field-days').classList.toggle('has-error', daysArr.length===0); if(daysArr.length===0) valid=false;
    document.getElementById('field-start').classList.toggle('has-error', !start); if(!start) valid=false;
    document.getElementById('field-end').classList.toggle('has-error', !end); if(!end) valid=false;
    if(start && end && start>=end){ const endField=document.getElementById('field-end'); endField.classList.add('has-error'); endField.querySelector('.error-msg').textContent='End time must be after start time.'; valid=false; }
    else document.querySelector('#field-end .error-msg').textContent='This field is required.';
    document.getElementById('field-comment').classList.toggle('has-error', !comment); if(!comment) valid=false;
    if(!valid) return;
    const shift={ days, start, end, approval_status:'Pending' };
    saveScheduleToServer(id, shift, comment).then(()=>{ showToast('Schedule sent to superadmin for approval'); renderScheduleView(id,shift); }).catch(err=>showToast(err.message,true));
  });
}
let toastTimer; function showToast(msg,isError){ const toast=document.getElementById('toast'); toast.textContent=msg; toast.className='toast show'+(isError?' error':''); clearTimeout(toastTimer); toastTimer=setTimeout(()=>toast.classList.remove('show'),3200); }
loadInitial();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>