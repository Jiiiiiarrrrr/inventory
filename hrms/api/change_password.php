<?php
require_once __DIR__ . "/common.php";
session_start();

if (!isset($_SESSION["user"])) {
    response(["success" => false, "message" => "Login required. Please login again."], 401);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    response(["success" => false, "message" => "Method not allowed"], 405);
}

$data = getBody();
$newPassword = trim($data["new_password"] ?? "");

if ($newPassword === "") {
    response(["success" => false, "message" => "New password is required."], 400);
}

if (strlen($newPassword) < 6) {
    response(["success" => false, "message" => "New password must be at least 6 characters."], 400);
}

$pdo = conn();
$userId = intval($_SESSION["user"]["id"] ?? 0);

try {
    $pdo->query("ALTER TABLE hrms_users ADD must_change_password TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT id FROM hrms_users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    response(["success" => false, "message" => "User not found. Please login again."], 404);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE hrms_users SET password = ?, must_change_password = 0 WHERE id = ?");
$stmt->execute([$newHash, $userId]);

$_SESSION["user"]["must_change_password"] = 0;

try {
    addLog($_SESSION["user"]["name"] ?? $_SESSION["user"]["username"] ?? "User", "Password Change", "Changed account password.");
} catch (Exception $e) {}

response(["success" => true, "message" => "Password changed successfully."]);
