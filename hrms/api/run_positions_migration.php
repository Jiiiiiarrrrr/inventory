<?php
// One-off migration runner: forces hrms_feature_init() to run right now
// and reports whether hrms_positions exists afterward, instead of waiting
// for apply.php or api/positions.php to be hit organically.
//
// Visit this file directly in the browser once (e.g.
// http://localhost/INVENTORY/hrms/api/run_positions_migration.php), then
// delete it — it's a one-time setup tool, not a page the app calls itself.
define('HRMS_NO_AUTH_GATE', true);
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/feature_init.php";
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = conn();
} catch (Throwable $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit;
}

echo "Running hrms_feature_init()...\n";
try {
    hrms_feature_init($pdo);
    echo "Done.\n\n";
} catch (Throwable $e) {
    echo "hrms_feature_init() threw: " . $e->getMessage() . "\n\n";
}

try {
    $exists = $pdo->query("SHOW TABLES LIKE 'hrms_positions'")->fetch();
    if (!$exists) {
        echo "hrms_positions still does NOT exist. Something is blocking table creation\n";
        echo "(check DB user privileges for CREATE TABLE on this database).\n";
        exit;
    }
    echo "hrms_positions exists.\n\n";

    $cols = $pdo->query("SHOW COLUMNS FROM hrms_positions")->fetchAll();
    echo "Columns:\n";
    foreach ($cols as $c) {
        echo "  - {$c['Field']} ({$c['Type']})\n";
    }

    $rows = $pdo->query("SELECT id, title, slots_total, is_open, linked_role FROM hrms_positions ORDER BY id")->fetchAll();
    echo "\nRows (" . count($rows) . "):\n";
    foreach ($rows as $r) {
        echo "  #{$r['id']} {$r['title']} — {$r['slots_total']} slots, is_open={$r['is_open']}, linked_role={$r['linked_role']}\n";
    }
} catch (Throwable $e) {
    echo "Verification query failed: " . $e->getMessage() . "\n";
}
