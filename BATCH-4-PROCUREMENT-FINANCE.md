# Batch 4 — Responsive Procurement & Finance

## Scope

This batch improves the existing Procurement, Supplier, Budget Approval, Price Approval, Budget Settings, Menu Cost, and Profitability views. It changes only responsive presentation and navigation cues; it does **not** change budget calculations, approval authority, request status, shipment processing, supplier data, or financial data.

## New shared assets

- `procurement-finance.css`
- `procurement-finance.js`

Enabled on:

- `inventory-manager.php`
- `finance-dashboard.php`
- `reports.php`

## Delivered improvements

### Procurement workflow context

Purchase Request and Finance Approval panels now show a responsive visual sequence:

1. Request
2. Finance review
3. Shipment
4. Quality check
5. Receive to stock

The current step differs by screen, helping managers and finance staff understand where each request sits. The indicator is informational only; it does not modify status transitions.

### Request and supplier forms

- Purchase request, supplier, shipment, price, and budget forms receive a consistent contained form layout.
- Inputs, selects, and textareas use touch-safe control heights.
- Quantity/unit and other two-column fields collapse to one column on narrow phones.
- The formal-letter preview and actions wrap safely on phones, avoiding layout overflow.

### Finance approval and budget UX

- Approval/decline controls remain grouped and readable.
- Budget summary rows align long currency values safely.
- Finance dashboard alerts, budget cards, and actions retain the existing coffee-brand style with improved mobile spacing.
- Existing approval confirmations and request documents remain unchanged.

### Mobile data records

The highest-density procurement/finance tables become labelled cards below 640px:

- Purchase requests
- Sellers
- Budget approvals and approval history
- Price approvals
- Budget-by-month records

Desktop/tablet tables remain tabular and maintain all existing columns.

## Validation performed

- JavaScript syntax checked for `responsive-ui.js`, `dashboard-navigation.js`, `inventory-ui.js`, and `procurement-finance.js`.
- Verified every Batch 4 CSS/JS reference resolves from the PHP pages that use it.
- The workspace does not include PHP, so run the included archive in XAMPP/Apache for full browser and server-side testing.

## Suggested test pass

At 1440px, 768px, 430px, and 360px, test:

1. Inventory Manager → Purchase Requests, Sellers, Shipment Tracking.
2. Finance Dashboard → Budget Approvals, Price Approvals, Budget Settings, Menu-Level Costs.
3. Approve/decline controls, price approval actions, monthly budget edit form, and formal letter preview.
4. Long supplier names, high peso amounts, and records with signature/security-photo attachments.
