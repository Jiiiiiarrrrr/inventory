<?php session_start();
$isApplicant = isset($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'applicant');
$applicantEmail = $isApplicant ? strtolower((string)($_SESSION['user']['email'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Applicant Portal</title>
<style>
:root{--espresso:#3b2313;--cream:#faf6f0;--line:#e8ddd0;--accent:#a9714a;--muted:#7a6055;--surface:#fff;--success:#4f7a4a;--success-bg:#e2ecdf;--danger:#a8492f;--danger-bg:#f6e3dc;--blue:#2f6690;--blue-bg:#ddeaf5;--violet:#6d4a90;--violet-bg:#eee6f8;--warn-bg:#fff8ea}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--cream);color:var(--espresso);font-family:Georgia,"Iowan Old Style",serif}.top{background:var(--espresso);color:var(--cream);padding:14px 24px;display:flex;justify-content:space-between;align-items:center}.top b{font-size:17px}.logout{border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:var(--cream);border-radius:999px;padding:8px 14px;font:inherit;font-weight:900;cursor:pointer}.wrap{max-width:920px;margin:auto;padding:28px 24px}.card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:24px;margin:14px 0;box-shadow:0 2px 12px rgba(59,35,19,.08)}h1{margin:0 0 6px}.muted{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:900}.Pending{background:#ecd9b5;color:var(--accent)}.Accepted{background:var(--success-bg);color:var(--success)}.Initial{background:var(--warn-bg);color:var(--accent)}.Actual{background:var(--blue-bg);color:var(--blue)}.Final{background:var(--violet-bg);color:var(--violet)}.Hired,.Converted{background:var(--success-bg);color:var(--success)}.Rejected{background:var(--danger-bg);color:var(--danger)}.info-grid{display:grid;grid-template-columns:160px 1fr;gap:0;border-top:1px solid var(--line);margin-top:18px}.label,.value{padding:11px 0;border-bottom:1px solid var(--line)}.label{font-weight:900;color:var(--muted)}.value{font-weight:900;text-align:right;overflow-wrap:anywhere}.help{background:var(--warn-bg);border:1px solid var(--line);border-radius:10px;padding:14px;margin-top:18px;color:var(--accent);line-height:1.4}.staffbox{background:var(--success-bg);color:var(--success);border-color:#b9d4b4}.loading{text-align:center;color:var(--muted);padding:40px 20px}
.stage-track{display:flex;gap:6px;margin-top:14px;flex-wrap:wrap}
.stage-step{flex:1;min-width:90px;text-align:center;padding:8px 6px;border-radius:8px;background:#f0f0f0;color:#999;font-size:11.5px;font-weight:800}
.stage-step.done{background:var(--success-bg);color:var(--success)}
.stage-step.current{background:var(--accent);color:#fff}
.offer-card{border:1.5px solid #e0c9a6;background:linear-gradient(160deg,#fff 0%,#faf3e8 100%)}
.offer-card h3{margin:0 0 4px;color:var(--accent)}
.offer-note{margin-top:12px;padding:12px;background:#fff;border:1px solid #e0c9a6;border-radius:8px;font-size:14px;line-height:1.5}
.contract-link{display:inline-block;margin-top:12px;color:var(--accent);font-weight:900}
@media(max-width:650px){.info-grid{grid-template-columns:1fr}.value{text-align:left}.label{border-bottom:0;padding-bottom:2px}.value{padding-top:0}}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>
<div class="top"><b>Applicant Portal</b><a class="logout" href="login.php" style="text-decoration:none">Back to Login</a></div>
<div class="wrap"><div class="card"><h1>Check Application Status</h1><p class="muted">Enter the email you used when applying. This page is read-only.</p><div style="display:flex;gap:10px;margin-top:16px"><input id="email" type="email" placeholder="Enter your email" style="flex:1;padding:12px;border:1px solid var(--line);border-radius:8px;font:inherit"><button id="checkBtn" class="logout" style="background:var(--accent);border-color:var(--accent);border-radius:8px">Check</button></div></div><div id="results"></div></div>
<script src="../alerts.js"></script>
<script>
const STAGES = ['Pending','Accepted','Initial Interview','Actual Interview','Final Interview'];
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}

// Badge shown at the top: prefer the live interview stage while the
// applicant is still in the pipeline (status === 'Pending'); once HR
// makes a final call, status flips to 'Hired' / 'Rejected' and that
// takes over.
function badgeInfo(a){
  const status = a.status || 'Pending';
  if (status === 'Hired' || status === 'Converted to Staff') return { label: status === 'Hired' ? 'Hired' : 'Staff account created', cls: 'Hired' };
  if (status === 'Rejected') return { label: 'Rejected', cls: 'Rejected' };
  const stage = a.stage || 'Pending';
  const clsMap = { 'Pending':'Pending', 'Accepted':'Accepted', 'Initial Interview':'Initial', 'Actual Interview':'Actual', 'Final Interview':'Final' };
  return { label: stage, cls: clsMap[stage] || 'Pending' };
}

function stageTrackHtml(a){
  const status = a.status || 'Pending';
  if (status === 'Hired' || status === 'Rejected' || status === 'Converted to Staff') return '';
  const stage = a.stage || 'Pending';
  const idx = STAGES.indexOf(stage);
  return '<div class="stage-track">' + STAGES.map((s,i)=>{
    const cls = i < idx ? 'done' : (i === idx ? 'current' : '');
    return `<div class="stage-step ${cls}">${esc(s)}</div>`;
  }).join('') + '</div>';
}

function money(n){ return '₱'+Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2}); }
function offerHtml(a){
  if(!a.offer_published_at) return '';
  return `<div class="card offer-card">
    <h3>🎉 You passed the Final Interview!</h3>
    <p class="muted" style="font-size:13px;margin-bottom:14px">Here are your offer details. HR will reach out to finalize your start date and paperwork.</p>
    <div class="info-grid">
      <div class="label">Department</div><div class="value">${esc(a.offer_department||'—')}</div>
      <div class="label">Monthly Salary</div><div class="value">${a.offer_salary?money(a.offer_salary):'—'}</div>
      <div class="label">Work Days</div><div class="value">${esc(a.offer_schedule_days||'—')}</div>
      <div class="label">Shift Hours</div><div class="value">${a.offer_schedule_start&&a.offer_schedule_end?esc(a.offer_schedule_start)+' – '+esc(a.offer_schedule_end):'—'}</div>
    </div>
    ${a.offer_notes?`<p class="offer-note">${esc(a.offer_notes)}</p>`:''}
    ${a.contract_path?`<a class="contract-link" href="${esc(a.contract_path)}" target="_blank" rel="noopener">📄 View / download your contract</a>`:''}
  </div>`;
}

const APPLICANT_SESSION = <?= json_encode(['is' => $isApplicant, 'email' => $applicantEmail]) ?>;
function esignHtml(a){
  if(a.offer_published_at && Number(a.has_signature)){
    return `<div class="help staffbox" style="margin-top:18px"><strong>✔ Offer e-signed.</strong><br>The applicant's signature is on file and will be used to verify their future staff requests.</div>`;
  }
  const canSign = a.offer_published_at && !Number(a.has_signature) && !['Rejected','Hired','Converted to Staff'].includes(a.status);
  if(!canSign) return '';
  if(!APPLICANT_SESSION.is || APPLICANT_SESSION.email !== String(a.email||'').toLowerCase()){
    return `<div class="help" style="margin-top:18px"><strong>📝 E-sign required before hiring.</strong><br>This offer is waiting for the applicant's e-signature. The applicant must <a href="login.php" style="color:var(--accent);font-weight:900">log in to the applicant portal</a> with this email and sign there.</div>`;
  }
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
function render(apps){
  if(!apps.length){results.innerHTML='<div class="card loading">No application found for this account.</div>';return}
  results.innerHTML=apps.map(a=>{
    const converted = a.status==='Converted to Staff' || a.status==='Hired';
    const badge = badgeInfo(a);
    return `<div class="card"><h2>${esc(a.position)}</h2><p><span class="badge ${badge.cls}">${esc(badge.label)}</span></p>${stageTrackHtml(a)}<div class="info-grid"><div class="label">Name</div><div class="value">${esc(a.name)}</div><div class="label">Email</div><div class="value">${esc(a.email)}</div><div class="label">Applied on</div><div class="value">${esc(a.date)}</div></div>${esignHtml(a)}${converted?'<div class="help staffbox"><strong>You are now official staff.</strong><br>Your staff account has been created. Go back to login and use your email/username with the password given by HR, then change your password.</div>':''}</div>${offerHtml(a)}`
  }).join('')
  initEsign();
}
async function load(){try{if(!email.value.trim()){results.innerHTML='';return}const r=await fetch('api/applicant_status.php?email='+encodeURIComponent(email.value.trim())),d=await r.json();if(!d.success){results.innerHTML=`<div class="card loading">${esc(d.message||'Could not load application.')}</div>`;return}render(d.data||[])}catch(e){results.innerHTML='<div class="card loading">Could not load application status.</div>'}}
checkBtn.onclick=load;email.addEventListener('keydown',e=>{if(e.key==='Enter')load()});
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body></html>