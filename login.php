<?php
require __DIR__ . '/db.php';

function role_home($role){
    switch ($role) {
        case 'clerk':      return 'inventory-clerk.php';
        case 'manager':    return 'inventory-manager.php';
        case 'finance':    return 'finance-dashboard.php';
        case 'superadmin': return 'user-management.php';
        case 'admin':      return 'pos/index.php';
        case 'cashier':
        case 'barista':
        case 'cleaner':    return 'pos/index.php';
        default:           return 'login.php';
    }
}

// Purge the LEGACY plaintext-password cookie from old installs, if present.
if (isset($_COOKIE['brewco_pass'])) setcookie('brewco_pass', '', time() - 3600, '/', '', false, true);
// Remember-me auto-login via hashed rotating token (no password in the cookie).
if (empty($_SESSION['user']) && !empty($_COOKIE['brewco_remember']) && db_ok()) {
    $rid = remember_consume($_COOKIE['brewco_remember']);
    if ($rid) {
        $ru = db_one('SELECT id, first_name, last_name, email, role FROM users WHERE id=? AND is_active=1 LIMIT 1', [$rid]);
        if ($ru) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>(int)$ru['id'], 'firstName'=>$ru['first_name'],
                                 'lastName'=>$ru['last_name'], 'email'=>$ru['email'],
                                 'role'=>$ru['role']];
            header('Location: ' . role_home($ru['role'])); exit;
        }
    }
}
if (!empty($_SESSION['user'])) {
    header('Location: ' . role_home($_SESSION['user']['role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Please enter both email and password.';
    }
    elseif (login_is_locked($email)) {
        $error = 'Too many failed attempts. Please try again in '.LOGIN_LOCKOUT_MINUTES.' minutes.';
    }
    elseif (db_ok()) {
        $u = db_one('SELECT id, first_name, last_name, email, password_hash, role
                     FROM users WHERE email=? AND is_active=1 LIMIT 1', [$email]);
        if ($u && password_verify($pass, $u['password_hash'])) {
            login_record($email, true);
            session_regenerate_id(true);
            session_regenerate_id(true); // new session id on login (defeats session fixation)
            $_SESSION['user'] = ['id'=>(int)$u['id'], 'firstName'=>$u['first_name'],
                                 'lastName'=>$u['last_name'], 'email'=>$u['email'],
                                 'role'=>$u['role']];
            // Remember-me = hashed rotating token; the password never leaves the server.
            remember_issue((int)$u['id'], !empty($_POST['remember']));
            if (!empty($_POST['remember'])) {
                setcookie('brewco_email', $u['email'], time()+60*60*24*30, '/', '', false, true);
            } else {
                setcookie('brewco_email', '', time() - 3600, '/', '', false, true);
            }
            audit($_SESSION['user'], 'login', 'User logged in as '.$u['role']);
            header('Location: ' . role_home($u['role'])); exit;
        } else {
            login_record($email, false);
            $error = 'Invalid email or password.';
        }
    }
    else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. HRMS — Sign in</title>
<style>
  :root {
    --brown-900:#faf6f0; --brown-800:#5c3a29; --brown-700:#7a5040;
    --brown-600:#7a4f38; --brown-500:#a9714a; --accent:#a9714a;
    --accent-dark:#8f5c39; --gold:#d8a066;
    --cream:#faf6f0; --cream-2:#f5ede3;
    --card:#fff; --ink:#3b2313; --muted:#7a6055; --line:#e8ddd0;
    --danger:#a23232; --ok:#256b4d;
    --shadow:0 18px 50px rgba(74,47,34,.18);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
    background:var(--cream);
    color:var(--ink);
    min-height:100vh;
    display:flex;
  }
  svg.icon{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  :focus-visible{outline:3px solid rgba(169,113,74,.45);outline-offset:2px;border-radius:6px}

  /* ---- LEFT PANEL (Branding) ---- */
  .left-panel{
    flex:0 0 60%;
    background:
      linear-gradient(160deg, rgba(58,36,27,.85) 0%, rgba(92,58,41,.7) 40%, rgba(122,79,56,.6) 70%, rgba(169,113,74,.75) 100%),
      url('coffee-bg.jpg') center/cover no-repeat;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:40px;
    position:relative;
    overflow:hidden;
  }
  .left-panel::before{
    content:'';
    position:absolute;
    inset:0;
    background:
      radial-gradient(circle at 20% 30%, rgba(216,160,102,.2) 0%, transparent 50%),
      radial-gradient(circle at 80% 70%, rgba(169,113,74,.15) 0%, transparent 50%);
    z-index:0;
  }
  /* Subtle vignette overlay for depth */
  .left-panel::after{
    content:'';
    position:absolute;
    inset:0;
    background:
      radial-gradient(ellipse at center, transparent 40%, rgba(0,0,0,.3) 100%);
    z-index:1;
    pointer-events:none;
  }
  .left-content{
    position:relative;
    z-index:2;
    text-align:center;
    color:#fff;
  }
  .left-logo{
    width:120px;
    height:120px;
    margin:0 auto 24px;
  }
  .left-logo img{
    width:100%;
    height:100%;
  }
  .left-brand{
    font-size:36px;
    font-weight:800;
    letter-spacing:0.02em;
    margin-bottom:8px;
    color:#fff;
  }
  .left-tagline{
    font-size:14px;
    font-weight:700;
    letter-spacing:0.25em;
    text-transform:uppercase;
    color:var(--gold);
    margin-bottom:32px;
  }
  .left-divider{
    width:60px;
    height:2px;
    background:var(--gold);
    margin:0 auto 24px;
    opacity:.6;
  }
  .left-quote{
    font-size:16px;
    font-style:italic;
    color:rgba(255,255,255,.7);
    line-height:1.6;
    max-width:300px;
    margin:0 auto;
  }
  .left-quote span{
    color:var(--gold);
    font-weight:700;
    font-style:normal;
  }

  /* ---- RIGHT PANEL (Form) ---- */
  .right-panel{
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:60px 24px;
    background:var(--cream);
  }
  .login-card{
    width:100%;
    max-width:360px;
  }
  .login-header{
    margin-bottom:32px;
  }
  .login-header h1{
    font-size:28px;
    font-weight:800;
    color:var(--ink);
    margin-bottom:6px;
  }
  .login-header p{
    font-size:15px;
    color:var(--muted);
  }

  /* ---- Error Alert ---- */
  .alert{
    background:#fde8e8;
    color:var(--danger);
    border-radius:10px;
    padding:12px 14px;
    font-size:14px;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:10px;
    border-left:4px solid var(--danger);
  }
  .alert svg{width:20px;height:20px;flex-shrink:0}

  /* ---- Form ---- */
  .form-group{
    margin-bottom:20px;
  }
  .form-group label{
    display:block;
    font-size:14px;
    font-weight:600;
    color:var(--ink);
    margin-bottom:8px;
  }
  .input-wrap{
    display:flex;
    align-items:center;
    gap:12px;
    border:1.5px solid var(--line);
    border-radius:10px;
    padding:12px 14px;
    background:#fff;
    transition:border-color .2s, box-shadow .2s;
  }
  .input-wrap:focus-within{
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(169,113,74,.12);
  }
  /* Input icons are stroke-only. Without an explicit fill, browser-default
     SVG filling turns the email envelope into the solid black box seen here. */
  .input-wrap > svg,
  .input-wrap .eye-btn svg{
    width:20px;
    height:20px;
    color:var(--muted);
    fill:none;
    stroke:currentColor;
    flex-shrink:0;
  }
  .input-wrap input{
    border:none;
    outline:none;
    width:100%;
    min-width:0;
    font-size:15px;
    color:var(--ink);
    background:transparent;
    font-family:inherit;
  }
  /* responsive-ui.css provides a general focus outline. The login input uses
     its wrapper as the focus target, so suppress the duplicate inner outline. */
  .input-wrap input:focus,
  .input-wrap input:focus-visible{
    outline:none !important;
    box-shadow:none !important;
  }
  .input-wrap input::placeholder{
    color:#b0a090;
  }
  /* Keep Chrome's saved-email autofill clean and consistent with the card. */
  .input-wrap input:-webkit-autofill,
  .input-wrap input:-webkit-autofill:hover,
  .input-wrap input:-webkit-autofill:focus{
    -webkit-text-fill-color:var(--ink);
    -webkit-box-shadow:0 0 0 1000px #fff inset;
    box-shadow:0 0 0 1000px #fff inset;
    caret-color:var(--ink);
  }
  .eye-btn{
    background:none;
    border:none;
    cursor:pointer;
    padding:0;
    color:var(--muted);
    display:flex;
    align-items:center;
    flex-shrink:0;
  }
  .eye-btn:hover{color:var(--accent)}
  .eye-btn svg{width:20px;height:20px}

  /* ---- Remember Me ---- */
  .remember-row{
    display:flex;
    align-items:center;
    justify-content:flex-start;
    margin-bottom:24px;
  }
  .remember-label{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
    color:var(--muted);
    cursor:pointer;
  }
  .remember-label input[type="checkbox"]{
    width:16px;
    height:16px;
    accent-color:var(--accent);
    cursor:pointer;
  }
  .remember-label .saved-badge{
    font-size:11px;
    color:var(--ok);
    font-weight:600;
    margin-left:4px;
  }

  .btn-signin{
    width:100%;
    background:var(--accent);
    color:#fff;
    border:none;
    border-radius:10px;
    padding:14px;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    transition:background .2s, transform .1s;
    font-family:inherit;
  }
  .btn-signin:hover{background:var(--accent-dark)}
  .btn-signin:active{transform:scale(.99)}


  @media(max-width:860px){
    body{flex-direction:column}
    .left-panel{
      flex:0 0 auto;
      min-height:260px;
      padding:24px;
    }
    .left-logo{width:56px;height:56px;margin-bottom:10px}
    .left-brand{font-size:22px}
    .left-tagline{font-size:11px;margin-bottom:0}
    .left-divider{display:none}
    .left-quote{display:none}
    .right-panel{padding:24px}
    .login-header h1{font-size:24px}
    .login-card{max-width:100%}
  }
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="left-content">
    <div class="left-logo">
      <img src="brewco-logo.svg" alt="Brew & Co.">
    </div>
    <div class="left-brand">Brew &amp; Co.</div>
    <div class="left-tagline">Coffee, Done Right</div>
    <div class="left-divider"></div>
    <div class="left-quote">
      <span>"</span>Every cup tells a story. Track every bean, bottle, and cup with precision.<span>"</span>
    </div>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="login-card">
    <div class="login-header">
      <h1>Welcome back</h1>
      <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
      <div class="form-group">
        <label for="email">Email</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
          <input id="email" type="email" name="email" placeholder="Enter your email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? ($_COOKIE['brewco_email'] ?? '')) ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" id="togglePw" class="eye-btn" aria-label="Show or hide password" title="Show/hide password">
            <svg id="eyeOpen" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eyeOff" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>
      </div>

      <div class="remember-row" style="justify-content: flex-start;">
        <label class="remember-label">
          <input type="checkbox" name="remember" value="1"<?= !empty($_COOKIE['brewco_email']) ? ' checked' : '' ?>>
          Remember me on this device
          <?php if (!empty($_COOKIE['brewco_email'])): ?><span class="saved-badge">(saved)</span><?php endif; ?>
        </label>
      </div>

      <button class="btn-signin" type="submit">Sign in</button>
    </form>
  </div>
</div>

<script>
  document.getElementById('togglePw').addEventListener('click', function(){
    const pw = document.getElementById('password');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    document.getElementById('eyeOpen').style.display = show ? 'none' : '';
    document.getElementById('eyeOff').style.display = show ? '' : 'none';
  });
</script>
<script src="input-guard.js"></script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
</body>
</html>