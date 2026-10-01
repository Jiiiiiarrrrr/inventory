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
<title>Profile</title>
<style>
:root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--radius:14px;--shadow:0 2px 12px rgba(59,35,19,.05)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
h1{font-size:24px;font-weight:800;margin-bottom:20px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:800px}
@media(max-width:600px){.grid{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow)}
.card h2{font-size:17px;font-weight:800;margin-bottom:16px;color:var(--accent)}
.avatar{width:80px;height:80px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;margin:0 auto 16px}
.info-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--line)}
.info-row:last-child{border-bottom:0}
.info-label{color:var(--muted);font-size:13px}
.info-value{font-weight:700;font-size:14px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:var(--muted)}
.form-group input{width:100%;padding:10px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:14px;background:var(--cream);color:var(--ink);outline:none;font-family:inherit}
.form-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed}
.btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}.btn-secondary:hover{background:var(--cream)}
.regsig-preview{border:1.5px dashed var(--line);border-radius:10px;background:var(--cream);min-height:90px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;padding:8px}
.regsig-preview img{max-height:110px;max-width:100%;object-fit:contain}
#regSigPad{width:100%;height:150px;border:1.5px solid var(--line);border-radius:10px;background:#fff;cursor:crosshair;touch-action:none;display:block;margin-top:12px}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.alert-success{background:#e2ecdf;color:var(--ok)}
.alert-error{background:#fce4dc;color:var(--danger)}
</style>

<link rel="stylesheet" href="../../responsive-ui.css">
<link rel="stylesheet" href="../../dashboard-navigation.css">
<link rel="stylesheet" href="../../hrms-responsive.css">
</head>
<body>
<div class="eyebrow">Account</div>
<h1>My Profile</h1>

<div class="grid">
  <div class="card">
    <div class="avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?></div>
    <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($user['name'] ?? '—') ?></span></div>
    <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($user['email'] ?? '—') ?></span></div>
    <div class="info-row"><span class="info-label">Role</span><span class="info-value"><?= htmlspecialchars(ucfirst($user['role'] ?? 'Staff')) ?></span></div>
  </div>

  <div class="card">
    <h2>Change Password</h2>
    <div id="alertBox"></div>
    <form id="passwordForm">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" id="currentPassword" required>
      </div>
      <div class="form-group">
        <label>New Password</label>
        <input type="password" id="newPassword" required minlength="6">
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" id="confirmPassword" required minlength="6">
      </div>
      <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
  </div>
</div>

<div style="max-width:800px;margin-top:16px">
  <div class="card">
    <h2>Registered Signature</h2>
    <p class="info-label" style="margin-bottom:12px">This signature verifies every request you sign. It can be updated <b>once a month</b>.</p>
    <div id="regSigAlert"></div>
    <div class="regsig-preview" id="regSigPreview">Loading…</div>
    <p class="info-label" id="regSigLock" style="margin:10px 0"></p>
    <div style="position:relative;margin-top:12px"><canvas id="regSigPad" width="1000" height="300" style="pointer-events:none"></canvas><div id="regSigGate" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.65);border-radius:10px"><button type="button" class="btn btn-primary" id="regSigGateBtn">✍️ Confirm &amp; Begin E-Sign</button></div></div>
    <div style="margin-top:12px;display:flex;gap:10px">
      <button class="btn btn-primary" id="regSigUpdate">Update Signature</button>
      <button class="btn btn-secondary" id="regSigClear">Clear</button>
    </div>
  </div>
</div>

<script src="../../alerts.js"></script>
<script>
document.getElementById('passwordForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const current = document.getElementById('currentPassword').value;
  const newPass = document.getElementById('newPassword').value;
  const confirm = document.getElementById('confirmPassword').value;
  if (newPass !== confirm) { showAlert('Passwords do not match.', 'error'); return; }
  try {
    const r = await fetch('../api/profile.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'change_password',current_password:current,new_password:newPass})});
    const d = await r.json();
    if (d.success) { showAlert('Password updated successfully.', 'success'); this.reset(); }
    else { showAlert(d.message || 'Failed to update password.', 'error'); }
  } catch(e) { showAlert('Could not update password.', 'error'); }
});
function showAlert(msg, type){
  const box = document.getElementById('alertBox');
  box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
  setTimeout(() => box.innerHTML = '', 3000);
}

