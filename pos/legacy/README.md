# Retired root POS pages

The original source contained a second, older POS implementation in the
Inventory root (`pos-client.php`, `pos-cashier.php`, and `pos-admin.php`).

Brew & Co. now uses the modern application in the parent `pos/` directory as
its one active POS. Normal root login, dashboard and POS entry points route to
`pos/index.php` / `pos/pos.php` / `pos/kiosk.php`.

The three legacy pages are retained here only as historical source reference;
they are not linked by normal navigation. Old root URLs are redirected to the
modern POS from the root `.htaccess` file.
