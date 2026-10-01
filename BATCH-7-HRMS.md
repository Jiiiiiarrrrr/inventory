# Batch 7 — Responsive HRMS

## Scope

This batch makes the active HRMS workflows more reliable on phones and tablets while retaining Brew & Co.’s existing cream, espresso, and warm-gold visual language. It is additive: it only loads `hrms-responsive.css` and `hrms-responsive.js` alongside the existing shared assets.

It does **not** modify HRMS API endpoints, session/role checks, applicant/employee records, attendance calculations, request approvals, schedule approvals, signatures, uploaded files, or finance data.

## New shared assets

- `hrms-responsive.css`
- `hrms-responsive.js`

## Covered interfaces

### Role shells and account journeys

- HR Admin shell: `hrms/index.php`
- Superadmin shell: `hrms/indexx.php`
- Staff shell: `hrms/indexxx.php`
- HRMS login and password-change screens
- Public application form, applicant portal, and application-status screen

### HR administration

- Dashboards, applicants, employees, archive, audit logs, notifications, profiles, and staff-account creation
- Employee schedules and superadmin schedule approval
- Attendance, attendance summary, and request/approval interfaces
- HRMS finance role, initial finance setup, budget, and finance dashboard screens

The focused payroll/payslip interfaces remain reserved for Batch 8.

## Delivered responsive behaviour

### Role navigation and workspace shells

- Existing off-canvas sidebars retain their own open/close logic, while gaining safe-area padding and mobile-safe menu placement.
- HRMS iframe workspaces keep a full dynamic viewport height without creating horizontal overflow.
- Sidebar navigation receives `aria-current` state when a destination is selected.

### Forms, requests, schedules, attendance, and recruitment

- Inputs, selects, textareas, and action buttons are constrained to their containers and remain tap-safe.
- Compact control bars, filters, form rows, schedule layouts, profile grids, dashboard grids, and signature areas stack cleanly on tablets and phones.
- Request details, applicant detail/offer dialogs, schedule dialogs, and signature dialogs use viewport-safe sizing and can scroll internally when required.
- Signature pads, applicant e-sign surfaces, document previews, file names, and long HR data wrap safely rather than overflowing.

### Employee, applicant, and request records

- Dynamic HRMS tables now receive field labels after their existing JavaScript loads data.
- Tables with seven or fewer fields transform into labelled mobile record cards below 640px.
- Wider attendance, schedule, and audit-report tables intentionally retain horizontal scrolling so no time, image, approval, or status column is hidden.
- The responsive script rechecks dynamically injected rows after API responses; it does not alter their content or actions.

### Finance budget role screens

- HRMS finance/budget surfaces receive the same responsive form, card, control-bar, modal, and data-wrap safeguards.
- No payroll calculation, payment, payslip, or budget approval business logic changed.

## Validation performed

- `node --check hrms-responsive.js` passed.
- Rechecked syntax for all Batch 1–7 shared JavaScript assets; all passed.
- Verified the 56 HRMS CSS/JS references across 28 covered PHP interfaces resolve to actual files.
- PHP is not installed in this workspace, so server-side PHP linting and authenticated end-to-end runtime testing must be performed in the destination XAMPP/Apache/MySQL environment.

## Suggested browser test pass

Test at 1440px, 768px, 430px, and 360px:

1. Log in as HR Admin, Superadmin, Staff, Finance, and Applicant; open and close each sidebar.
2. Search employee records; verify employee data remains visible in mobile cards.
3. Create, review, approve/reject, and view a request with its formal letter and signature dialog.
4. Review staff scheduling and superadmin schedule approval; horizontally scroll the full schedule table where applicable.
5. Clock attendance and review attendance summary, including time-in/time-out images.
6. Review applicants, applicant details, offer dialog, public application form, and applicant e-sign status.
7. Test notifications, profile/password, archive/log screens, and finance budget/dashboard forms.
