<?php
require_once __DIR__ . "/common.php";
session_start();

if (!isset($_SESSION["user"])) {
    response(["success" => false, "message" => "Not logged in"], 401);
}

response([
    "success" => true,
    "user" => $_SESSION["user"]
]);
