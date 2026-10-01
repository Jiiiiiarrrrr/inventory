<?php
// ===== Inventory registered-signature panel (shared include) =====
// Staff can register / update their e-signature ONLY from their profile page.
// Updates are locked to once every 30 days (first registration is always allowed).
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sig_action']) && $_POST['sig_action'] === 'register_signature') {
    $page = $_POST['sig_page'] ?? 'inventory-manager.php';
    if (!preg_match('/^[a-z0-9-]+\.php$/', $page)) $page = 'inventory-manager.php';
    $back = $page . '?view=profile';
    if (!empty($_SESSION['user']) && verify_token($_POST['token'] ?? '')) {
        $u2 = $_SESSION['user'];
        inv_ensure_purchase_flow();
        $reg = inv_registered_signature((int)$u2['id']);
        $lock = inv_sig_lock($reg['updated_at']);
        if (!$lock['can']) {
            header('Location: ' . $back . '&msg=' . urlencode('You can update your signature again in ' . $lock['days_left'] . ' day(s) — updates are allowed once a month.') . '&bad=1'); exit;
        }
        $path = inv_save_registered_signature((int)$u2['id'], $_POST['sig_data'] ?? '');
        if ($path) {
            audit($u2, 'signature.register', ($reg['path'] ? 'Updated' : 'Registered') . ' inventory e-signature from profile');
            header('Location: ' . $back . '&msg=' . urlencode('Your e-signature has been saved. Use the same signature when signing requests.') . '&saved=1'); exit;
        }
        header('Location: ' . $back . '&msg=' . urlencode('Could not read the signature drawing — please draw it again and save.') . '&bad=1'); exit;
    }
    header('Location: ' . $back . '&msg=' . urlencode('Invalid form token — please try again.') . '&bad=1'); exit;
}

function inv_signature_panel($u, $page) {
    inv_ensure_purchase_flow();
    $reg  = inv_registered_signature((int)$u['id']);
    $lock = inv_sig_lock($reg['updated_at']);
    $msg  = $_GET['msg'] ?? ''; $bad = isset($_GET['bad']); $saved = isset($_GET['saved']);
    ob_start(); ?>
    <div class="panel" id="invSigPanel">
      <style>
        #invSigPad{width:100%;height:170px;border:1.5px solid var(--line,#e8ddd0);border-radius:12px;background:#fff;cursor:crosshair;touch-action:none;display:block}
        .inv-reg-box{border:1.5px dashed var(--line,#e8ddd0);border-radius:10px;padding:10px;background:#fff;min-height:86px;display:flex;align-items:center;justify-content:center;font-size:12.5px;color:var(--muted,#7a6055);text-align:center;margin:8px 0 12px}
        .inv-reg-box img{max-height:96px;max-width:100%;object-fit:contain}
      </style>
      <h2>✍️ Registered E-Signature</h2>
      <div class="desc">Your official signature for inventory documents. It can be registered or updated <b>only here in your profile</b>, and only <b>once a month</b>. Request signing verifies your drawing against this signature.</div>
      <?php if($saved && !$msg): ?><div style="background:#e2f0e8;color:var(--ok);border-radius:10px;padding:10px 14px;font-weight:600;font-size:13px;margin-bottom:10px">✅ Saved.</div><?php endif; ?>
      <?php if($msg): ?><div style="background:<?= $bad?'#f6e0e0':'#e2f0e8' ?>;color:<?= $bad?'var(--danger)':'var(--ok)' ?>;border-radius:10px;padding:10px 14px;font-weight:600;font-size:13px;margin-bottom:10px"><?= $bad?'⚠':'✅' ?> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <div class="inv-reg-box" id="invRegBox">
        <?php if($reg['path']): ?><img src="<?= htmlspecialchars($reg['path']) ?>?v=<?= (int)strtotime((string)$reg['updated_at']) ?>" alt="Your registered signature"><?php else: ?>No registered signature yet — draw and save yours below.<?php endif; ?>
      </div>
      <?php if($reg['updated_at']): ?>
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:10px">Last updated: <?= htmlspecialchars($reg['updated_at']) ?>
        <?php if(!$lock['can']): ?> · 🔒 You can update it again in <b><?= $lock['days_left'] ?> day(s)</b>.<?php endif; ?></div>
      <?php endif; ?>
      <form method="post" action="<?= htmlspecialchars($page) ?>" id="invSigForm"><?= token_field() ?>
        <input type="hidden" name="sig_action" value="register_signature">
        <input type="hidden" name="sig_page" value="<?= htmlspecialchars($page) ?>">
        <input type="hidden" name="sig_data" id="invSigData" value="">
        <label style="display:block;font-weight:700;margin:0 0 6px;font-size:13px"><?= $reg['path'] ? 'Draw your new signature' : 'Draw your signature' ?></label>
        <div style="position:relative"><canvas id="invSigPad" width="1000" height="340" style="pointer-events:none"></canvas><div id="invSigGate" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.65);border-radius:12px"><button type="button" class="btn-sm ok" id="invSigGateBtn">✍️ Confirm &amp; Begin E-Sign</button></div></div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px">Draw with your mouse, finger, or stylus. This exact signature will be required when you sign purchase requests.</div>
        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
          <button type="submit" class="btn-sm ok" id="invSigSave" <?= $lock['can'] ? '' : 'disabled' ?> onclick="return invSigSubmit(event)">💾 Save Signature</button>
          <button type="button" class="btn-sm" onclick="invSigClear()">Clear</button>
        </div>
        <?php if(!$lock['can']): ?><div style="color:#a8492f;font-size:12.5px;margin-top:8px;font-weight:600">🔒 Signature updates are locked to once a month — try again in <?= $lock['days_left'] ?> day(s).</div><?php endif; ?>
      </form>
    </div>
    <?php return ob_get_clean();
}
