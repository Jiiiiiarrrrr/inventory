/* logout-confirm.js — intercept logout links with a sweet confirm dialog. */
(function () {
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href="logout.php"]') : null;
    if (!a) return;
    e.preventDefault();
    swalConfirm('Log out of your session?', function () { window.location.href = 'logout.php'; });
  });
})();