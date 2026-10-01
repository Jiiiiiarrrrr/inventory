<?php
/**
 * db.php — Shared database connection + helpers for Brew & Co. Inventory
 *
 * Every page includes this file. It:
 *   1. Starts the session.
 *   2. Opens ONE shared PDO connection to the `brewco_inventory` database.
 *   3. Provides db() / db_ok() helpers.
 *   4. Auto-detects the user's real data from the DB (no more hardcoded arrays).
 *
 * If the database is unreachable, db_ok() returns false and each page falls
 * back to its built-in demo data so the UI still renders during development.
 */

/* ---- Configuration ---------------------------------------------------- */
/*
   Local defaults are below. To deploy without putting live credentials in this
   tracked file, copy config.infinityfree.example.php to config.php, fill in
   your hosting-panel values, and keep config.php private.
*/
$localConfig = __DIR__ . '/config.php';
if (is_file($localConfig)) require_once $localConfig;

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'brewco_inventory');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('LOGIN_MAX_ATTEMPTS') || define('LOGIN_MAX_ATTEMPTS', 5);     // failed logins allowed
defined('LOGIN_LOCKOUT_MINUTES') || define('LOGIN_LOCKOUT_MINUTES', 10); // lockout duration

/* ---- Mailer configuration (forgot-password email) ----------------------- */
defined('MAIL_FROM') || define('MAIL_FROM', 'no-reply@brewco.ph');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'Brew & Co.');
defined('SITE_URL') || define('SITE_URL', 'http://localhost/INVENTORY/');

/* ---- Session hardening ------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,            // until browser closes
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/* Idle session timeout: expire the session after 60 minutes of inactivity. */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

/** Returns the shared PDO connection, or null if the DB is unreachable. */
function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (PDOException $e) {
        return null; // DB not available -> pages use demo fallback
    }
}

/** True when the live database is reachable. */
function db_ok() { return db() !== null; }

/** Tiny SELECT helper: returns all rows for a query. */
function db_all($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Tiny SELECT helper: returns the first row or null. */
function db_one($sql, $params = []) {
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

/** Runs an INSERT / UPDATE / DELETE. Returns affected row count. */
function db_exec($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Returns the id of the last inserted row. */
function db_last_id() { return db()->lastInsertId(); }

/**
 * Record one inventory movement and apply it to the item balance.
 *
 * This is intentionally PHP application logic rather than a MySQL stored
 * procedure so it works on shared/free hosting, including InfinityFree, where
 * CREATE PROCEDURE privileges are not granted. It mirrors the former
 * record_movement procedure: incoming stock increases the balance; outgoing,
 * reduce and remove actions decrease it; an adjustment uses the sign supplied.
 * Call it inside tx() whenever it forms part of a multi-step operation.
 */
function inv_record_movement($itemId, $action, $qty, $unit, $note, $performedBy) {
    $itemId = (int)$itemId;
    $performedBy = (int)$performedBy;
    $action = (string)$action;
    $qty = (float)$qty;
    if ($itemId <= 0 || !in_array($action, ['in', 'out', 'reduce', 'remove', 'adjust'], true)) {
        throw new InvalidArgumentException('Invalid stock movement.');
    }

    // The movement ledger records a positive quantity. Only an adjustment
    // retains the caller-provided sign when changing the item balance.
    $amount = abs($qty);
    $sign = $action === 'in' ? 1 : ($action === 'adjust' ? ($qty < 0 ? -1 : 1) : -1);
    db_exec('INSERT INTO stock_movements (item_id, action, qty, unit, note, performed_by) VALUES (?,?,?,?,?,?)',
            [$itemId, $action, $amount, (string)$unit, (string)$note, $performedBy]);
    $updated = db_exec('UPDATE items SET current_qty = current_qty + ? WHERE id=?', [$sign * $amount, $itemId]);
    if ($updated !== 1) throw new RuntimeException('Inventory item was not found.');
    return true;
}

/**
 * audit() — record an action in the audit_log table (best-effort; never breaks
 * the main request even if logging fails). Pass an array for $_SESSION['user'].
 */
function audit($user, $action, $detail) {
    if (!db_ok()) return;
    try {
        $uid  = (int)($user['id'] ?? 0);
        $role = $user['role'] ?? null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $st   = db()->prepare('INSERT INTO audit_log (user_id, role, action, detail, ip) VALUES (?,?,?,?,?)');
        $st->execute([$uid, $role, $action, substr($detail, 0, 255), $ip]);
    } catch (Throwable $e) { /* ignore */ }
}

/**
 * db_server() — connect to the MySQL server WITHOUT selecting a database.
 * Used by the restore tool so it can recreate a database that may not exist yet.
 */
function db_server() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * tx() — run a callback inside a database transaction.
 * Rolls back if the callback throws; returns whatever the callback returns.
 * Usage:  $result = tx(function() use ($x){ ... db_exec(...) ...; return true; });
 */
function tx($fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

/** Generate a one-time form token (stored in session) to prevent duplicate / forged submits. */
function form_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['form_token'])) $_SESSION['form_token'] = bin2hex(random_bytes(16));
    return $_SESSION['form_token'];
}

/** Verify a submitted form token matches the session token. Returns bool. */
function verify_token($submitted) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['form_token']) && is_string($submitted)
        && hash_equals($_SESSION['form_token'], $submitted);
}

