/* =====================================================================
   alerts.js — lightweight SweetAlert-style dialogs (no external CDN).
   Provides:
     swalAlert(message, type)            -> info/success/warning/error popup
     swalConfirm(message, onConfirm)     -> confirmation dialog
   These replace the plain browser alert()/confirm() dialogs.
   ===================================================================== */
(function () {
  var COLORS = {
    ok:      '#256b4d',
    warn:    '#96690a',
    danger:  '#a23232',
    accent:  '#a9714a'
  };
  var ICONS = {
    info:  '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/>',
    success:'<path d="M20 6L9 17l-5-5"/>',
    warn:  '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
    error: '<path d="M18 6L6 18M6 6l12 12"/>'
  };
  var svgNS = 'http://www.w3.org/2000/svg';
  var activeEl = null;

  function build(icon, color, msg, onDismiss) {
    var bg = document.createElement('div');
    bg.style.cssText = 'position:fixed;inset:0;background:rgba(40,26,18,.5);display:flex;align-items:center;justify-content:center;z-index:10000;padding:20px;font-family:"Segoe UI",system-ui,sans-serif;';
    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:16px;max-width:380px;width:100%;padding:28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:swalpop .2s ease;transform-origin:center;';
    var style = document.createElement('style');
    style.textContent = '@keyframes swalpop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}';
    document.head.appendChild(style);
    var circle = document.createElement('div');
    circle.style.cssText = 'width:64px;height:64px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;';
    circle.style.background = color + '1f';
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.style.cssText = 'width:32px;height:32px;stroke:' + color + ';fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;';
    svg.innerHTML = ICONS[icon];
    circle.appendChild(svg);
    var title = document.createElement('div');
    title.style.cssText = 'font-size:19px;font-weight:800;color:#33261d;margin-bottom:10px;';
    title.textContent = msg.title || '';
    var text = document.createElement('div');
    text.style.cssText = 'font-size:14px;color:#6f6055;line-height:1.6;margin-bottom:20px;white-space:pre-line;';
    text.textContent = msg.message || msg;
    box.appendChild(circle);
    if (title.textContent) box.appendChild(title);
    box.appendChild(text);
    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:10px;';
    bg.appendChild(box);
    bg.addEventListener('click', function (e) { if (e.target === bg) close(); });
    document.addEventListener('keydown', onKey);
    function close() { document.removeEventListener('keydown', onKey); bg.remove(); if (activeEl) activeEl.focus(); if (typeof onDismiss === 'function') onDismiss(); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    return { bg: bg, box: box, actions: actions, close: close };
  }

  function makeBtn(label, color, primary) {
    var b = document.createElement('button');
    b.textContent = label;
    b.style.cssText = 'flex:1;padding:12px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid #eaded0;background:#fff;color:#33261d;';
    if (primary) { b.style.background = color; b.style.color = '#fff'; b.style.borderColor = color; }
    b.addEventListener('mouseover', function () { if (primary) b.style.opacity = '.9'; else b.style.background = '#f6ecdd'; });
    b.addEventListener('mouseout', function () { if (primary) b.style.opacity = '1'; else b.style.background = '#fff'; });
    return b;
  }

  function swalAlert(message, type) {
    type = type || 'info';
    var color = type === 'success' ? COLORS.ok : (type === 'warn' ? COLORS.warn : (type === 'error' ? COLORS.danger : COLORS.accent));
    var icon = type === 'success' ? 'success' : (type === 'warn' ? 'warn' : (type === 'error' ? 'error' : 'info'));
    var d = build(icon, color, { message: message });
    activeEl = document.activeElement;
    var ok = makeBtn('OK', color, true);
    ok.addEventListener('click', d.close);
    d.actions.appendChild(ok);
    d.box.appendChild(d.actions);
    document.body.appendChild(d.bg);
    ok.focus();
  }

  function swalConfirm(message, onConfirm, onCancel) {
    var settled = false;
    var d = build('warn', COLORS.accent, { message: message }, function () {
      // dismissed via Escape / backdrop click = cancel
      if (!settled && typeof onCancel === 'function') onCancel();
    });
    activeEl = document.activeElement;
    var cancel = makeBtn('Cancel', '#6f6055', false);
    cancel.addEventListener('click', function () { settled = true; d.close(); if (typeof onCancel === 'function') onCancel(); });
    var ok = makeBtn('Yes', COLORS.accent, true);
    ok.addEventListener('click', function () { settled = true; d.close(); if (typeof onConfirm === 'function') onConfirm(); });
    d.actions.appendChild(cancel);
    d.actions.appendChild(ok);
    d.box.appendChild(d.actions);
    document.body.appendChild(d.bg);
    ok.focus();
    return d;
  }

  /* Promise wrapper:  if (await swalAsk('Are you sure?')) { ... }  */
  function swalAsk(message) {
    return new Promise(function (resolve) {
      swalConfirm(message, function () { resolve(true); }, function () { resolve(false); });
    });
  }

  /* Every plain alert() becomes a sweet alert too. Icon guessed by wording. */
  function guessType(m) {
    m = String(m);
    if (/success|saved|approved|done|complete|recorded/i.test(m)) return 'success';
    if (/fail|error|invalid|required|cannot|denied|rejected|missing|unable/i.test(m)) return 'error';
    return 'info';
  }
  window.alert = function (m) { swalAlert(m, guessType(m)); };

  /* Replaces the browser confirm() on a form submit: shows a sweet confirm,
     then submits the form if the user agrees. Attach with:
       <form onsubmit="return sweetConfirmSubmit(event,'Are you sure?')">
  */
  window.sweetConfirmSubmit = function (event, message) {
    event.preventDefault();
    var btn = event.currentTarget;
    var form = btn.form || btn.closest('form');
    if (!form) return false;
    swalConfirm(message, function () { form.submit(); });
    return false;
  };

  /* Generic required-field validation for ANY form/container: every
     input/select/textarea carrying the `required` attribute is checked;
     empty ones get a red border and one sweet alert lists what's missing.
     Returns true when all required fields are filled. */
  function swalValidateRequired(container) {
    var missing = [];
    var first = null;
    var fields = container.querySelectorAll('input[required], select[required], textarea[required]');
    for (var i = 0; i < fields.length; i++) {
      var el = fields[i];
      var val = (el.value || '').trim();
      var empty = val === '' || (el.type === 'number' && !(parseFloat(val) > 0));
      if (empty) {
        el.style.borderColor = '#a23232';
        el.addEventListener('input', function () { this.style.borderColor = ''; }, { once: true });
        var lab = '';
        var prev = el.previousElementSibling;
        if (prev && prev.tagName === 'LABEL') lab = prev.textContent;
        if (!lab) {
          var wrap = el.closest('.field, .form-group, .modal-row > div');
          var l = wrap && wrap.querySelector('label');
          if (l) lab = l.textContent;
        }
        lab = (lab || el.getAttribute('placeholder') || el.id || 'field').replace(/\s*\*\s*$/, '').trim();
        missing.push(lab);
        if (!first) first = el;
      } else {
        el.style.borderColor = '';
      }
    }
    if (missing.length) {
      swalAlert('Please fill in all required fields:\n• ' + missing.join('\n• '), 'error');
      if (first) first.focus();
      return false;
    }
    return true;
  }

  window.swalAlert = swalAlert;
  window.swalConfirm = swalConfirm;
  window.swalAsk = swalAsk;
  window.swalValidateRequired = swalValidateRequired;
})();
