<?php
// ===== POS API CONFIG (reconstructed) =====
// Same database as the root inventory system (see /db.php credentials) so the
// POS, the inventory manager and the recipe-linked stock all share one source
// of truth. If your deployment already has a working pos/api/config.php, keep
// yours — this file only exists so fresh setups boot.
if (!function_exists('getDB')) {
    function getDB() {
        static $conn = null;
        if ($conn instanceof mysqli) return $conn;
        $conn = @new mysqli('localhost', 'root', '', 'brewco_inventory');
        if ($conn->connect_errno) return false;
        $conn->set_charset('utf8mb4');
        return $conn;
    }
}
