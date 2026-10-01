<?php
require_once __DIR__ . "/common.php";
session_start();
$pdo = conn();

function is_hash_login($password) {
    return preg_match('/^\$2y\$|^\$argon2/i', (string)$password);
}
function pass_ok_login($input, $stored) {
    return is_hash_login($stored) ? password_verify($input, $stored) : hash_equals((string)$stored, (string)$input);
}
function make_hash_login($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
function ensure_applicant_accounts($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_applicant_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_applicant_accounts($pdo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    response(["success" => false, "message" => "Method not allowed"], 405);
}

$data = getBody();
$username = trim($data["username"] ?? "");
$password = trim($data["password"] ?? "");
if ($username === "" || $password === "") {
    response(["success" => false, "message" => "Username/email and password are required"], 400);
}

// 1) System hrms_users: superadmin, admin, staff, finance
try {
    if (hrms_login_locked($pdo, $username)) {
    response(["success" => false, "message" => "Too many failed attempts. Please try again in 10 minutes."], 429);
}
$stmt = $pdo->prepare("SELECT id, username, password, role, full_name, email, employee_id, must_change_password FROM hrms_users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    hrms_login_record($pdo, $username, false);
    // Self-heal: a hired applicant whose hrms_users row still carries an older
    // hash adopts the password they set when e-signing their offer.
    if ($user && !pass_ok_login($password, $user["password"])) {
        try {
            $st = $pdo->prepare("SELECT staff_password_hash FROM hrms_applicants WHERE email = ? ORDER BY id DESC LIMIT 1");
            $st->execute([$user["email"]]);
            $h = (string)$st->fetchColumn();
            if ($h !== '' && password_verify($password, $h)) {
                $pdo->prepare("UPDATE hrms_users SET password = ? WHERE id = ?")->execute([$h, $user["id"]]);
                $user["password"] = $h;
            }
        } catch (Exception $e) { /* column not migrated yet */ }
    }
    if ($user && pass_ok_login($password, $user["password"])) {
        hrms_login_record($pdo, $username, true);
        if (!is_hash_login($user["password"])) {
            $pdo->prepare("UPDATE hrms_users SET password = ? WHERE id = ?")->execute([make_hash_login($password), $user["id"]]);
        }
        // finance -> finance.php (new Finance module shell)
        $redirect = $user["role"] === "superadmin" ? "indexx.php"
                  : ($user["role"] === "staff" ? "indexxx.php"
                  : ($user["role"] === "finance" ? "finance.php" : "index.php"));
        session_regenerate_id(true); // new session id on login (defeats session fixation)
        $_SESSION["user"] = [
            "id" => $user["id"],
            "username" => $user["username"],
            "role" => $user["role"],
            "name" => $user["full_name"],
            "email" => $user["email"],
            "employee_id" => $user["employee_id"] ?? null,
            "must_change_password" => intval($user["must_change_password"] ?? 0)
        ];
        if (intval($user["must_change_password"] ?? 0) === 1) $redirect = "change-password.php";
        response(["success" => true, "user" => $_SESSION["user"], "redirect" => $redirect]);
    }
} catch (Exception $e) {
    // Continue to applicant login if hrms_users table check fails for any reason.
}

// 2) Temporary applicant account: read-only applicant portal only
$stmt = $pdo->prepare("SELECT * FROM hrms_applicant_accounts WHERE email = ? LIMIT 1");
$stmt->execute([$username]);
$app = $stmt->fetch();
if ($app && pass_ok_login($password, $app["password"])) {
    if ($app["status"] !== "active") {
        response(["success" => false, "message" => "Applicant account is no longer active. If you were hired, use the staff login sent by HR."], 403);
    }
    if (!is_hash_login($app["password"])) {
        $pdo->prepare("UPDATE hrms_applicant_accounts SET password = ? WHERE id = ?")->execute([make_hash_login($password), $app["id"]]);
    }
    session_regenerate_id(true); // new session id on login (defeats session fixation)
    $_SESSION["user"] = [
        "id" => $app["id"],
        "username" => $app["email"],
        "role" => "applicant",
        "name" => $app["full_name"],
        "email" => $app["email"],
        "applicant_id" => $app["applicant_id"]
    ];
    response(["success" => true, "user" => $_SESSION["user"], "redirect" => "applicant-portal.php"]);
}

response(["success" => false, "message" => "Invalid username or password"], 401);
