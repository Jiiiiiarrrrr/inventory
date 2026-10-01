<?php session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Change Password</title>
<style>
:root{--espresso:#3b2313;--cream:#faf6f0;--line:#e8ddd0;--accent:#a9714a;--muted:#7a6055;--danger:#a8492f;--danger-bg:#f6e3dc;--success:#4f7a4a;--success-bg:#e2ecdf}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--cream);font-family:Georgia,"Iowan Old Style",serif;color:var(--espresso);padding:24px}.card{width:100%;max-width:460px;background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px;box-shadow:0 12px 38px rgba(59,35,19,.14)}h1{margin:0 0 8px;font-size:30px}.muted{color:var(--muted);margin:0 0 18px;line-height:1.45}label{display:block;font-weight:900;margin:14px 0 6px}.input-wrap{display:flex;gap:8px}.input-wrap input{flex:1}input{width:100%;padding:11px;border:1px solid var(--line);border-radius:8px;background:var(--cream);font:inherit}.mini{width:auto;border:1px solid var(--line);background:#fff;color:var(--accent);border-radius:8px;padding:0 11px;font:inherit;font-size:12px;font-weight:900;cursor:pointer}.btn{width:100%;padding:12px;margin-top:16px;border:0;border-radius:8px;background:var(--accent);color:#fff;font:inherit;font-weight:900;cursor:pointer}.btn:disabled{opacity:.65;cursor:wait}.msg{display:none;margin-top:12px;padding:10px;border-radius:8px;font-weight:900;line-height:1.4}.err{display:block;background:var(--danger-bg);color:var(--danger)}.ok{display:block;background:var(--success-bg);color:var(--success)}.hint{font-size:12px;color:var(--muted);margin-top:6px}.match{font-size:12px;margin-top:6px;font-weight:900}.match.good{color:var(--success)}.match.bad{color:var(--danger)}</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>
<div class="card">
  <h1>Create new password</h1>
  <p class="muted">Set your new password before using the system.</p>

  <label for="newp">New password</label>
  <div class="input-wrap"><input id="newp" type="password" placeholder="Enter new password"><button class="mini" type="button" data-toggle="newp">Show</button></div>
  <div class="hint">At least 6 characters. Spaces at the start/end are ignored.</div>

  <label for="confirmPass">Confirm new password</label>
  <div class="input-wrap"><input id="confirmPass" type="password" placeholder="Confirm new password"><button class="mini" type="button" data-toggle="confirmPass">Show</button></div>
  <div id="matchText" class="match"></div>

  <button class="btn" id="btn">Update password</button>
  <div class="msg" id="msg"></div>
</div>
<script>
const newp = document.getElementById('newp');
const confirmPass = document.getElementById('confirmPass');
const msg = document.getElementById('msg');
const matchText = document.getElementById('matchText');
const btn = document.getElementById('btn');
function clean(v){ return String(v || '').trim(); }
function setMsg(text, ok=false){ msg.textContent=text; msg.className='msg ' + (ok ? 'ok' : 'err'); }
function checkMatch(){
  const a=clean(newp.value), b=clean(confirmPass.value);
  if(!a && !b){ matchText.textContent=''; matchText.className='match'; return; }
  if(a===b){ matchText.textContent='Passwords match.'; matchText.className='match good'; }
  else { matchText.textContent='Passwords do not match.'; matchText.className='match bad'; }
}
newp.addEventListener('input', checkMatch);
confirmPass.addEventListener('input', checkMatch);
document.querySelectorAll('[data-toggle]').forEach(t=>{
  t.onclick=()=>{
    const input=document.getElementById(t.dataset.toggle);
    input.type=input.type==='password'?'text':'password';
    t.textContent=input.type==='password'?'Show':'Hide';
  };
});
async function getMe(){
  const r=await fetch('api/me.php',{credentials:'same-origin'});
  const d=await r.json();
  if(!d.success) location.href='login.php';
  return d.user;
}
btn.onclick=async()=>{
  msg.className='msg'; msg.textContent='';
  const newPassword=clean(newp.value);
  const confirmPassword=clean(confirmPass.value);
  if(!newPassword || !confirmPassword){ setMsg('Please complete both password fields.'); return; }
  if(newPassword.length<6){ setMsg('New password must be at least 6 characters.'); return; }
  if(newPassword!==confirmPassword){ setMsg('New passwords do not match.'); return; }
  btn.disabled=true;
  try{
    const r=await fetch('api/change_password.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({new_password:newPassword})});
    const d=await r.json();
    if(!d.success){ setMsg(d.message || 'Failed to change password.'); return; }
    setMsg('Password changed. Redirecting...', true);
    const u=await getMe();
    setTimeout(()=>{ location.href = u.role==='superadmin' ? 'indexx.php' : (u.role==='staff' ? 'indexxx.php' : 'index.php'); },700);
  }catch(e){ setMsg('Backend error. Make sure Apache and MySQL are running.'); }
  finally{ btn.disabled=false; }
};
getMe();
</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body>
</html>
