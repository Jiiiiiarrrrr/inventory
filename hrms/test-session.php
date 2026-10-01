<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2"><title>Session Test</title>
<style>
body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0}
h2{color:#fff}
pre{background:#000;padding:10px;border:1px solid #333}
.ok{color:#0f0}.err{color:#f00}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
</head>
<body>
<h1>Session Diagnostic</h1>

<h2>1. Is session started?</h2>
<?php echo session_status() === PHP_SESSION_ACTIVE ? '<div class="ok">✓ Yes</div>' : '<div class="err">✗ No</div>'; ?>

<h2>2. Session ID:</h2>
<div class="ok"><?= session_id() ?></div>

<h2>3. Session Contents:</h2>
<pre>
<?php
if (isset($_SESSION['user'])) {
    echo "✓ Session['user'] exists\n";
    print_r($_SESSION['user']);
} else {
    echo "✗ Session['user'] NOT set\n";
    echo "Available session keys:\n";
    print_r(array_keys($_SESSION));
}
?>
</pre>

<h2>4. Test Login URL:</h2>
<div class="ok">
<a href="login.php" style="color:#0ff">← Back to Login</a><br><br>
<a href="indexxx.php" style="color:#0ff">→ Try Staff Dashboard</a>
</div>


<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
</body>
</html>
