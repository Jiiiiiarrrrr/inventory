# Batch 6 — Responsive POS

## Scope

This batch improves the responsive experience for product browsing, cart/order review, kiosk ordering, checkout surfaces, cashier queues, POS navigation, and menu/recipe administration. It does **not** change menu prices, tax calculation, stock checks, payment collection, order creation, cancellation, or POS APIs.

## New shared assets

- `pos-responsive.css`
- `pos-responsive.js`

Enabled on:

- `pos/index.php`
- `pos/pos.php`
- `pos/kiosk.php`
- `pos-client.php`
- `pos-cashier.php`
- `pos-admin.php`

## Delivered improvements

### Product browsing and menu controls

- Category chips remain horizontally scrollable rather than wrapping into unusable rows on small screens.
- POS product, kiosk product, and legacy client-order cards retain tap-safe sizes.
- Product cards that have existing click actions become keyboard accessible with Enter and Space.
- Dynamic product grids are observed so newly rendered items receive the same keyboard support.

### Cart and checkout access

- Cart/order summaries have clearer responsive borders, totals, item spacing, quantity controls, and checkout button heights.
- A mobile **View order** shortcut is added for POS browse screens where the cart can sit below the products.
- The shortcut scrolls to the existing cart; it does not create a second cart or duplicate checkout behavior.
- Kiosk cart controls are left intact and receive safer small-screen spacing.

### Cashier and order queue

- Incoming-order tickets become more resilient at narrow widths.
- Cash received field and Pay button wrap into a usable layout on phones.
- Ticket controls retain their original payment/cancel form submissions.

### POS app navigation and menus

- POS top navigation is safe to scroll and wrap on small screens.
- POS sidebar apps receive mobile-safe dimensions and content spacing.
- Navigation links receive selected/`aria-current` state when used.
- Existing role visibility remains managed by the current POS JavaScript and backend rules.

### POS admin recipes

- Recipe ingredient rows retain a usable ingredient selector, quantity field, and remove button on narrow phones.
- Existing add/edit recipe logic stays unchanged.

## Validation performed

- JavaScript syntax checked for all six shared UI scripts.
- Verified all 12 Batch 6 CSS/JS paths from the PHP screens using them.
- PHP is not installed in this workspace, so run the included archive under XAMPP/Apache for complete UI and checkout testing.

## Suggested test pass

At 1440px, 768px, 430px, and 360px test:

1. POS customer browse, category filters, product selection, quantity controls, cart shortcut, and checkout modal.
2. Self-order kiosk category rail, product grid, cart toggle, order-type modal, and confirmation ticket.
3. Legacy client order page and cashier payment queue.
4. POS sidebar navigation and top-navbar role views.
5. POS Admin menu item and recipe add/edit forms.
