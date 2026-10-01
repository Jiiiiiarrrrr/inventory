# Brew & Co. — Inventory Management System

A complete PHP + MySQL inventory management system for a coffee shop, with role-based
dashboards for **Clerk**, **Manager**, **Finance**, and **Superadmin**.

## ✨ Features

| Area | What you can do |
|------|-----------------|
| **Login & Roles** | Clerk, Manager, Finance, Superadmin log in to their own dashboard |
| **Stock movements** | Clerk records Stock In / Out / Reduce / Remove (updates quantities automatically) |
| **Item management** | Manager adds, edits & deactivates stock items and categories |
| **Item costing** | Each item has a unit cost; system shows stock value & ingredient-level COGS |
| **Stocktaking** | Manager runs physical counts; differences are applied as adjustments |
| **Goods receiving (GRN)** | Receiving a shipment records qty, flags shortages/overages & auto-adds stock |
| **Auto-reorder** | Manager generates purchase requests for all low-stock items in one click |
| **Low-stock alerts** | Manager emails a low-stock alert from the dashboard |
| **CSV import/export** | Export items, movements & low-stock; import items |
| **Landing dashboard** | Overview with charts & role quick-links (index.php) |
| **Purchase request details** | View a PR's full lifecycle |
| **Batch & expiry (FEFO)** | Track expiry dates on lots, use nearest-to-expire-first, expiry alerts |
| **Returns to supplier** | Return defective/expired goods for credit (auto-reduces stock) |
| **Supplier performance** | On-time delivery & rejection rate computed from real data |
| **Print / PDF** | Print-ready count sheets & reports |
| **Sellers** | Manager adds, edits & deactivates suppliers |
| **Procurement** | Manager creates Purchase Requests → Finance approves/declines against budget |
| **Shipments** | Manager tracks In transit → Out for delivery → Delivered (auto-creates QC on delivery) |
| **Quality Check** | Manager passes/fails received goods |
| **Budget** | Finance sets/edits monthly procurement budgets |
| **Menu costs** | Finance adds/edits menu items; revenue & COGS computed live |
| **Reports** | Charts, low-stock export (CSV), profitability — Manager & Finance |
| **User management** | Superadmin creates/edits users, resets passwords, toggles access |
| **Audit trail** | Superadmin sees every action (who/when/IP), filterable + CSV export |
| **Backup & restore** | Superadmin downloads/restores full database backups |

## 🗂️ Files

```
index.php              Landing page — overview charts & role quick-links
login.php              Login page (routes by role) + remember me
logout.php             Logs out (with confirmation)
db.php                 Shared DB connection + helpers (audit, transactions, tokens)
inventory-clerk.php    Clerk dashboard
inventory-manager.php  Manager dashboard
finance-dashboard.php  Finance dashboard
user-management.php    Superadmin — user accounts
audit-trail.php        Superadmin — activity log
reports.php            Manager/Finance — insights & CSV exports
backup.php             Superadmin — DB backup/restore
stocktake.php          Manager — physical stock counts & adjustments
expiry.php             Manager — batch & expiry (FEFO) tracking
returns.php            Manager — returns to supplier
warehouse.php          Manager — bulk receiving, warehouse storage, transfers & clerk stock-request approvals
pos-client.php         POS — client kiosk (order screen, public)
pos-cashier.php        POS — cashier (finalize payment, deduct ingredients from stock)
pos-admin.php          POS — admin (manage menu items, categories & recipes)
brewco_inventory.sql   Database schema + base accounts
```

## 📦 Requirements

- XAMPP (Apache + MySQL/MariaDB) or any PHP 8.0+ + MySQL setup
- No external libraries/CDNs needed — charts & styling are built-in

## 🚀 Setup (XAMPP) — step by step

1. **Start XAMPP** and turn on **Apache** and **MySQL** (green arrows).

2. **Create the database**
   - Open **phpMyAdmin** at `http://localhost/phpmyadmin`.
   - Click **Import**, choose **`brewco_inventory.sql`**, and click **Go**.

3. **Put the files in the right folder**
   - Copy all the `.php` files (and the SQL files) into:
     `C:\xampp\htdocs\INVENTORY\`
   - Keep them together in **one** folder.

4. **Check the DB connection** (`db.php`)
   - It defaults to `host=localhost`, `user=root`, empty password, database `brewco_inventory`.
   - This matches XAMPP's defaults, so it should just work.
   - If you use a different MySQL user/password, edit the `DB_*` constants at the
     top of `db.php`.

5. **Open the site**
   - Visit **`http://localhost/INVENTORY/`** in your browser. It will take you to
     the login page (or straight to your dashboard if you're signed in).
   - ⚠️ Always use `http://localhost/...`, **never** double-click the file (that
     opens it as code, not the site).

## 🔐 Roles & access

| Page | Clerk | Manager | Finance | Superadmin |
|------|:---:|:---:|:---:|:---:|
| Inventory Clerk | ✅ | — | — | ✅ |
| Inventory Manager | — | ✅ | — | ✅ |
| Finance Dashboard | — | — | ✅ | ✅ |
| Reports | — | ✅ | ✅ | ✅ |
| User Management | — | — | — | ✅ |
| Audit Trail | — | — | — | ✅ |
| Backup & Restore | — | — | — | ✅ |

## 💾 Backups

As **superadmin**, open **Backup & Restore** in the sidebar:
- **Download Backup** — saves the whole database as a `.sql` file.
- **Restore** — upload a previous `.sql` backup to bring data back.
- Take a backup before major changes, and keep copies off the machine.

## 🧭 How the workflow fits together

```
Clerk records Stock Out  →  Low stock alert
Manager creates Purchase Request  →  Finance approves (deducts budget)
Shipment created  →  In transit  →  Out for delivery  →  Delivered
Delivered auto-creates a Quality Check  →  Manager passes it
Clerk records Stock In (restock)  →  Stock is healthy again
```

## 🛡️ Robustness & safety (built-in)

- **Duplicate-submit / CSRF guard** — every form carries a hidden token; forged or
  double-submitted forms are rejected.
- **Negative-stock prevention** — you can't record more stock out/reduce than you have.
- **Transactions** — multi-step writes (stock movements, GRN, stocktake, reorders)
  are atomic: either all steps succeed or none do.
- **Clear error messages** — instead of a generic "something went wrong", you get a
  specific reason (e.g. "Insufficient stock — only 42 available").
- **Confirmation dialogs** — deactivating, finalizing, and logging out ask for confirmation.
- **Forgot password** — token-based reset. In this demo the reset link is shown on
  screen; in production you'd email it instead.
- **Login rate-limit / lockout** — after a few failed attempts, that email/IP is
  blocked for several minutes.
- **Session hardening** — HTTP-only + SameSite cookies, idle session timeout.
- **DB credentials in config** — edit the `DB_*` constants at the top of `db.php`.

## 🛠️ Common issues

- **Login shows demo/dummy data instead of the DB** → the app can't reach MySQL.
  Start MySQL in XAMPP and confirm the DB exists (phpMyAdmin shows `brewco_inventory`).
- **Page shows raw code** → you opened the file directly. Use `http://localhost/...`.
- **Import error `#1451 foreign key`** → you re-imported `brewco_inventory.sql`
  without resetting. The script is re-runnable now (drops tables safely), so just
  re-import it again; it will rebuild cleanly.
- **Change the DB password** → update `db.php`.

---

© Brew & Co. Inventory System — built with PHP & MySQL.
