<?php
session_start();
/* Already have a login account in this browser? Go straight to your
   dashboard instead of showing the sign-in form again. */
if (!empty($_SESSION['user'])) {
    $__r = $_SESSION['user']['role'] ?? 'admin';
    $__home = $__r === 'superadmin' ? 'indexx.php'
            : ($__r === 'staff'      ? 'indexxx.php'
            : ($__r === 'finance'    ? 'finance.php'
            : ($__r === 'applicant'  ? 'applicant-portal.php' : 'index.php')));
    header('Location: ' . $__home);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — HRMS Brew &amp; Co.</title>
<style>
  :root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--danger:#a8492f;--ok:#4f7a4a;--shadow:0 18px 50px rgba(59,35,19,.12)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Georgia,"Iowan Old Style","Segoe UI",serif;background:var(--cream);color:var(--ink);min-height:100vh;display:flex}
  svg.icon{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  :focus-visible{outline:3px solid rgba(169,113,74,.35);outline-offset:2px;border-radius:6px}

  .left-panel{
    flex:1;
    background: linear-gradient(160deg, rgba(58,36,27,.6) 0%, rgba(92,58,41,.45) 40%, rgba(122,79,56,.35) 70%, rgba(169,113,74,.4) 100%),
                url('../coffee-bg.jpg') center/cover no-repeat;
    display:flex;align-items:center;justify-content:center;
    padding:40px;color:#fff;
    text-align:center;position:relative;
  }
  .left-content{position:relative;z-index:1;max-width:420px}
  .left-logo{margin-bottom:16px}
  .left-logo img{width:100px;height:100px;filter:drop-shadow(0 4px 12px rgba(0,0,0,.3))}
  .left-brand{font-size:42px;font-weight:800;letter-spacing:.01em;color:#fff;margin-bottom:6px;text-shadow:0 2px 8px rgba(0,0,0,.2)}
  .left-tagline{font-size:13px;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:#d8a066;margin-bottom:24px}
  .left-divider{width:60px;height:2px;background:#d8a066;margin:0 auto 24px;opacity:.6}
  .left-quote{font-size:17px;font-style:italic;color:rgba(255,255,255,.85);line-height:1.6}

  .right-panel{
    width:480px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    padding:40px 36px;
    background:var(--cream);
  }
  .login-card{width:100%;max-width:400px}
  .login-header{margin-bottom:28px}
  .login-header h1{font-size:28px;font-weight:800;color:var(--ink);margin-bottom:6px}
  .login-header p{font-size:14px;color:var(--muted)}
  .alert{background:#fde8e8;color:var(--danger);border-radius:10px;padding:11px 14px;font-size:13px;font-weight:700;margin-bottom:16px;display:none}
  .alert.show{display:block}
  .form-group{margin-bottom:16px}
  .form-group label{display:block;font-size:13px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .form-group input{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:10px;font-size:15px;background:var(--card);color:var(--ink);outline:none;transition:border-color .2s,box-shadow .2s}
  .form-group input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.1)}
  .form-group.has-error input{border-color:var(--danger)}
  .field-error{color:var(--danger);font-size:12px;margin-top:5px;display:none}
  .form-group.has-error .field-error{display:block}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .remember-row{display:flex;align-items:center;margin-bottom:20px}
  .remember-label{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer}
  .remember-label input[type=checkbox]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}
  .btn-signin{width:100%;padding:14px;background:var(--accent);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:800;cursor:pointer;transition:background .2s}
  .btn-signin:hover{background:var(--accent-dark)}
  .btn-signin:disabled{opacity:.6;cursor:not-allowed}

  .applicant-divider{display:flex;align-items:center;gap:10px;margin:18px 0;color:var(--muted);font-size:12px}
  .applicant-divider:before,.applicant-divider:after{content:"";height:1px;background:var(--line);flex:1}
  .link-btn{width:100%;padding:11px 18px;border:1.5px solid var(--line);border-radius:9px;background:transparent;color:var(--accent);font:inherit;font-size:14px;font-weight:800;cursor:pointer;margin-top:10px;text-align:center;text-decoration:none;display:block;transition:all .2s}
  .link-btn:hover{background:rgba(169,113,74,.06);border-color:var(--accent)}

  .success{background:#e2ecdf;color:var(--ok);border-radius:10px;padding:11px 13px;font-size:13px;font-weight:700;margin-bottom:16px;display:none}
  .success.show{display:block}

  @media (max-width: 900px) {
    body{flex-direction:column}
    .left-panel{padding:32px 24px;min-height:180px}
    .left-brand{font-size:28px}
    .left-logo img{width:60px;height:60px}
    .left-quote{font-size:14px}
    .right-panel{width:100%;padding:28px 20px}
  }
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
<link rel="stylesheet" href="../hrms-responsive.css">
</head>
<body>

<div class="left-panel">
  <div class="left-content">
    <div class="left-logo">
      <img src="../brewco-logo.svg" alt="Brew &amp; Co.">
    </div>
    <div class="left-brand">Brew &amp; Co.</div>
    <div class="left-tagline">Coffee, Done Right</div>
    <div class="left-divider"></div>
    <div class="left-quote">"Every cup tells a story. Track every bean, bottle, and cup with precision."</div>
  </div>
</div>

<div class="right-panel">
  <div class="login-card">
    <div class="login-header">
      <h1>Welcome back</h1>
      <p>Sign in to your account</p>
    </div>

    <div class="alert" id="loginError"></div>
    <div class="success" id="loginSuccess"></div>

    <div id="loginView">
      <div class="form-group" id="field-username">
        <label for="username">Username or email</label>
        <input id="username" type="text" placeholder="Enter your username or email" autocomplete="username" />
        <div class="field-error">Please enter your username or email.</div>
      </div>
      <div class="form-group" id="field-password">
        <label for="password">Password</label>
        <div style="position:relative">
          <input id="password" type="password" placeholder="Enter your password" autocomplete="current-password" onkeydown="if(event.key==='Enter')login()" />
          <button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:var(--muted);padding:0" title="Show/hide">👁️</button>
        </div>
        <div class="field-error">Please enter your password.</div>
      </div>

      <div class="remember-row">
        <label class="remember-label">
          <input type="checkbox" id="rememberMe" />
          Remember me on this device
        </label>
      </div>

      <button class="btn-signin" id="loginBtn" onclick="login()">Sign in</button>

      <div class="applicant-divider">Applicant options</div>
      <a class="link-btn" href="apply.php">Browse open positions &amp; apply</a>
      <a class="link-btn" href="applicant-status.php">Check application / interview status</a>
    </div>
  </div>
</div>

<script>
const API_BASE='api';
const REMEMBER_KEY = 'hrms_remember_username';
const loginView=document.getElementById('loginView');

function togglePw(){const pw=document.getElementById('password');pw.type=pw.type==='password'?'text':'password'}
function showError(el,msg){el.textContent=msg;el.classList.add('show')}
function clearMsg(){document.getElementById('loginError').classList.remove('show');}
function clearFieldErrors(scope){ (scope||document).querySelectorAll('.form-group').forEach(f=>f.classList.remove('has-error')); }

// Restore remembered username on load.
(function(){
  const saved = localStorage.getItem(REMEMBER_KEY);
  if (saved) {
    document.getElementById('username').value = saved;
    document.getElementById('rememberMe').checked = true;
  }
})();

async function login(){
  clearMsg();
  clearFieldErrors();
  const username=document.getElementById('username').value.trim(), password=document.getElementById('password').value;
  let valid = true;
  if (!username) { document.getElementById('field-username').classList.add('has-error'); valid = false; }
  if (!password) { document.getElementById('field-password').classList.add('has-error'); valid = false; }
  if (!valid) { showError(document.getElementById('loginError'),'Please enter your username/email and password.'); return; }

  document.getElementById('loginBtn').disabled=true;
  try{
    const r=await fetch(`${API_BASE}/auth.php`,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({username,password})});
    const d=await r.json();
    if(!d.success){showError(document.getElementById('loginError'),d.message||'Invalid email or password.');return}

    if (document.getElementById('rememberMe').checked) {
      localStorage.setItem(REMEMBER_KEY, username);
    } else {
      localStorage.removeItem(REMEMBER_KEY);
    }

    location.href=d.redirect||'index.php';
  }catch(e){showError(document.getElementById('loginError'),'Cannot connect to backend.');}
  finally{document.getElementById('loginBtn').disabled=false}
}

</script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
<script src="../hrms-responsive.js" defer></script>
</body>
</html>