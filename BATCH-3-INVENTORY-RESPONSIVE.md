# Batch 3 — Responsive Inventory and Stock Controls

## Scope

This batch improves the existing Inventory, Warehouse, Stocktake, Batch/Expiry, Returns, Reports, and POS Admin recipe-management screens. It is a presentation and usability update only: it does not change stock calculations, approval rules, form submissions, routes, or database logic.

## New assets

- `inventory-ui.css`
- `inventory-ui.js`

These assets are enabled on:

- `inventory.php`
- `inventory-manager.php`
- `inventory-clerk.php`
- `warehouse.php`
- `stocktake.php`
- `expiry.php`
- `returns.php`
- `reports.php`
- `pos-admin.php` (recipe builder)

## Delivered improvements

### Inventory dashboards

- Inventory manager and clerk quick actions are enhanced into responsive action cards.
- Low-stock / needs-attention entries are visually grouped as compact, readable alerts.
- At narrow widths, action cards change from a two-column grid to an icon-and-label vertical list.
- Dashboard panels, search/filter controls, operational tables, and pagination remain within the viewport.

### Stock movement, receiving, transfer, return and batch forms

- Operational forms receive a consistent contained surface and spacing.
- Controls use touch-safe heights and visible focus/filled states.
- Two-column quantity/unit fields collapse into one column on narrow phones.
- Stock-in/out/remove radio controls work as a responsive segmented control.
- Long operational forms remain readable without making desktop forms unnecessarily large.

### Stock quantities and data tables

- Existing wide tables continue to horizontally scroll rather than losing data.
- The **Current Stocks** and **Warehouse Stock** tables become labelled record cards below 640px, so quantities and status are readable without horizontal scrolling.
- Table controls and inline action buttons remain touch-accessible.

### Recipe editor

- POS Admin recipe ingredient rows have a clearer contained layout.
- On narrow phones, each recipe row becomes a grid that keeps the ingredient selector, quantity, and remove action usable.
- Existing recipe add/edit logic is untouched.

## Validation performed

- `inventory-ui.js`, `responsive-ui.js`, and `dashboard-navigation.js` passed Node syntax checks.
- Verified every Batch 3 CSS/JS reference resolves from its corresponding PHP screen.
- PHP is not installed in this workspace, so test the archive through XAMPP/Apache before deploying it.

## Suggested visual test pass

Test Manager Dashboard, Clerk Movement entry, Current Stocks, Warehouse Transfer, Stocktake, Batch/Expiry, Supplier Return, and POS Admin Recipe Builder at 1440px, 768px, 430px, and 360px widths.
