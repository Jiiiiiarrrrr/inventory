# Batch 2 — Dashboard and role navigation

## Goal

Polish the responsive dashboard shell and navigation across the existing Inventory, Finance, HRMS, and POS interfaces while preserving the current role checks, PHP routes, forms, and API behavior.

## New shared assets

- `dashboard-navigation.css`
- `dashboard-navigation.js`

These are loaded after the Batch 1 responsive foundation on 55 full-page PHP screens, including the HRMS module pages shown in the iframe.

## Delivered improvements

### Role-aware workspace context

Sidebar-based workspaces now receive a small contextual marker generated in the browser:

- Inventory workspace
- Finance workspace
- HRMS & Payroll
- Point of Sale

It does not read, write, or change user roles. It only helps the user understand which part of the system they are currently using.

### System navigation

- Sidebar workspaces include an **All Brew & Co. systems** shortcut back to the central hub.
- The currently open route is visually marked where the menu uses ordinary links.
- Existing active navigation states are retained.
- Navigation entries receive accessible titles, and the existing mobile sidebar closes after a navigation choice.
- The active navigation item is kept in view on desktop-side navigation.

### Dashboard hierarchy

- Dashboard header regions now have clearer separation.
- Summary/stat cards have a consistent top accent, border, shadow, and number hierarchy.
- Existing dashboard panels have a more consistent surface treatment.
- User identity blocks in dashboard headers have a compact, readable container.
- The original coffee-brand colours and module-specific styles remain intact.

### POS navigation

- POS navigation links scroll horizontally instead of overflowing at smaller widths.
- The POS header wraps into an intentional three-row arrangement on mobile: brand/account, then navigation.
- Active POS menu items have a visible selected state.

### Portal

- The system portal receives improved card sizing and keyboard-focus visibility.
- Portal cards remain compact on small screens while retaining an equal visual rhythm on desktop.

## Validation performed

- JavaScript syntax checked with Node for both shared UI scripts.
- All 110 CSS/JavaScript Batch 2 references were resolved and verified from their PHP file locations.
- PHP could not be runtime-rendered in this workspace because PHP is not installed here. Test the supplied archive under XAMPP/Apache before deployment.

## Suggested visual test pass

At 1440px, 1024px, 768px, 430px, and 360px, check:

1. Inventory manager/clerk and Finance dashboard sidebar context and system shortcut.
2. HRMS admin/superadmin/finance shell navigation and iframe module loading.
3. POS navbar scrolling/wrapping and active menu links.
4. Dashboard statistic cards and page headers.
5. Mobile sidebar close behaviour after selecting a menu item.
