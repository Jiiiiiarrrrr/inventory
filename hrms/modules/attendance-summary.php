<?php session_start();
?>
<!DOCTYPE html><html><head>
<link rel="icon" type="image/png" href="../../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../../favicon.ico?v=2"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;background:#faf6f0;color:#3b2313;font-family:Georgia,serif;padding:24px}.grid{display:grid;grid-template-columns:280px 1fr;gap:18px}.panel,.card{background:white;border:1px solid #e8ddd0;border-radius:12px;min-width:0}.panel input{margin:12px;width:calc(100% - 24px);padding:10px;border:1px solid #e8ddd0;border-radius:8px;font:inherit}.emp{padding:12px;border-top:1px solid #e8ddd0;cursor:pointer}.emp.active{background:#f5ede3;font-weight:900}.card{padding:18px;overflow:auto;max-width:100%}.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:14px 0}.stat{border:1px solid #e8ddd0;border-radius:10px;padding:12px;background:#fff}.stat label{display:block;color:#7a6055;font-size:12px;font-weight:900;text-transform:uppercase}.stat strong{font-size:23px}table{width:100%;border-collapse:collapse;min-width:940px}th{background:#4f8a35;color:white;text-align:left;padding:9px 8px;font-size:12.5px}td{border:1px solid #b9dca9;padding:7px 8px;font-size:12.5px;vertical-align:middle}.badge{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:900}.Present{background:#e2ecdf;color:#4f7a4a}.Late,.Undertime{background:#fff8ea;color:#a9714a}.Absent{background:#f6e3dc;color:#a8492f}.Incomplete{background:#eee;color:#777}.Day{background:#eee;color:#777}.thumb{width:56px;height:42px;object-fit:cover;border-radius:6px;border:1px solid #e8ddd0}.filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.filters input{padding:9px;border:1px solid #e8ddd0;border-radius:8px;font:inherit}.load-error{color:#a8492f;padding:20px 0}</style>
<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head><body>
<h1>Monthly Attendance Summary</h1>
<p>Superadmin-only detailed monthly attendance report per employee.</p>
<div class="grid">
  <aside class="panel"><input id="search" placeholder="Search employee..." autocomplete="off" spellcheck="false"><div id="empList">Loading...</div></aside>
  <main class="card" id="content">Select an employee.</main>
</div>
<script>
const API='../api';
let EMP=[],selected=null;

function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
function img(p){return p?`<a href="../${p}" target=_blank><img class=thumb src="../${p}" onerror="this.closest('a').outerHTML='<span style=&quot;color:#7a6055;font-size:12px&quot;>Photo missing</span>'"></a>`:'—'}

function apiFetch(url, opts){
  return fetch(url, opts).then(r=>r.text().then(text=>{
    let data;
    try { data = JSON.parse(text); }
    catch(e){ throw new Error('Server did not return valid JSON (HTTP '+r.status+'). '+text.slice(0,200)); }
    if (!r.ok && !data.success) throw new Error(data.message || ('Request failed (HTTP '+r.status+')'));
    return data;
  }));
}

async function loadEmp(){
  try {
    const d = await apiFetch(`${API}/employees.php`);
    EMP = d.data || [];
    renderEmp();
  } catch(e) {
    document.getElementById('empList').innerHTML = `<div class="load-error" style="padding:12px">${esc(e.message)}</div>`;
  }
}
function renderEmp(){
  let q = search.value.toLowerCase();
  let list = EMP.filter(e => !q || `${e.name} ${e.email}`.toLowerCase().includes(q));
  empList.innerHTML = list.map(e=>`<div class="emp ${selected==e.id?'active':''}" data-emp="${e.id}">${esc(e.name)}<br><small>${esc(e.role)}</small></div>`).join('') || 'No employees';
  empList.querySelectorAll('[data-emp]').forEach(el=>el.addEventListener('click', ()=>pick(Number(el.dataset.emp))));
}
async function pick(id){
  selected = id;
  renderEmp();
  let month = document.getElementById('month')?.value || new Date().toISOString().slice(0,7);
  content.innerHTML = 'Loading summary...';
  try {
    const d = await apiFetch(`${API}/attendance_summary.php?employee_id=${id}&month=${month}`);
    const s = d.summary;
    content.innerHTML = `<div class=filters><h2 style="margin-right:auto">${esc(d.employee.name)}</h2><label>Month <input id=month type=month value="${d.month}"></label></div><div class=stats><div class=stat><label>Present</label><strong>${s.present}</strong></div><div class=stat><label>Absent</label><strong>${s.absent}</strong></div><div class=stat><label>Worked</label><strong>${s.worked}</strong></div><div class=stat><label>Late</label><strong>${s.late}</strong></div><div class=stat><label>Undertime</label><strong>${s.undertime}</strong></div></div><table><thead><tr><th>Date</th><th>Day</th><th>Time In</th><th>Photo</th><th>Time Out</th><th>Photo</th><th>Worked</th><th>Late</th><th>Undertime</th><th>Status</th></tr></thead><tbody>${d.rows.map(x=>`<tr><td>${x.date}</td><td>${x.day}</td><td>${x.time_in?new Date(x.time_in).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}):'—'}</td><td>${img(x.in_photo)}</td><td>${x.time_out?new Date(x.time_out).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}):'—'}</td><td>${img(x.out_photo)}</td><td>${x.worked}</td><td>${x.late}</td><td>${x.undertime}</td><td><span class="badge ${x.status.split(' ')[0]}">${x.status}</span></td></tr>`).join('')}</tbody></table>`;
    document.getElementById('month').addEventListener('change', ()=>pick(id));
  } catch(e) {
    content.innerHTML = `<div class="load-error"><b>Could not load attendance summary.</b><br><span style="font-size:12.5px">${esc(e.message)}</span></div>`;
  }
}
search.oninput = renderEmp;
loadEmp();
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body></html>