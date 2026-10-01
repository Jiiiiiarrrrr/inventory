<?php
require_once __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

function conn() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    try {
        return new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        response(["success" => false, "message" => "Database connection failed: " . $e->getMessage()], 500);
    }
}

function response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getBody() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function addLog($employee, $type, $detail) {
    try {
        $pdo = conn();
        $stmt = $pdo->prepare("INSERT INTO hrms_audit_logs (employee_name, type, detail) VALUES (?, ?, ?)");
        $stmt->execute([$employee, $type, $detail]);
    } catch (Exception $e) {
        // Ignore logging errors so main action still works.
    }
}


// ===== Brute-force lockout (6 fails / 10 min per account) =====
function hrms_login_locked($pdo, $acct) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_login_attempts (acct VARCHAR(190) NOT NULL PRIMARY KEY, fails INT NOT NULL DEFAULT 0, last_fail DATETIME NULL)");
        $st = $pdo->prepare("SELECT fails, last_fail FROM hrms_login_attempts WHERE acct=?");
        $st->execute([$acct]); $r = $st->fetch();
        if ($r && (int)$r['fails'] >= 6 && $r['last_fail'] && strtotime($r['last_fail']) > time() - 600) return true;
    } catch (Exception $e) {}
    return false;
}
function hrms_login_record($pdo, $acct, $ok) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_login_attempts (acct VARCHAR(190) NOT NULL PRIMARY KEY, fails INT NOT NULL DEFAULT 0, last_fail DATETIME NULL)");
        if ($ok) { $pdo->prepare("DELETE FROM hrms_login_attempts WHERE acct=?")->execute([$acct]); return; }
        $pdo->prepare("INSERT INTO hrms_login_attempts (acct, fails, last_fail) VALUES (?,1,NOW())
                        ON DUPLICATE KEY UPDATE fails=fails+1, last_fail=NOW()")->execute([$acct]);
    } catch (Exception $e) {}
}

/* ---- Round 5 hardening -------------------------------------------------
   Every HRMS API endpoint now requires a logged-in session. auth.php stays
   public because it IS the login flow (its me/logout actions check the
   session themselves). Cross-origin POSTs are rejected (CSRF belt on top
   of the SameSite=Lax session cookie).
   ---------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli' && !defined('HRMS_NO_AUTH_GATE')) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_SERVER['HTTP_ORIGIN'])) {
        $__h = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $__p = parse_url($_SERVER['HTTP_ORIGIN']);
        $__oh = strtolower($__p['host'] ?? '');
        $__o  = $__oh . (isset($__p['port']) ? ':' . $__p['port'] : '');
        if (!hash_equals($__h, $__o) && !hash_equals($__h, $__oh)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cross-origin request blocked']);
            exit;
        }
    }
    $__script = strtolower(basename($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($__script !== 'auth.php' && empty($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Login required']);
        exit;
    }
}