/* ---------- Registered signature (once-a-month update) ---------- */
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
let regPadStrokes = 0;
(async function initRegSig(){
  const preview = document.getElementById('regSigPreview');
  const lockEl  = document.getElementById('regSigLock');
  const btn     = document.getElementById('regSigUpdate');
  try {
    const r = await fetch('../api/my_signature.php', {credentials:'same-origin'});
    const d = await r.json();
    if (d.has && d.path) {
      preview.innerHTML = '';
      const img = new Image();
      img.onerror = () => { preview.textContent = 'Registered signature file missing on server — update it below or sign a request to re-record it.'; };
      img.src = '../' + d.path;
      preview.appendChild(img);
    } else {
      preview.textContent = 'No registered signature yet — your first signed request records it, or draw one below.';
    }
    if (d.can_update === false) {
      btn.disabled = true;
      lockEl.textContent = '🔒 Locked — you can update again in ' + d.days_left + ' day(s) (last update ' + String(d.updated_at).split(' ')[0] + ').';
    } else if (d.updated_at) {
      lockEl.textContent = 'Last updated ' + String(d.updated_at).split(' ')[0] + '.';
    }
  } catch(e) { preview.textContent = 'Could not load your registered signature.'; }
})();
const regPad = document.getElementById('regSigPad');
const regCtx = regPad.getContext('2d');
const regGate=document.getElementById('regSigGate');
if(regGate){document.getElementById('regSigGateBtn').onclick=async()=>{if(!await swalAsk('Confirm e-sign: you are about to enter your new registered signature. It will verify your future request signatures. Continue?'))return;regGate.remove();regPad.style.pointerEvents='auto';};}
regCtx.lineWidth = 3; regCtx.lineCap = 'round'; regCtx.lineJoin = 'round'; regCtx.strokeStyle = '#22303f';
let regDrawing = false;
function regPos(e){ const r = regPad.getBoundingClientRect(); return { x:(e.clientX-r.left)*(regPad.width/r.width), y:(e.clientY-r.top)*(regPad.height/r.height) }; }
regPad.addEventListener('pointerdown', e => { regDrawing = true; regPadStrokes++; const p = regPos(e); regCtx.beginPath(); regCtx.moveTo(p.x, p.y); regPad.setPointerCapture(e.pointerId); e.preventDefault(); });
regPad.addEventListener('pointermove', e => { if (!regDrawing) return; const p = regPos(e); regCtx.lineTo(p.x, p.y); regCtx.stroke(); e.preventDefault(); });
['pointerup','pointercancel','pointerleave'].forEach(ev => regPad.addEventListener(ev, () => { regDrawing = false; }));
document.getElementById('regSigClear').addEventListener('click', () => { regCtx.clearRect(0, 0, regPad.width, regPad.height); regPadStrokes = 0; });
document.getElementById('regSigUpdate').addEventListener('click', async function(){
  const box = document.getElementById('regSigAlert');
  if (regPadStrokes === 0) { box.innerHTML = '<div class="alert alert-error">Please draw your new signature first.</div>'; return; }
  this.disabled = true;
  try {
    const r = await fetch('../api/my_signature.php', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'update', signature:regPad.toDataURL('image/png')})});
    const d = await r.json();
    if (d.success) { box.innerHTML = '<div class="alert alert-success">' + esc(d.message) + '</div>'; setTimeout(() => location.reload(), 1200); }
    else { box.innerHTML = '<div class="alert alert-error">' + esc(d.message || 'Failed to update.') + '</div>'; this.disabled = false; }
  } catch(e) { box.innerHTML = '<div class="alert alert-error">Could not update signature.</div>'; this.disabled = false; }
});
</script>

<script src="../../responsive-ui.js" defer></script>
<script src="../../dashboard-navigation.js" defer></script>
<script src="../../hrms-responsive.js" defer></script>
</body>
</html>
