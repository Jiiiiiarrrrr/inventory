<?php
// ===== LOGOUT API =====
// Clears the POS staff session. Called by pos.php's logoutPos() for staff mode;
// customer mode never touches the session so it just returns to the landing.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
unset($_SESSION['user_id'], $_SESSION['role']);

echo json_encode(['success' => true]);
