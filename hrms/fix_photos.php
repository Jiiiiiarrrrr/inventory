<?php
// =====================================================================
// fix_photos.php — attendance photo repair tool (superadmin/admin only)
// Open: http://localhost/INVENTORY/hrms/fix_photos.php
//
// For every attendance row whose photo file cannot be found:
//   1. Searches the whole INVENTORY folder for the same filename.
//      Found  -> re-links photo_path to the real location (fixed).
//   2. Not found anywhere -> the JPG was deleted (folder swap / no backup).
//      Press "Clear dead references" to set photo_path = NULL so the
//      UI shows "No photo" instead of "Photo missing".
// (Only way to get the actual old images back: restore them from the
//  Windows Recycle Bin or a backup of the old hrms/uploads folder.)
// =====================================================================
require_once __DIR__ . '/api/common.php';
require_once __DIR__ . '/security.php';
session_start();
$u = require_roles(['admin', 'superadmin']);
$pdo = conn();
header("Content-Type: text/html; charset=utf-8");

$hrms = str_replace('\\', '/', realpath(__DIR__));
$root = str_replace('\\', '/', realpath(__DIR__ . '/..'));   // INVENTORY root

// ---- build a filename => full path map of everything under INVENTORY ----
$fileMap = [];
try {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile()) $fileMap[$f->getFilename()] = str_replace('\\', '/', $f->getPathname());
    }
} catch (Exception $e) {}

$clearMode = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_missing');
$rows = $pdo->query("SELECT id, employee_id, photo_path, created_at FROM hrms_attendance_records WHERE photo_path IS NOT NULL AND photo_path <> '' ORDER BY id DESC")->fetchAll();

$report = [];
foreach ($rows as $r) {
    $rel = $r['photo_path'];
    $abs = $hrms . '/' . $rel;
    if (is_file($abs)) { $report[] = ['id' => $r['id'], 'when' => $r['created_at'], 'path' => $rel, 'state' => 'ok', 'new' => '']; continue; }

    $base = basename($rel);
    $found = $fileMap[$base] ?? null;
    if ($found) {
        // relative path from the hrms/ folder (what the UI expects)
        if (strpos($found, $hrms . '/') === 0) {
            $newRel = substr($found, strlen($hrms) + 1);
        } else {
            $newRel = '../' . substr($found, strlen($root) + 1);
        }
        $pdo->prepare("UPDATE hrms_attendance_records SET photo_path = ? WHERE id = ?")->execute([$newRel, $r['id']]);
        $report[] = ['id' => $r['id'], 'when' => $r['created_at'], 'path' => $rel, 'state' => 'relinked', 'new' => $newRel];
        continue;
    }

    if ($clearMode) {
        $pdo->prepare("UPDATE hrms_attendance_records SET photo_path = NULL WHERE id = ?")->execute([$r['id']]);
        $report[] = ['id' => $r['id'], 'when' => $r['created_at'], 'path' => $rel, 'state' => 'cleared', 'new' => ''];
    } else {
        $report[] = ['id' => $r['id'], 'when' => $r['created_at'], 'path' => $rel, 'state' => 'missing', 'new' => ''];
    }
}
$missing = count(array_filter($report, fn($x) => $x['state'] === 'missing'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="../favicon.png?v=2">
<link rel="alternate icon" type="image/x-icon" href="../favicon.ico?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Photo Repair</title>
<style>
:root{--accent:#a9714a;--cream:#faf6f0;--card:#fff;--ink:#3b2313;--muted:#7a6055;--line:#e8ddd0;--ok:#4f7a4a;--warn:#c99a5b;--danger:#a8492f;--radius:14px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);padding:24px}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:var(--muted);font-size:13px;margin-bottom:20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:20px;margin-bottom:16px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;padding:10px 14px;border-bottom:1px solid var(--line)}
td{padding:10px 14px;border-bottom:1px solid var(--line);font-size:13px;word-break:break-all}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.b-ok{background:#e2ecdf;color:var(--ok)} .b-relinked{background:#e0ecf5;color:#2b6ca3}
.b-missing{background:#fce4dc;color:var(--danger)} .b-cleared{background:#f0f0f0;color:#777}
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-danger{background:var(--danger);color:#fff} .btn-secondary{background:var(--card);color:var(--accent);border:1.5px solid var(--line)}
.note{background:#fff4e0;border:1px solid #eadbc0;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:16px}
</style>

<link rel="stylesheet" href="../responsive-ui.css">
<link rel="stylesheet" href="../dashboard-navigation.css">
</head>
<body>
<h1>🔧 Attendance Photo Repair</h1>
<p class="sub">Checks every attendance photo reference against the files on disk.</p>

<?php if ($missing > 0): ?>
<div class="note">⚠️ <?= $missing ?> photo file(s) no longer exist anywhere in the INVENTORY folder (they were deleted with the old hrms/uploads folder). Restore them from the Windows Recycle Bin / backup if you still have them — otherwise clear the dead references below so the list shows “No photo”.</div>
<form method="post">
  <input type="hidden" name="action" value="clear_missing">
  <button class="btn btn-danger" onclick="return sweetConfirmSubmit(event,'Set photo_path = NULL for all dead references? The JPG files themselves cannot be recovered by this tool.')">Clear dead references (show “No photo”)</button>
</form>
<br>
<?php endif; ?>

<div class="card">
<table>
<thead><tr><th>Record</th><th>Date</th><th>Stored path</th><th>Status</th><th>New path</th></tr></thead>
<tbody>
<?php if (!$report): ?><tr><td colspan="5" style="text-align:center;color:var(--muted)">No attendance photos referenced at all.</td></tr><?php endif; ?>
<?php foreach ($report as $x): ?>
<tr>
  <td>#<?= (int)$x['id'] ?></td>
  <td><?= htmlspecialchars($x['when']) ?></td>
  <td><?= htmlspecialchars($x['path']) ?></td>
  <td><span class="badge b-<?= $x['state'] ?>"><?= strtoupper($x['state']) ?></span></td>
  <td><?= htmlspecialchars($x['new']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<a href="indexx.php"><button class="btn btn-secondary">← Back to HRMS</button></a>
<script src="../alerts.js"></script>

<script src="../responsive-ui.js" defer></script>
<script src="../dashboard-navigation.js" defer></script>
</body>
</html>
