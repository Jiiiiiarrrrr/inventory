<?php
require __DIR__ . '/db.php';

$msg = ''; $bad = false; $link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && db_ok()) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $u = db_one('SELECT id, first_name, email FROM users WHERE email=? AND is_active=1 LIMIT 1', [$email]);
        if ($u) {
            $token = bin2hex(random_bytes(32));
            db_exec('INSERT INTO password_resets (user_id, token, used, expires_at) VALUES (?,?,0, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
                    [$u['id'], $token]);
            $link = SITE_URL . 'reset-password.php?token=' . $token;
            $name = $u['first_name'];
            $subject = 'Reset your Brew & Co. password';
            $body = '<p>Hi '.htmlspecialchars($name).',</p>'
                  . '<p>We received a request to reset your password. Click the link below to set a new one (valid for 1 hour):</p>'
                  . '<p><a href="'.htmlspecialchars($link).'">Reset my password</a></p>'
                  . '<p>If you didn\'t request this, you can ignore this email.</p>'
                  . '<p>— Brew &amp; Co.</p>';
            $sent = send_mail($email, $subject, $body);
            if ($sent) {
                $msg = 'A reset link has been sent to your email. Check your inbox.';
            } else {
                $bad = true;
                $msg = 'Could not send the email (no mail/SMTP configured). Here is your reset link (demo):';
            }
            $showLink = $link;
        } else {
            $bad = true; $msg = 'No active account found with that email.';
        }
    } else {
        $bad = true; $msg = 'Please enter a valid email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico?v=2">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brew &amp; Co. — Forgot Password</title>
<style>
  :root{--accent:#a9714a;--accent-dark:#8f5c39;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--danger:#a23232;--shadow:0 18px 50px rgba(74,47,34,.18)}
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
  .msg{background:#e2f0e8;color:var(--ok, #256b4d);border-radius:10px;padding:11px 13px;font-size:13px;margin-bottom:16px}
  .msg.bad{background:#f6e0e0;color:var(--danger)}
  .linkbox{background:#f6ecdd;border:1px solid var(--line);border-radius:10px;padding:12px;font-size:13px;word-break:break-all;margin-top:10px}
  .back{margin-top:18px;font-size:13px;text-align:center}
  .back a{color:var(--accent);font-weight:600}
</style>

<link rel="stylesheet" href="responsive-ui.css">
<link rel="stylesheet" href="dashboard-navigation.css">
</head>
<body>
  <form class="card" method="post" action="forgot-password.php" novalidate>
    <div class="brand"><div class="logo">☕</div><h1>Forgot password</h1>
      <p>Enter your account email to get a reset link.</p></div>
    <?php if ($msg): ?><div class="msg <?= $bad?'bad':'' ?>"><?= htmlspecialchars($msg) ?><?php if(!empty($showLink)): ?><div class="linkbox"><b>Reset link (demo):</b><br><a href="<?= htmlspecialchars($showLink) ?>"><?= htmlspecialchars($showLink) ?></a></div><?php endif; ?></div><?php endif; ?>
    <label>Email</label>
    <input type="email" name="email" required placeholder="you@brewco.ph">
    <button class="btn" type="submit">Send reset link</button>
    <div class="back"><a href="login.php">← Back to sign in</a></div>
  </form>
<script src="input-guard.js"></script>
<script src="datatable.js"></script>
<script src="alerts.js"></script>
<script src="logout-confirm.js"></script>
<script src="ux-improvements.js"></script>

<script src="responsive-ui.js" defer></script>
<script src="dashboard-navigation.js" defer></script>
</body>
</html>