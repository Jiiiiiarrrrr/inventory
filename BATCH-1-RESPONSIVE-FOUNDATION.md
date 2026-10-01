# Batch 1 — Responsive UI Foundation

## Delivered

This batch adds a shared responsive layer to the existing Brew & Co. Inventory, POS, and HRMS interface without changing the database, API endpoints, roles, or business workflows.

### New shared assets

- `responsive-ui.css`
- `responsive-ui.js`

The assets are linked from the full-page Inventory, POS, and HRMS screens, including dynamically loaded HRMS modules that can also be opened directly.

## Responsive behaviours

- **Mobile navigation:** Sidebar screens retain the existing navigation structure. On small screens the sidebar fits within the viewport, opens as an overlay, and can be closed with the backdrop, navigation choice, or Escape key.
- **Tablet layouts:** Common metric cards and content grids reflow into two columns where space allows.
- **Phone layouts:** Common dashboards, forms, stat grids, cards, action toolbars, and dialogs collapse safely into one column.
- **Data tables:** Tables are wrapped automatically in keyboard-focusable, horizontally scrollable regions rather than being squeezed into unreadable columns.
- **Forms and actions:** Controls use a minimum 44px touch target, action rows wrap, inputs are width-safe, and textareas stay resizable.
- **Overflow safety:** Long names, codes, emails, and dynamic content wrap without widening the page; images and media scale to their container.
- **Accessibility:** Visible keyboard focus, Escape-to-close navigation, ARIA state updates on navigation toggles, safe viewport sizing, and reduced-motion support.
- **Print mode:** Mobile-only navigation elements do not appear when printing.

## Coverage

The foundation stylesheet is linked from 55 full-page PHP files across:

- Inventory dashboards, warehouse, reports, stocktake, returns, audit, user management, login, and hub
- POS admin, cashier, client, staff POS, kiosk, and landing screens
- HRMS login, dashboard shells, applicant pages, finance pages, and module views

## Compatibility notes

- This batch is additive. It does not remove existing inline styles or alter PHP/SQL logic.
- Existing module-specific breakpoints remain in place; the shared layer supplies common safe dimensions and fallbacks.
- The workspace does not include a PHP runtime, so PHP pages could not be rendered here. The JavaScript was syntax-checked with Node, and all injected CSS/JS file paths were verified.

## Local test checklist

After copying the updated `inventory` folder to XAMPP/your PHP host, verify these widths in browser dev tools:

- 1440px desktop
- 1024px tablet landscape
- 768px tablet portrait
- 430px mobile
- 360px small mobile

Check at least one Inventory dashboard, Warehouse table, POS checkout, HRMS dashboard, HRMS payroll table, a form modal, and the login pages. Test the sidebar open/close control and confirm forms, tables, and dialogs remain usable.
