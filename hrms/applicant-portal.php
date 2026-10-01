<?php session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Applicant Portal</title>
<style>
:root{--espresso:#3b2313;--cream:#faf6f0;--line:#e8ddd0;--accent:#a9714a;--muted:#7a6055;--surface:#fff;--success:#4f7a4a;--success-bg:#e2ecdf;--danger:#a8492f;--danger-bg:#f6e3dc;--blue:#2f6690;--blue-bg:#ddeaf5;--violet:#6d4a90;--violet-bg:#eee6f8;--warn-bg:#fff8ea}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--cream);color:var(--espresso);font-family:Georgia,"Iowan Old Style",serif}.top{background:var(--espresso);color:var(--cream);padding:14px 24px;display:flex;justify-content:space-between;align-items:center}.top b{font-size:17px}.logout{border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:var(--cream);border-radius:999px;padding:8px 14px;font:inherit;font-weight:900;cursor:pointer}.wrap{max-width:920px;margin:auto;padding:28px 24px}.card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:24px;margin:14px 0;box-shadow:0 2px 12px rgba(59,35,19,.08)}h1{margin:0 0 6px}.muted{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:900}.Under{background:#ecd9b5;color:var(--accent)}.Pre-Interview{background:var(--warn-bg);color:var(--accent)}.Actual{background:var(--blue-bg);color:var(--blue)}.Post-Interview{background:var(--violet-bg);color:var(--violet)}.Hired,.Converted{background:var(--success-bg);color:var(--success)}.Rejected{background:var(--danger-bg);color:var(--danger)}.info-grid{display:grid;grid-template-columns:160px 1fr;gap:0;border-top:1px solid var(--line);margin-top:18px}.label,.value{padding:11px 0;border-bottom:1px solid var(--line)}.label{font-weight:900;color:var(--muted)}.value{font-weight:900;text-align:right;overflow-wrap:anywhere}.timeline{margin-top:18px}.timeline h3{margin:0 0 10px}.step{display:grid;grid-template-columns:145px 1fr;gap:12px;padding:12px;border:1px solid var(--line);border-radius:10px;margin-bottom:10px;background:#fff}.step-title{font-weight:900;color:var(--accent)}.step-body{line-height:1.45}.empty{color:var(--muted);font-style:italic}.help{background:var(--warn-bg);border:1px solid var(--line);border-radius:10px;padding:14px;margin-top:18px;color:var(--accent);line-height:1.4}.staffbox{background:var(--success-bg);color:var(--success);border-color:#b9d4b4}.cv-link{display:inline-block;margin-top:8px;color:var(--accent);font-weight:900}.loading{text-align:center;color:var(--muted);padding:40px 20px}@media(max-width:650px){.info-grid,.step{grid-template-columns:1fr}.value{text-align:left}.label{border-bottom:0;padding-bottom:2px}.value{padding-top:0}}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>
<div class="top"><b>Applicant Portal</b><button class="logout" id="logoutBtn">Logout</button></div>
<div class="wrap"><div class="card"><h1>My Application Status</h1><p class="muted">This page is read-only. You can view your application and interview updates here.</p></div><div id="results"><div class="card loading">Loading...</div></div></div>
<script src="../alerts.js"></script>
<script>
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
function cls(s){return String(s||'').split(' ')[0].replace(/[^a-zA-Z-]/g,'')}
function cleanNoteLine(line){return String(line||'').replace(/^HR\s*Notes\s*:?/i,'').trim()}
function parseNotes(notes){
  const raw=String(notes||'').replace(/\r/g,'\n');
  const lines=raw.split('\n').map(x=>x.trim()).filter(Boolean);
  const out=[]; let cv=null;
  lines.forEach(line=>{
    const cvMatch=line.match(/CV:\s*(\S+)/i); if(cvMatch){cv=cvMatch[1]; line=line.replace(/CV:\s*\S+/i,'').trim();}
    line=cleanNoteLine(line); if(!line) return;
    let title='HR Note', body=line;
    if(/^Actual Interview:/i.test(line)){title='Actual Interview'; body=line.replace(/^Actual Interview:\s*/i,'');}
    else if(/^Interview:/i.test(line)){title='Interview'; body=line.replace(/^Interview:\s*/i,'');}
    else if(/^Post-Interview/i.test(line)){title='Post-Interview'; body='Follow-up started.';}
    else if(/^Final decision/i.test(line)){title='Final Decision'; body=line.replace(/^Final decision\s*/i,'').replace(/^\((.*?)\):\s*/,'$1 — ');}
    else if(/^Converted to official staff account/i.test(line)){title='Staff Account'; body='Your official staff account has been created.';}
    out.push({title,body});
  });
  return {steps:out,cv};
}
function timelineHtml(notes){
  const parsed=parseNotes(notes);
  let html='<div class="timeline"><h3>HR Updates</h3>';
  if(!parsed.steps.length) html+='<div class="empty">No HR notes yet.</div>';
  else html+=parsed.steps.map(s=>`<div class="step"><div class="step-title">${esc(s.title)}</div><div class="step-body">${esc(s.body)}</div></div>`).join('');
  if(parsed.cv) html+=`<a class="cv-link" href="${esc(parsed.cv)}" target="_blank">View submitted CV</a>`;
  html+='</div>'; return html;
}
function esignHtml(a){
  if(a.offer_published_at && Number(a.has_signature)){
    return `<div class="help staffbox" style="margin-top:18px"><strong>✔ Offer e-signed.</strong><br>Your signature is on file and will be used to verify your future staff requests.</div>`;
  }
  const canSign = a.offer_published_at && !Number(a.has_signature) && !['Rejected','Hired','Converted to Staff'].includes(a.status);
  if(!canSign) return '';
  return `<div class="help" style="margin-top:18px"><strong>📝 E-sign your offer (required before hiring)</strong><br>
   Offer: ${esc(a.offer_department||'—')} — ₱${esc(a.offer_salary||'0')} (${esc(a.offer_schedule_days||'—')} ${esc(a.offer_schedule_start||'')}–${esc(a.offer_schedule_end||'')})<br>
   <div style="position:relative;margin:10px 0"><canvas id="esignPad" width="900" height="260" style="pointer-events:none;width:100%;height:130px;background:#fff;border:1px solid var(--line);border-radius:10px;cursor:crosshair;touch-action:none;display:block;margin:10px 0"></canvas><div id="esignGate" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.65);border-radius:10px"><button type="button" class="logout" style="background:var(--accent);border-color:var(--accent);color:#fff" id="esignGateBtn">✍️ Confirm &amp; Begin E-Sign</button></div></div>
   <div style="margin:12px 0 4px;text-align:left;font-size:13px;font-weight:700">🔑 Set your staff password</div>
   <input id="esignPass" type="password" placeholder="Min 6 characters — your future HRMS login password" style="width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit;margin-bottom:8px">
   <input id="esignPass2" type="password" placeholder="Repeat password" style="width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit">
   <p class="muted" style="font-size:12px;margin:6px 0 10px">Once HR hires you, log in to HRMS with your email + this password.</p>
   <button class="logout" style="background:var(--accent);border-color:var(--accent);color:#fff" id="esignBtn">✍️ E-sign & Accept Offer</button>
   <button class="logout" id="esignClear" style="margin-left:8px">Clear</button>
   <p class="muted" id="esignMsg" style="margin-top:8px"></p></div>`;
}
function initEsign(){
  const pad=document.getElementById('esignPad'); if(!pad) return;
  const gate=document.getElementById('esignGate');
  if(gate){document.getElementById('esignGateBtn').onclick=async()=>{if(!await swalAsk('Confirm e-sign: you are about to enter your official e-signature. It will be recorded on this document and used for signature security. Continue?'))return;gate.remove();pad.style.pointerEvents='auto';};}

  const ctx=pad.getContext('2d'); ctx.lineWidth=3; ctx.lineCap='round'; ctx.lineJoin='round'; ctx.strokeStyle='#22303f';
  let drawing=false, strokes=0;
  const pos=e=>{const r=pad.getBoundingClientRect();return{x:(e.clientX-r.left)*(pad.width/r.width),y:(e.clientY-r.top)*(pad.height/r.height)}};
  pad.addEventListener('pointerdown',e=>{drawing=true;strokes++;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);pad.setPointerCapture(e.pointerId);e.preventDefault()});
  pad.addEventListener('pointermove',e=>{if(!drawing)return;const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault()});
  ['pointerup','pointercancel','pointerleave'].forEach(ev=>pad.addEventListener(ev,()=>{drawing=false}));
  document.getElementById('esignClear').onclick=()=>{ctx.clearRect(0,0,pad.width,pad.height);strokes=0};
  document.getElementById('esignBtn').onclick=async()=>{
    const msg=document.getElementById('esignMsg');
    if(strokes===0){msg.textContent='Please draw your signature first.';return}
    const p1=(document.getElementById('esignPass')||{value:''}).value||'', p2=(document.getElementById('esignPass2')||{value:''}).value||'';
    if(p1.length<6){swalAlert('Your staff password must be at least 6 characters.','error');return}
    if(p1!==p2){swalAlert('Passwords do not match.','error');return}
    try{
      const r=await fetch('api/applicant_status.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'e_sign',signature:pad.toDataURL('image/png'),password:p1})});
      const d=await r.json();
      msg.textContent=d.message||'';
      if(d.success) load();
    }catch(e){msg.textContent='Network error. Please try again.'}
  };
}
function slotLineHtml(a){
  if (a.position_slots_total === null || a.position_slots_total === undefined) return '';
  const total = Number(a.position_slots_total), filled = Number(a.position_slots_filled || 0);
  return `<p class="muted" style="font-size:12.5px;margin-top:2px">${esc(filled)} of ${esc(total)} slot${total===1?'':'s'} filled for this position</p>`;
}
function render(apps){
  if(!apps.length){results.innerHTML='<div class="card loading">No application found for this account.</div>';return}
  results.innerHTML=apps.map(a=>{let converted=a.status==='Converted to Staff';return `<div class="card"><h2>${esc(a.position)}</h2>${slotLineHtml(a)}<p><span class="badge ${cls(a.status)}">${esc(converted?'Staff account created':a.status)}</span></p><div class="info-grid"><div class="label">Name</div><div class="value">${esc(a.name)}</div><div class="label">Email</div><div class="value">${esc(a.email)}</div><div class="label">Applied on</div><div class="value">${esc(a.date)}</div></div>${esignHtml(a)}${timelineHtml(a.notes)}${converted?'<div class="help staffbox"><strong>You are now official staff.</strong><br>Your staff account has been created. Go back to login and use your email/username with the password given by HR, then change your password.</div>':''}</div>`}).join('');
  initEsign();
}
async function load(){try{const r=await fetch('api/applicant_status.php',{credentials:'same-origin'}),d=await r.json();if(!d.success){results.innerHTML=`<div class="card loading">${esc(d.message||'Could not load application.')}</div>`;return}render(d.data||[])}catch(e){results.innerHTML='<div class="card loading">Could not load application status.</div>'}}
logoutBtn.onclick=async()=>{await fetch('api/logout.php',{credentials:'same-origin'});location.href='login.php'};load();
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body></html>