<?php
// POS Orders API — uses pos_sales + pos_sale_items tables, joined against menu_items
// (pos_sale_items.item_id is FK'd to menu_items.id).
//
// Two order-entry surfaces feed this same table:
//   - Kiosk (customer self-order): creates the order as Pending, no payment yet.
//     The Cashier collects payment later.
//   - Cashier / Order Station: either collects payment for a Pending kiosk order
//     (see collectPayment), or places+pays an order in one step (still supported,
//     for walk-up customers who don't use the kiosk) — in that case the order is
//     created directly as Paid.
//
// Stock is deducted as soon as an order is created — Pending or Paid — so items
// are reserved the moment a customer checks out, and can't be sold out from
// under them while they're still walking to the counter to pay. Voiding an
// order (Pending or Paid) restores that stock. menu_items.sold, unlike stock,
// is only ever incremented once an order actually becomes Paid — that's a
// sales-report figure, not a stock figure.
//
// NOTE: this does NOT touch raw ingredient stock (items.current_qty / menu_ingredients) —
// that will instead come from an external inventory system being integrated later.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/recipe-stock.php';

// Idempotent migration: remember WHEN an order was actually paid, so daily
// sales report by payment date (an order placed 11pm and paid 12:05am belongs
// to the day the money was collected, not the day it was typed in).
function pos_ensure_paid_at($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $col = $conn->query("SHOW COLUMNS FROM pos_sales LIKE 'paid_at'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE pos_sales ADD COLUMN paid_at DATETIME NULL AFTER created_at");
        $conn->query("UPDATE pos_sales SET paid_at = created_at WHERE status = 'Paid' AND paid_at IS NULL");
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// The Kiosk itself is unauthenticated (customers don't log in), so creating a
// Pending order must stay open. Every other action here is Cashier/Admin POS work.
if (!($method === 'POST' && $action !== 'collect_payment')) {
    requireRole(['Cashier', 'Admin']);
}

switch ($method) {
    case 'GET':
        if ($action === 'daily_summary') getDailySummary();
        elseif ($action === 'pending') getPendingOrders();
        else getOrders();
        break;
    case 'POST':
        if ($action === 'collect_payment') collectPayment();
        else createOrder();
        break;
    case 'PUT':    updateOrderStatus(); break;
    default: echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Increment menu_items.sold for each item in a finalized (Paid) order.
 * Uses sold_counted flag to avoid double-counting if called multiple times.
 */
function countMenuSales($conn, $orderId) {
    $chk = $conn->prepare('SELECT sold_counted FROM pos_sales WHERE id = ?');
    $chk->bind_param('i', $orderId);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row || !empty($row['sold_counted'])) return;

    $stmt = $conn->prepare('SELECT item_id, qty FROM pos_sale_items WHERE order_id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        $qty = (int)$item['qty'];
        $itemId = (int)$item['item_id'];
        $upd = $conn->prepare("UPDATE menu_items SET sold = sold + ? WHERE id = ? AND is_active = 1");
        $upd->bind_param('ii', $qty, $itemId);
        if (!$upd->execute()) {
            throw new Exception('Failed to update sold count: ' . $upd->error);
        }
    }

    $mark = $conn->prepare('UPDATE pos_sales SET sold_counted = 1 WHERE id = ?');
    $mark->bind_param('i', $orderId);
    if (!$mark->execute()) {
        throw new Exception('Failed to mark order as counted: ' . $mark->error);
    }
}

/**
 * Check stock for every line item already recorded against $orderId, then deduct
 * it. Called once per order, at the moment it's first created (Pending or Paid) —
 * never again at collectPayment(), since that would double-deduct. Throws an
 * Exception (caller should roll back) if stock is short.
 */
function deductStockForOrder($conn, $orderId) {
    $itemsStmt = $conn->prepare('SELECT item_id, qty FROM pos_sale_items WHERE order_id = ?');
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $lines = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $neededByItem = [];
    foreach ($lines as $line) {
        $itemId = (int)$line['item_id'];
        $neededByItem[$itemId] = ($neededByItem[$itemId] ?? 0) + (int)$line['qty'];
    }

    // First pass: lock rows and verify enough stock exists for everything.
    foreach ($neededByItem as $itemId => $neededQty) {
        $stockStmt = $conn->prepare("SELECT id, name, stock FROM menu_items WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stockStmt->bind_param('i', $itemId);
        $stockStmt->execute();
        $menuItem = $stockStmt->get_result()->fetch_assoc();

        if (!$menuItem) {
            throw new Exception("Item not found or inactive (id {$itemId}).");
        }
        if ((int)$menuItem['stock'] < $neededQty) {
            throw new Exception("Not enough stock for {$menuItem['name']}: need {$neededQty}, only have {$menuItem['stock']}.");
        }
    }

    // Second pass: deduct.
    $deductStmt = $conn->prepare('UPDATE menu_items SET stock = stock - ? WHERE id = ?');
    foreach ($neededByItem as $itemId => $neededQty) {
        $deductStmt->bind_param('ii', $neededQty, $itemId);
        if (!$deductStmt->execute()) {
            throw new Exception('Failed to deduct stock: ' . $deductStmt->error);
        }
    }
}

function getOrders() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    $date = $_GET['date'] ?? '';
    $statusFilter = $_GET['status'] ?? '';
    $sql = "SELECT s.*, GROUP_CONCAT(CONCAT(i.name, ' x', si.qty) SEPARATOR ', ') AS items_summary
            FROM pos_sales s
            LEFT JOIN pos_sale_items si ON s.id = si.order_id
            LEFT JOIN menu_items i ON si.item_id = i.id";
    $params = []; $types = ''; $where = [];
    if ($date) { $where[] = "DATE(s.created_at) = ?"; $params[] = $date; $types .= 's'; }
    if ($statusFilter) { $where[] = "s.status = ?"; $params[] = $statusFilter; $types .= 's'; }
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $bindParams = [$types];
        foreach ($params as $key => $value) { $bindParams[] = &$params[$key]; }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = array_sum(array_map(function($o) { return floatval($o['total']); }, $orders));

    echo json_encode(['success' => true, 'data' => $orders, 'daily_total' => $total]);
    $conn->close();
}

/**
 * Orders placed on the Kiosk, awaiting payment at the Cashier. Oldest first so
 * the queue reads top-to-bottom in the order customers should be served.
 */
function getPendingOrders() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    $stmt = $conn->prepare("SELECT s.*, GROUP_CONCAT(CONCAT(i.name, ' x', si.qty) SEPARATOR ', ') AS items_summary
            FROM pos_sales s
            LEFT JOIN pos_sale_items si ON s.id = si.order_id
            LEFT JOIN menu_items i ON si.item_id = i.id
            WHERE s.status = 'Pending'
            GROUP BY s.id ORDER BY s.created_at ASC");
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'data' => $orders]);
    $conn->close();
}