/** Print a hidden CSRF/duplicate-submit token field inside a form. */
function token_field() {
    return '<input type="hidden" name="token" value="' . htmlspecialchars(form_token()) . '">';
}

/**
 * notification_bell() — prints a working notification bell (top-right of the
 * topbar) populated with real data for the current user's role. Include on any
 * page that has a topbar with a user-area.
 */
function notification_bell($role) {
    $n = get_notifications($role);
    $count = count($n);
    $html = '<style>.dropdown{display:none}.dropdown.show{display:block !important}</style>
<div class="pos" style="position:relative">
      <button class="icon-btn" onclick="toggleNoti(event)" aria-label="Notifications">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="badge" style="position:absolute;top:-4px;right:-4px;background:#a23232;color:#fff;font-size:11px;min-width:19px;height:19px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;padding:0 4px">'.$count.'</span>
      </button>
      <div class="dropdown" id="notiBox" role="menu" style="position:absolute;top:52px;right:0;width:300px;background:#fff;border-radius:14px;box-shadow:0 14px 40px rgba(74,47,34,.18);padding:8px;z-index:50">
        <h4 style="font-size:13px;color:#6f6055;padding:10px 12px 6px">NOTIFICATIONS</h4>';
    if ($count) {
        foreach ($n as $x) {
            $html .= '<div class="noti" style="display:flex;gap:10px;padding:11px 12px;border-radius:10px">
              <span class="dot" style="width:8px;height:8px;border-radius:50%;background:#a23232;margin-top:6px;flex-shrink:0"></span>
              <div class="txt" style="font-size:13px"><b>'.htmlspecialchars($x['title']).'</b><small style="color:#6f6055;display:block;margin-top:2px">'.htmlspecialchars($x['text']).'</small></div></div>';
        }
    } else {
        $html .= '<div style="padding:20px;text-align:center;color:#6f6055;font-size:13px">No new notifications. 🎉</div>';
    }
    $html .= '</div></div>';
    return $html;
}

/** Script needed for the notification bell (toggle + click-outside). */
function notification_script() {
    return "<script>
      function toggleNoti(e){ e.stopPropagation(); var b=document.getElementById('notiBox'); if(b) b.classList.toggle('show'); }
      document.addEventListener('click',function(){ var b=document.getElementById('notiBox'); if(b) b.classList.remove('show'); });
    </script>";
}

/**
 * send_mail() — sends an email using PHP's mail() function.
 * Returns true on success, false on failure. Set up an SMTP relay in
 * php.ini (sendmail_path) or use a library like PHPMailer for reliable delivery.
 */
function send_mail($to, $subject, $body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    return @mail($to, $subject, $body, $headers);
}

