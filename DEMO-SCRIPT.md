# ☕ Brew & Co. Inventory — Live Demo Script

A quick, presenter-friendly walkthrough of the whole system. Everything runs offline on your XAMPP.

---

## 🚀 Before you start (1 min)

1. Open **XAMPP Control Panel** → Start **Apache** + **MySQL** (both green).
2. Open browser → **`http://localhost/INVENTORY/`**
3. If you're not logged in, you'll see the login page. If the page ever shows raw code, use `http://localhost/...` (never double-click the file).

> 💾 **Pro tip:** as superadmin, open **Backup & Restore** → download a backup first. If anything goes wrong mid-demo, restore it and you're back to a clean state.

---

## 🔑 The 4 demo accounts

| Role | Email | Password | Lands on |
|------|-------|----------|----------|
| Clerk | `jenwin@brewco.ph` | `password123` | Inventory Clerk |
| Manager | `jayr@brewco.ph` | `password123` | Inventory Manager |
| Finance | `lavish@brewco.ph` | `password123` | Finance Dashboard |
| Superadmin | `admin@brewco.ph` | `admin123` | User Management |

Type the email & password manually on the login page (there are no auto-fill chips).

---

## 🧍 Part 1 — CLERK (jenwin)
*"This is the person on the floor recording stock day-to-day."*

1. **Dashboard** — overview cards (total items, low stock, movements today) + "Needs Attention" panel.
2. **Stock In / Out** → record a Stock Out (e.g. Fresh Milk). Show the **negative-stock guard**: try to remove more than available and it blocks with a clear message.
3. **Current Stocks** — live quantities, search & filter.
4. **Stock Logs** — full history with search + pagination.
   - **Highlight:** every movement auto-updates quantity and is logged with who/when.
5. Log out → click the sweet **"Log out of your session?"** popup.

---

## 🧑‍💼 Part 2 — MANAGER (jayr)
*"This is the person who keeps the shop supplied."*

1. **Dashboard** — low/out-of-stock panel with **suggested order qty**, pending approvals.
2. **Procurement** → create a **Purchase Request**. Show the new **Details** button (lifecycle).
3. **Items & Categories** — add an item (with **unit cost**); note the **Total Stock Value**.
4. **Seller Contacts** — show **on-time delivery %** and **rejection %** per supplier.
5. **Shipment Tracking** → add a shipment → advance to **Delivered** → click **Receive** (GRN). Highlight that receiving **auto-adds to stock** and flags shortages.
6. **Batch & Expiry (FEFO)** → add a lot with a near expiry date → show the **"Expiring within 14 days"** warning.
7. **Returns to Supplier** → record a return; stock reduces automatically.
8. **Stocktaking** → start a count → enter a physical count → **Print count sheet** → Finalize (applies the adjustment).
9. **Auto-reorder** → one click generates PRs for all low items.
10. **Reports** → charts, stock value, COGS, and **Export CSV**.

---

## 💰 Part 3 — FINANCE (lavish)
*"This person controls the money."*

1. **Dashboard** — revenue, COGS, gross profit, budget remaining bar.
2. **Budget Approvals** → approve a pending PR (deducts budget) or decline it.
3. **Budget Settings** → set/edit a monthly budget.
4. **Menu-Level Costs** → add/edit a menu item; margins recompute live.
5. **Reports** → profitability view.

---

## 🛡️ Part 4 — SUPERADMIN (admin)
*"The person who runs the whole system."*

1. **User Management** → add a user, reset a password, toggle access (show the role guard: a Clerk **can't** open this page).
2. **Audit Trail** → filter by user/action, **Export CSV** — show that everything is logged.
3. **Backup & Restore** → download a backup (and mention you can restore it).

---

## 🎤 Suggested "wow" talking points

- **It's all offline** — "Everything runs locally, no internet or hosting needed."
- **End-to-end flow** — "A product goes: low stock → auto-reorder → finance approval → shipment → receive (GRN) → quality check → back in stock."
- **Inventory is tied to money** — "Each item has a cost, so we see real stock value and ingredient COGS."
- **It protects the business** — "Stocktakes catch shrinkage, returns handle defective goods, expiry/FEFO reduces waste, and the audit trail logs everything."
- **It's secure** — "Password hashing, login lockout, CSRF tokens, role-based access."

---

## ⚠️ Gotchas during a live demo

- **Email alerts** (low-stock email) need mail/SMTP setup — skip or note it. Everything else works.
- **Login lockout:** after 5 wrong password attempts, that login is blocked ~10 min. Don't spam wrong passwords live!
- **Data gets changed as you demo** — that's normal. Use the superadmin **backup/restore** to reset if needed.

---

## ⏱️ Suggested pacing (if needed)

- 60–90 seconds per role
- End on **Superadmin → Backup** so it feels like a safe, finished product.
