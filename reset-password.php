<?php
require __DIR__ . '/db.php';

$msg = ''; $bad = false; $token = trim($_GET['token'] ?? '');
$valid = false;
if ($token) {
    $r = db_one('SELECT pr.id, pr.user_id FROM password_resets pr
                 WHERE pr.token=? AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1', [$token]);
    if ($r) $valid = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token && db_ok()) {
    if (!verify_token($_POST['token'] ?? '')) { $bad = true; $msg = 'Invalid form token.'; }
    else {
        $r = db_one('SELECT pr.id, pr.user_id FROM password_resets pr
                     WHERE pr.token=? AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1', [$token]);
        $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
        if (!$r) { $bad = true; $msg = 'This reset link is invalid or has expired.'; }
        elseif (strlen($pw) < 4) { $bad = true; $msg = 'Password must be at least 4 characters.'; }
        elseif ($pw !== $pw2) { $bad = true; $msg = 'Passwords do not match.'; }
        else {
            db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $r['user_id']]);
            db_exec('UPDATE password_resets SET used=1 WHERE id=?', [$r['id']]);
            audit(['id'=>$r['user_id'],'role'=>'user'], 'user.reset_pw', 'Password reset via forgot-password link');
            $msg = 'Your password has been reset. You can now sign in.';
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Reset Password</title>
<style>
  :root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--danger:#a23232;--ok:#256b4d;--shadow:0 18px 50px rgba(74,47,34,.18)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#efe3d3,#e3d0b9);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:var(--card);width:100%;max-width:410px;border-radius:20px;box-shadow:var(--shadow);padding:40px 34px}
  .brand{text-align:center;margin-bottom:24px}.brand .logo{font-size:40px;font-weight:800;color:var(--accent)}
  .brand h1{font-size:20px;margin-top:6px}.brand p{color:var(--muted);font-size:13px;margin-top:4px}
  label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
  input{width:100%;border:1px solid var(--line);border-radius:10px;padding:12px 13px;font-size:14px;color:var(--ink);outline:none}
  input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(169,113,74,.15)}
  .btn{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;margin-top:10px}
  .btn:hover{background:var(--accent-dark)}
  .msg{background:#e2f0e8;color:var(--ok);border-radius:10px;padding:11px 13px;font-size:13px;margin-bottom:16px}
  .msg.bad{background:#f6e0e0;color:var(--danger)}
  .back{margin-top:18px;font-size:13px;text-align:center}.back a{color:var(--accent);font-weight:600}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
</head>
<body>
  <div class="card">
    <div class="brand"><div class="logo">☕</div><h1>Reset password</h1><p>Set a new password for your account.</p></div>
    <?php if (!empty($done)): ?>
      <div class="msg"><?= htmlspecialchars($msg) ?></div>
      <div class="back"><a href="login.php">← Go to sign in</a></div>
    <?php elseif ($valid): ?>
      <?php if ($msg): ?><div class="msg <?= $bad?'bad':'' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <form method="post" action="reset-password.php?token=<?= htmlspecialchars($token) ?>" novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars(form_token()) ?>">
        <label>New password</label>
        <input type="password" name="password" required minlength="4" placeholder="min 4 characters" style="margin-bottom:12px">
        <label>Confirm password</label>
        <input type="password" name="password2" required placeholder="retype password">
        <button class="btn" type="submit">Reset password</button>
      </form>
      <div class="back"><a href="login.php">← Back to sign in</a></div>
    <?php else: ?>
      <div class="msg bad">This reset link is invalid or has expired. Please request a new one.</div>
      <div class="back"><a href="forgot-password.php">← Request a new link</a></div>
    <?php endif; ?>
  </div>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
</body>
</html>