/** True if the given email/IP has exceeded the allowed failed-login limit. */
function login_is_locked($email) {
    if (!db_ok()) return false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $n = (int)db_one("SELECT COUNT(*) n FROM login_attempts
                      WHERE email=? AND success=0 AND created_at > ?", [$email, $cutoff])['n'];
    if ($n >= LOGIN_MAX_ATTEMPTS) return true;
    $n2 = (int)db_one("SELECT COUNT(*) n FROM login_attempts
                      WHERE ip=? AND success=0 AND created_at > ?", [$ip, $cutoff])['n'];
    return $n2 >= LOGIN_MAX_ATTEMPTS;
}

/** Record a login attempt (success or failure). */
function login_record($email, $success) {
    if (!db_ok()) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db_exec('INSERT INTO login_attempts (email, ip, success) VALUES (?,?,?)', [$email, $ip, $success ? 1 : 0]);
}

/**
 * change_password_handler() — lets the logged-in user change their own password
 * from the "My Profile" view. Redirects back to ?view=profile with a message.
 * Call it at the very top of a page (after $u is defined). Returns nothing.
 * Usage: change_password_handler($u, 'inventory-clerk.php');
 */
function change_password_handler($u, $dest) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cpw'])) return;
    $goto = function ($msg, $bad = false) use ($dest) {
        header('Location: '.$dest.'?pwmsg='.urlencode($msg).($bad ? '&pwbad=1' : '').'#profile');
        exit;
    };
    if (!verify_token($_POST['token'] ?? '')) $goto('Invalid form token — please try again.', true);
    if (!db_ok()) $goto('Database unavailable right now.', true);
    $cur  = (string)($_POST['cpw_cur'] ?? '');
    $new  = (string)($_POST['cpw_new'] ?? '');
    $conf = (string)($_POST['cpw_conf'] ?? '');
    $row  = db_one('SELECT password_hash FROM users WHERE id=?', [(int)$u['id']]);
    if (!$row || !password_verify($cur, $row['password_hash'])) {
        $goto('Current password is incorrect.', true);
    }
    if (strlen($new) < 4) {
        $goto('New password must be at least 4 characters.', true);
    }
    if ($new !== $conf) {
        $goto('New passwords do not match.', true);
    }
    db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);
    audit($u, 'user.change_pw', 'Changed own password (user #'.$u['id'].')');
    $goto('Password changed successfully.');
}

/**
 * change_password_panel() — prints the "Change Password" form for the My Profile
 * view. Place inside the profile <section class="view" id="view-profile">.
 */
function change_password_panel($dest) {
    $o  = '';
    if (isset($_GET['pwmsg'])) {
        $bad = isset($_GET['pwbad']);
        $o .= '<div style="background:'.($bad ? '#f6e0e0;color:#a23232' : '#e2f0e8;color:#256b4d').';border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px">'.htmlspecialchars($_GET['pwmsg']).'</div>';
    }
    $o .= '<div class="panel">';
    $o .= '<h2>Change Password</h2>';
    $o .= '<div class="desc">Update your login password. You\'ll need your current password to confirm.</div>';
    $o .= '<form method="post" action="'.htmlspecialchars($dest).'" onsubmit="return cpwValidate()">'.token_field();
    $o .= '<input type="hidden" name="cpw" value="1">';
    $o .= '<div class="field"><label>Current password <span class="req">*</span></label><input name="cpw_cur" id="cpw_cur" type="password" required autocomplete="current-password"></div>';
    $o .= '<div class="field"><label>New password <span class="req">*</span></label><input name="cpw_new" id="cpw_new" type="password" required minlength="4" autocomplete="new-password"></div>';
    $o .= '<div class="field"><label>Confirm new password <span class="req">*</span></label><input name="cpw_conf" id="cpw_conf" type="password" required autocomplete="new-password"></div>';
    $o .= '<button type="submit" style="width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer">Update Password</button>';
    $o .= '</form></div>';
    $o .= "<script>
      function cpwValidate(){
        var n=document.getElementById('cpw_new').value, c=document.getElementById('cpw_conf').value;
        if(n.length<4){ swalAlert('New password must be at least 4 characters.', 'error'); return false; }
        if(n!==c){ swalAlert('New passwords do not match.', 'error'); return false; }
        return true;
      }
    </script>";
    return $o;
}

/**
 * get_notifications() — returns real notifications for the logged-in user,
 * based on their role. Each item: ['title'=>.., 'text'=>..].
 */
