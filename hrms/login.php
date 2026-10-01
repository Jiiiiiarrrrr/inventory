<?php
/**
 * HRMS login router — safe to open directly AND safe to include as a guard.
 *
 * Direct visit (address bar / bookmark / old link):
 *   - already logged in  -> straight to your dashboard for your role
 *   - not logged in      -> straight to the real login page (hrms-login)
 *   Never prints an error page, never exposes anything.
 *
 * Included by another HRMS page (legacy guard usage):
 *   - makes sure a session exists; sends anonymous visitors to the login page.
 */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (!function_exists('hrms_role_home')) {
    function hrms_role_home($role) {
        switch ((string)$role) {
            case 'superadmin': return 'indexx.php';
            case 'staff':      return 'indexxx.php';
            case 'finance':    return 'finance.php';
            case 'applicant':  return 'applicant-portal.php';
            case 'admin':
            default:           return 'index.php';
        }
    }
}

$__hrms_direct = isset($_SERVER['SCRIPT_FILENAME'])
    && @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__);

if ($__hrms_direct) {
    if (!empty($_SESSION['user'])) {
        header('Location: ' . hrms_role_home($_SESSION['user']['role'] ?? 'admin'));
        exit;
    }
    // Render the real login interface server-side. This means the hub never
    // has to request the legacy hrms-login.php endpoint directly.
    require __DIR__ . '/hrms-login.php';
    exit;
}

/* Included as a guard: anonymous visitors go to the real login page. */
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
