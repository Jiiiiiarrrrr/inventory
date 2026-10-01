<?php
// ===== SESSION AUTH GUARD =====
// Include this after config.php in any endpoint that should only be reachable by a
// logged-in user. Call requireRole([...]) with the roles allowed to hit that action.
//
// Relies on PHP sessions (cookie-based) — login endpoints (auth.php, account.php's
// loginAccount()) must call session_start() and write $_SESSION['user_id'] /
// $_SESSION['role'] on success. Nothing here trusts data sent in the request body.

function requireLogin() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in.']);
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to do that.']);
        exit;
    }
}

// Convenience: current logged-in user's id, or null if not logged in.
function currentUserId() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return $_SESSION['user_id'] ?? null;
}
