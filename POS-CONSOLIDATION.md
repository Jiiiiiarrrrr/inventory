# POS consolidation

## Why there appeared to be two POS systems

The original Brew & Co. source contained two separate POS implementations:

1. **Modern POS — active system**: `pos/index.php`, `pos/pos.php`, and `pos/kiosk.php`, with its own POS APIs and staff/customer flows.
2. **Legacy POS — retired source**: `pos-client.php`, `pos-cashier.php`, and `pos-admin.php` in the Inventory root.

The root files are an older prototype path and are not the POS system used by the modern `/pos/` interface.

## Consolidation performed

- The active, canonical POS is now the `pos/` folder.
- Root login and Inventory role routing for Admin, Cashier, Barista, and Cleaner now open `pos/index.php`.
- The modern POS customer option now opens its own `pos/kiosk.php` rather than the legacy root client page.
- The root legacy pages were moved to `pos/legacy/` as source reference only:
  - `pos/legacy/client.php`
  - `pos/legacy/cashier.php`
  - `pos/legacy/admin.php`
- There are no longer `pos-client.php`, `pos-cashier.php`, or `pos-admin.php` files in the Inventory root.
- Old root URLs redirect to the modern POS through `.htaccess` compatibility rules:
  - `pos-client.php` → modern kiosk
  - `pos-cashier.php` → modern POS landing
  - `pos-admin.php` → modern POS landing

## Result

Use these active pages going forward:

- `http://localhost/INVENTORY/pos/` — POS landing / staff sign-in
- `http://localhost/INVENTORY/pos/pos` — POS staff/customer workspace
- `http://localhost/INVENTORY/pos/kiosk` — self-order kiosk

The legacy code is not used by normal navigation and is retained only to avoid losing the original source history.