function get_notifications($role) {
    $notifs = [];
    if (!db_ok()) return $notifs;
    try {
        if ($role === 'clerk') {
            $low = db_all("SELECT name, current_qty, unit, reorder_level FROM items
                           WHERE is_active=1 AND current_qty <= reorder_level ORDER BY (current_qty/reorder_level)");
            foreach ($low as $r) {
                $notifs[] = ['title' => $r['name'].' is low ('.$r['current_qty'].' '.$r['unit'].')',
                             'text' => 'Reorder level: '.$r['reorder_level'].' '.$r['unit']];
            }
        } elseif ($role === 'manager') {
            $p = (int)db_one("SELECT COUNT(*) n FROM purchase_requests WHERE status='pending'")['n'];
            if ($p) $notifs[] = ['title' => $p.' purchase request(s) pending approval', 'text' => 'Awaiting Finance decision'];
            $q = (int)db_one("SELECT COUNT(*) n FROM quality_checks WHERE status='pending'")['n'];
            if ($q) $notifs[] = ['title' => $q.' quality check(s) pending', 'text' => 'Verify received goods'];
            $s = (int)db_one("SELECT COUNT(*) n FROM shipments WHERE status IN ('In transit','Out for delivery','Delivered')")['n'];
            if ($s) $notifs[] = ['title' => $s.' incoming shipment(s)', 'text' => 'Track and receive'];
            $pr = (int)db_one("SELECT COUNT(*) n FROM items WHERE cost_status='pending'")['n'];
            if ($pr) $notifs[] = ['title' => $pr.' item price(s) awaiting Finance', 'text' => 'Proposed prices to approve'];
            $wr = (int)db_one("SELECT COUNT(*) n FROM warehouse_requests WHERE status='pending'")['n'];
            if ($wr) $notifs[] = ['title' => $wr.' stock request(s) from clerks', 'text' => 'Approve to move stock from warehouse to cafe'];
        } elseif ($role === 'finance') {
            $p = (int)db_one("SELECT COUNT(*) n FROM purchase_requests WHERE status='pending'")['n'];
            if ($p) $notifs[] = ['title' => $p.' purchase request(s) to approve', 'text' => 'Review against budget'];
            $pr = (int)db_one("SELECT COUNT(*) n FROM items WHERE cost_status='pending'")['n'];
            if ($pr) $notifs[] = ['title' => $pr.' item price(s) to approve', 'text' => 'Set by the Inventory Manager'];
        } elseif ($role === 'superadmin' || $role === 'admin') {
            $a = (int)db_one("SELECT COUNT(*) n FROM audit_log WHERE created_at >= NOW() - INTERVAL 1 DAY")['n'];
            if ($a) $notifs[] = ['title' => $a.' action(s) in the last 24h', 'text' => 'Check the audit trail'];
            $pr = (int)db_one("SELECT COUNT(*) n FROM items WHERE cost_status='pending'")['n'];
            if ($pr) $notifs[] = ['title' => $pr.' item price(s) pending', 'text' => 'Finance approval needed'];
        } elseif ($role === 'cashier' || $role === 'barista' || $role === 'cleaner') {
            $pl = (int)db_one("SELECT COUNT(*) n FROM pos_orders WHERE status='placed'")['n'];
            if ($pl) $notifs[] = ['title' => $pl.' order(s) waiting for payment', 'text' => 'Finalize in the cashier screen'];
        }
    } catch (Throwable $e) { /* ignore */ }
    return $notifs;
}

// ---- Inventory: purchase-letter + stock-request admin gate (idempotent migration) ----
function inv_ensure_purchase_flow() {
    if (!db_ok()) return;
    try {
        $c = db_one("SHOW COLUMNS FROM warehouse_requests LIKE 'admin_status'");
        if (!$c) {
            db_exec("ALTER TABLE warehouse_requests ADD COLUMN admin_status VARCHAR(12) NOT NULL DEFAULT 'pending'");
            db_exec("ALTER TABLE warehouse_requests ADD COLUMN admin_decided_at DATETIME NULL");
            // legacy rows already decided by the warehouse: treat as previously approved
            db_exec("UPDATE warehouse_requests SET admin_status='approved' WHERE status<>'pending'");
        }
        $c2 = db_one("SHOW COLUMNS FROM purchase_requests LIKE 'letter_path'");
        if (!$c2) {
            db_exec("ALTER TABLE purchase_requests ADD COLUMN letter_path VARCHAR(255) NULL");
        }
        $c3 = db_one("SHOW COLUMNS FROM purchase_requests LIKE 'signature_path'");
        if (!$c3) {
            db_exec("ALTER TABLE purchase_requests ADD COLUMN signature_path VARCHAR(255) NULL");
        }
        $c4 = db_one("SHOW COLUMNS FROM purchase_requests LIKE 'selfie_path'");
        if (!$c4) {
            db_exec("ALTER TABLE purchase_requests ADD COLUMN selfie_path VARCHAR(255) NULL");
        }
        $c6 = db_one("SHOW COLUMNS FROM shipments LIKE 'pr_id'");
        if (!$c6) {
            db_exec("ALTER TABLE shipments ADD COLUMN pr_id INT UNSIGNED NULL");
        }
        $c5 = db_one("SHOW COLUMNS FROM users LIKE 'registered_signature_path'");
        if (!$c5) {
            db_exec("ALTER TABLE users ADD COLUMN registered_signature_path VARCHAR(255) NULL");
            db_exec("ALTER TABLE users ADD COLUMN registered_signature_updated_at DATETIME NULL");
        }
    } catch (Throwable $e) { /* ignore */ }
    inv_refresh_menu_stock();
}

// ---- Recipe-linked stock: how many WHOLE drinks can still be made ----
// For each menu item with a recipe: min over ingredients of floor(stock / qty-per-drink).
function inv_recipe_servings_map() {
    if (!db_ok()) return [];
    try {
        $rows = db_all("SELECT mi.menu_item_id, MIN(FLOOR(i.current_qty / mi.qty)) srv
                        FROM menu_ingredients mi
                        JOIN items i ON i.id=mi.item_id
                        WHERE mi.qty > 0
                        GROUP BY mi.menu_item_id");
    } catch (Throwable $e) { return []; }
    $map = [];
    foreach ($rows as $r) $map[(int)$r['menu_item_id']] = max(0, (int)$r['srv']);
    return $map;
}
// Push the computed whole-drink counts into menu_items.stock (what the POS shows).
function inv_refresh_menu_stock() {
    if (!db_ok()) return;
    try {
        db_exec("UPDATE menu_items m
                 SET m.stock = COALESCE((
                     SELECT MIN(FLOOR(i.current_qty / mi.qty))
                     FROM menu_ingredients mi
                     JOIN items i ON i.id=mi.item_id
                     WHERE mi.menu_item_id = m.id AND mi.qty > 0
                 ), m.stock)
                 WHERE m.deleted_at IS NULL
                   AND EXISTS (SELECT 1 FROM menu_ingredients mi WHERE mi.menu_item_id = m.id)");
    } catch (Throwable $e) { /* ignore */ }
}

// ---- Inventory registered signature: 30-day update lock + PNG saver ----
function inv_sig_lock($updatedAt) {
    $days = 30;
    if (!$updatedAt) return ['can' => true, 'days_left' => 0];
    $last = strtotime($updatedAt);
    if ($last === false) return ['can' => true, 'days_left' => 0];
    $elapsed = (time() - $last) / 86400;
    if ($elapsed >= $days) return ['can' => true, 'days_left' => 0];
    return ['can' => false, 'days_left' => (int)ceil($days - $elapsed)];
}
function inv_save_registered_signature($uid, $dataUrl) {
    if (!is_string($dataUrl) || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) return null;
    $bin = base64_decode(str_replace(["\r", "\n", ' '], '', $m[1]), true);
    if ($bin === false || strlen($bin) < 64 || strlen($bin) > 2 * 1024 * 1024) return null;
    if (substr($bin, 1, 3) !== 'PNG') return null;
    $dir = __DIR__ . '/uploads/signatures';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $path = 'uploads/signatures/inv_reg_' . (int)$uid . '.png';
    if (@file_put_contents(__DIR__ . '/' . $path, $bin) === false) return null;
    db_exec('UPDATE users SET registered_signature_path=?, registered_signature_updated_at=NOW() WHERE id=?',
            [$path, (int)$uid]);
    return $path;
}
function inv_registered_signature($uid) {
    if (!db_ok()) return ['path' => '', 'updated_at' => null];
    $row = db_one('SELECT registered_signature_path, registered_signature_updated_at FROM users WHERE id=?', [(int)$uid]);
    return ['path' => (string)($row['registered_signature_path'] ?? ''), 'updated_at' => $row['registered_signature_updated_at'] ?? null];
}


// ===== REMEMBER-ME: hashed rotating token (password NEVER stored in a cookie) =====
function remember_ensure() {
    try { db_exec("ALTER TABLE users ADD remember_selector VARCHAR(64) NULL"); } catch (Throwable $e) {}
    try { db_exec("ALTER TABLE users ADD remember_hash VARCHAR(255) NULL"); } catch (Throwable $e) {}
    try { db_exec("ALTER TABLE users ADD remember_expires DATETIME NULL"); } catch (Throwable $e) {}
}
function remember_issue($userId, $remember) {
    remember_ensure();
    if (!$remember) {
        try { db_exec("UPDATE users SET remember_selector=NULL, remember_hash=NULL, remember_expires=NULL WHERE id=?", [(int)$userId]); } catch (Throwable $e) {}
        if (!empty($_COOKIE['brewco_remember'])) setcookie('brewco_remember', '', time() - 3600, '/', '', false, true);
        return;
    }
    $sel = bin2hex(random_bytes(16));
    $ver = bin2hex(random_bytes(32));
    db_exec("UPDATE users SET remember_selector=?, remember_hash=?, remember_expires=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id=?",
            [$sel, password_hash($ver, PASSWORD_DEFAULT), (int)$userId]);
    setcookie('brewco_remember', $sel . ':' . $ver, time() + 60*60*24*30, '/', '', false, true);
}
function remember_consume($cookie) {
    remember_ensure();
    $parts = explode(':', (string)$cookie, 2);
    $sel = $parts[0] ?? ''; $ver = $parts[1] ?? '';
    if ($sel === '' || $ver === '') return null;
    $u = db_one("SELECT id, remember_hash, remember_expires FROM users WHERE remember_selector=? LIMIT 1", [$sel]);
    if (!$u || empty($u['remember_expires']) || strtotime($u['remember_expires']) < time()) return null;
    if (!password_verify($ver, (string)$u['remember_hash'])) return null;
    $nv = bin2hex(random_bytes(32)); // rotate on every use
    db_exec("UPDATE users SET remember_hash=?, remember_expires=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id=?",
            [password_hash($nv, PASSWORD_DEFAULT), (int)$u['id']]);
    setcookie('brewco_remember', $sel . ':' . $nv, time() + 60*60*24*30, '/', '', false, true);
    return (int)$u['id'];
}

/* ---- Round 5 hardening -------------------------------------------------
   1) Login guard: EVERY root page now requires a logged-in session unless it
      is one of the public pages (login / password reset / guest kiosk).
   2) CSRF belt: cross-origin POSTs are rejected on top of SameSite=Lax.
   ---------------------------------------------------------------------- */
function brewco_public_page($file) {
    static $public = ['login.php','forgot-password.php','reset-password.php',
                      'logout.php','index.php','db.php'];
    return in_array(strtolower(basename((string)$file)), $public, true);
}
function brewco_origin_ok() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return true;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return true;   // same-origin form posts may omit Origin
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $p    = parse_url($origin);
    $oh   = strtolower($p['host'] ?? '');
    $op   = isset($p['port']) ? ':' . $p['port'] : '';
    return hash_equals($host, $oh . $op) || hash_equals($host, $oh);
}
if (PHP_SAPI !== 'cli' && !defined('BREWCO_NO_GUARD')) {
    if (!brewco_origin_ok()) { http_response_code(403); exit('Cross-origin request blocked.'); }
    if (!brewco_public_page($_SERVER['SCRIPT_FILENAME'] ?? '') && empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}
