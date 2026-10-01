<?php
require __DIR__ . '/db.php';
$u = $_SESSION['user'] ?? null;
if ($u && !empty($u['id'])) { try { remember_issue((int)$u['id'], false); } catch (Throwable $e) {} }
if (isset($_COOKIE['brewco_pass'])) setcookie('brewco_pass', '', time() - 3600, '/', '', false, true);
if ($u) { try { audit($u, 'auth.logout', 'User logged out'); } catch (Throwable $e) { /* ignore */ } }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php'); exit;