function getDailySummary() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    pos_ensure_paid_at($conn);

    $summary = $conn->query("SELECT
        COUNT(DISTINCT s.id) AS order_count,
        COALESCE(SUM(s.total), 0) AS revenue,
        COALESCE(SUM(si.qty * mi.cost), 0) AS cost
        FROM pos_sales s
        LEFT JOIN pos_sale_items si ON s.id = si.order_id
        LEFT JOIN menu_items mi ON si.item_id = mi.id
        WHERE s.status = 'Paid' AND DATE(COALESCE(s.paid_at, s.created_at)) = CURDATE()")->fetch_assoc();

    $stmt = $conn->prepare("SELECT mi.name, mi.stock, SUM(si.qty) AS quantity, SUM(si.qty * si.unit_price) AS revenue
        FROM pos_sales s
        JOIN pos_sale_items si ON s.id = si.order_id
        JOIN menu_items mi ON si.item_id = mi.id
        WHERE s.status = 'Paid' AND DATE(COALESCE(s.paid_at, s.created_at)) = CURDATE()
        GROUP BY mi.id, mi.name, mi.stock
        ORDER BY quantity DESC, mi.name ASC");
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $revenue = (float)$summary['revenue'];
    $cost = (float)$summary['cost'];
    echo json_encode([
        'success' => true,
        'data' => [
            'date' => date('Y-m-d'),
            'order_count' => (int)$summary['order_count'],
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'items' => $items,
        ],
    ]);
    $conn->close();
}

/**
 * Create an order. Both shapes deduct stock immediately, since the items are
 * reserved the moment the order is placed:
 *   - cash_received present: Cashier is paying right now -> created as Paid,
 *     stock deducted, sold counted immediately.
 *   - cash_received absent: Kiosk self-order -> created as Pending, stock
 *     deducted now too. The Cashier finalizes payment later via
 *     collectPayment(), which only flips the status — stock was already taken.
 */
function createOrder() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    $data = json_decode(file_get_contents('php://input'), true);
    $cartItems = $data['items'] ?? [];
    $orderType = $data['order_type'] ?? 'Dine In';
    $payment = $data['payment'] ?? 'Cash';
    $cashReceived = isset($data['cash_received']) ? (float)$data['cash_received'] : null;

    if (empty($cartItems)) { echo json_encode(['success' => false, 'message' => 'No items in order.']); return; }

    // SERVER-SIDE PRICE AUTHORITY: never trust prices sent by the client (a
    // tampered kiosk/POS request could otherwise order a ₱180 latte for ₱1).
    // Re-read every line's price from menu_items, reject unknown / inactive /
    // deleted items and non-positive quantities, then compute the total here.
    if (!in_array($orderType, ['Dine In', 'Take Out'], true)) $orderType = 'Dine In';
    if (!in_array($payment, ['Cash', 'Pay at Cashier'], true)) $payment = 'Cash';
    $priceById = [];
    foreach ($cartItems as $item) {
        $itemId = (int)($item['id'] ?? 0);
        $qty    = (int)($item['qty'] ?? 0);
        if ($itemId <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item or quantity in order.']); return;
        }
        if (!isset($priceById[$itemId])) {
            $pst = $conn->prepare('SELECT price FROM menu_items WHERE id = ? AND is_active = 1 AND deleted_at IS NULL');
            $pst->bind_param('i', $itemId);
            $pst->execute();
            $prow = $pst->get_result()->fetch_assoc();
            if (!$prow) {
                echo json_encode(['success' => false, 'message' => 'Item is no longer available on the menu.']); return;
            }
            $priceById[$itemId] = (float)$prow['price'];
        }
    }

    // Subtotal from the cart, then 12% VAT — same formula the kiosk/POS ticket
    // already displays to the customer. Previously the frontend computed and
    // *showed* subtotal+VAT on the ticket, but only ever sent the bare item
    // price/qty here, so the backend stored subtotal-only as the order's total.
    // Every order was silently short by its VAT amount in Pending Orders,
    // Collect Payment, and End-of-day Sales — and a cash sale would hand back
    // too much change, since the register calculates change against the
    // VAT-inclusive total shown on screen. The backend is the only place total
    // should be computed, since it's the only source every screen reads from.
    $subtotal = 0;
    foreach ($cartItems as $item) { $subtotal += $priceById[(int)$item['id']] * (int)$item['qty']; }
    $vat = round($subtotal * 0.12);
    $total = $subtotal + $vat;

    $payingNow = ($cashReceived !== null);
    if ($payingNow && $cashReceived < $total) {
        echo json_encode(['success' => false, 'message' => 'Cash received must cover the total.']);
        return;
    }
    $status = $payingNow ? 'Paid' : 'Pending';
    $changeGiven = $payingNow ? ($cashReceived - $total) : null;

    // NOTE: order_code used to be built from SELECT COUNT(*) FROM pos_sales before
    // insert. That's not atomic: when two orders are placed at nearly the same
    // moment (kiosk + cashier, or two kiosk taps close together — routine during
    // a rush), both requests can read the same count and compute the SAME
    // order_code. pos_sales.order_code has a UNIQUE key, so the second INSERT
    // fails — and since the old code never checked execute()'s return value, that
    // failure was swallowed: $conn->insert_id stayed at 0 (or a stale id from an
    // earlier query), the pos_sale_items rows got inserted under that wrong id,
    // and the transaction still committed as if nothing went wrong. Net effect:
    // the customer's order silently never appears in Pending Orders (or its items
    // land on an unrelated order, which then shows the wrong items/total in
    // Pending, Receipts, and End-of-day Sales).
    //
    // Fix: don't compute the code before the row exists. Insert first with a
    // temporary placeholder, then derive order_code from the row's own
    // auto-increment id (which MySQL guarantees is unique with no race possible),
    // and check every execute() so a real failure aborts the transaction instead
    // of continuing silently.
    $conn->begin_transaction();
    try {
        pos_ensure_paid_at($conn);
        $placeholder = 'TMP-' . bin2hex(random_bytes(8));
        $paidAt = $payingNow ? date('Y-m-d H:i:s') : null;
        $stmt = $conn->prepare('INSERT INTO pos_sales (order_code, total, cash_received, change_given, status, order_type, payment, sold_counted, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
        $stmt->bind_param('sdddssss', $placeholder, $total, $cashReceived, $changeGiven, $status, $orderType, $payment, $paidAt);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create order: ' . $stmt->error);
        }
        $newOrderId = $conn->insert_id;

        $orderId = 'ORD-' . str_pad($newOrderId, 3, '0', STR_PAD_LEFT);
        $fixCode = $conn->prepare('UPDATE pos_sales SET order_code = ? WHERE id = ?');
        $fixCode->bind_param('si', $orderId, $newOrderId);
        if (!$fixCode->execute()) {
            throw new Exception('Failed to assign order code: ' . $fixCode->error);
        }

        // Insert line items always — the Cashier's Pending queue needs to show
        // what was ordered even before payment happens.
        $stmt2 = $conn->prepare('INSERT INTO pos_sale_items (order_id, item_id, qty, unit_price) VALUES (?, ?, ?, ?)');
        foreach ($cartItems as $item) {
            $itemId = (int)($item['id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $price = $priceById[$itemId]; // server-authoritative price
            if ($itemId <= 0 || $qty <= 0) {
                throw new Exception('Invalid item in cart.');
            }
            $stmt2->bind_param('iiid', $newOrderId, $itemId, $qty, $price);
            if (!$stmt2->execute()) {
                throw new Exception('Failed to save order item: ' . $stmt2->error);
            }
        }

        // Stock is deducted as soon as the order exists — Pending or Paid —
        // so a customer's items are actually reserved the moment they check out,
        // instead of staying available for someone else to order out from under
        // them while payment is still pending at the counter. "sold" (for the
        // items-sold report) still only counts once the order is actually Paid.
        // Refresh makeable counts first so the check below uses live ingredient
        // stock, reserve the drinks, then CONSUME the recipe ingredients so every
        // other drink sharing those ingredients shows its new stock right away.
        refreshMenuStockFromRecipes($conn);
        deductStockForOrder($conn, $newOrderId);
        consumeIngredientsForOrder($conn, $newOrderId, -1);
        if ($payingNow) {
            countMenuSales($conn, $newOrderId);
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'status' => $status,
            'cash_received' => $cashReceived,
            'change_given' => $changeGiven,
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $conn->close();
}

/**
 * Cashier collects payment for a Pending (Kiosk-placed) order: marks it Paid
 * and counts the sale. Stock for this order was already deducted when it was
 * placed as Pending — don't deduct it again here.
 */
function collectPayment() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($_GET['id'] ?? ($data['id'] ?? 0));
    $cashReceived = isset($data['cash_received']) ? (float)$data['cash_received'] : null;

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Order ID required.']); return; }
    if ($cashReceived === null) { echo json_encode(['success' => false, 'message' => 'Cash received is required.']); return; }

    $conn->begin_transaction();
    try {
        $orderStmt = $conn->prepare("SELECT status, total FROM pos_sales WHERE id = ? FOR UPDATE");
        $orderStmt->bind_param('i', $id);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();

        if (!$order) { throw new Exception('Order not found.'); }
        if ($order['status'] !== 'Pending') { throw new Exception('Order is not awaiting payment.'); }

        $total = (float)$order['total'];
        if ($cashReceived < $total) { throw new Exception('Cash received must cover the total.'); }
        $changeGiven = $cashReceived - $total;

        pos_ensure_paid_at($conn);
        $update = $conn->prepare("UPDATE pos_sales SET status = 'Paid', cash_received = ?, change_given = ?, paid_at = NOW() WHERE id = ?");
        $update->bind_param('ddi', $cashReceived, $changeGiven, $id);
        if (!$update->execute()) {
            throw new Exception('Failed to mark order as paid: ' . $update->error);
        }

        countMenuSales($conn, $id);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Payment collected.', 'cash_received' => $cashReceived, 'change_given' => $changeGiven, 'total' => $total]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $conn->close();
}

/**
 * Voiding an order restores the stock that was deducted. Stock is now taken
 * as soon as an order is placed — Pending or Paid — so voiding either status
 * restores it. (Only an already-Voided order never had stock touched again
 * since its own void, which is guarded against above.)
 */
function updateOrderStatus() {
    $conn = getDB();
    if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); return; }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($_GET['id'] ?? 0);
    $status = $data['status'] ?? 'Voided';

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Order ID required.']); return; }

    if ($status !== 'Voided') {
        $stmt = $conn->prepare('UPDATE pos_sales SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Order status updated.']);
        $conn->close();
        return;
    }

    $conn->begin_transaction();
    try {
        $orderStmt = $conn->prepare('SELECT status FROM pos_sales WHERE id = ? FOR UPDATE');
        $orderStmt->bind_param('i', $id);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();

        if (!$order) { throw new Exception('Order not found.'); }
        if ($order['status'] === 'Voided') { throw new Exception('Order is already voided.'); }

        // Restore stock for any non-Voided order (Pending or Paid) — both had
        // stock deducted at creation time now.
        $itemsStmt = $conn->prepare('SELECT item_id, qty FROM pos_sale_items WHERE order_id = ?');
        $itemsStmt->bind_param('i', $id);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $restoreStmt = $conn->prepare('UPDATE menu_items SET stock = stock + ? WHERE id = ?');
        foreach ($items as $item) {
            $qty = (int)$item['qty'];
            $itemId = (int)$item['item_id'];
            $restoreStmt->bind_param('ii', $qty, $itemId);
            if (!$restoreStmt->execute()) {
                throw new Exception('Failed to restore stock: ' . $restoreStmt->error);
            }
        }

        // Give back the raw recipe ingredients this order consumed, then refresh.
        consumeIngredientsForOrder($conn, $id, 1);

        $voidStmt = $conn->prepare("UPDATE pos_sales SET status = 'Voided' WHERE id = ?");
        $voidStmt->bind_param('i', $id);
        $voidStmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order voided.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $conn->close();
}