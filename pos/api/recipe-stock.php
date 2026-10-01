<?php
// ===== RECIPE-LINKED STOCK HELPERS (POS API) =====
// For menu items that have a recipe (menu_ingredients rows), menu_items.stock is
// NOT a free counter: it always equals the number of WHOLE servings still
// makeable from raw ingredient stock — MIN over the recipe of
// FLOOR(items.current_qty / qty-per-serving). Items without a recipe keep their
// manual stock untouched.
//
// Flow: when an order is created (Pending or Paid) the POS consumes the recipe
// ingredients of every line (see consumeIngredientsForOrder), then refreshes
// menu stock — so two drinks sharing an ingredient (e.g. coffee beans) both
// show their new makeable count immediately, and when an ingredient runs out
// every drink whose recipe needs it drops to 0 (you truly can't make it),
// while drinks that don't use it are unaffected.

if (!function_exists('refreshMenuStockFromRecipes')) {
function refreshMenuStockFromRecipes($conn) {
    $conn->query("UPDATE menu_items m
                  SET m.stock = COALESCE((
                      SELECT MIN(FLOOR(i.current_qty / mi.qty))
                      FROM menu_ingredients mi
                      JOIN items i ON i.id = mi.item_id
                      WHERE mi.menu_item_id = m.id AND mi.qty > 0
                  ), m.stock)
                  WHERE m.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM menu_ingredients mi WHERE mi.menu_item_id = m.id)");
}
}

if (!function_exists('consumeIngredientsForOrder')) {
/**
 * Consume (sign = -1) or restore (sign = +1) the raw recipe ingredients of every
 * line of an order, then refresh all recipe-linked menu stock.
 * Safe for items without recipes (the UPDATE simply matches no rows).
 */
function consumeIngredientsForOrder($conn, $orderId, $sign = -1) {
    $orderId = (int)$orderId;
    $res = $conn->query("SELECT item_id, qty FROM pos_sale_items WHERE order_id = {$orderId}");
    if (!$res) return;
    while ($line = $res->fetch_assoc()) {
        $itemId = (int)$line['item_id'];
        $qty    = (int)$line['qty'];
        if ($itemId <= 0 || $qty <= 0) continue;
        if ($sign < 0) {
            $st = $conn->prepare('UPDATE items i
                                  JOIN menu_ingredients mi ON mi.item_id = i.id
                                  SET i.current_qty = GREATEST(i.current_qty - (mi.qty * ?), 0)
                                  WHERE mi.menu_item_id = ?');
        } else {
            $st = $conn->prepare('UPDATE items i
                                  JOIN menu_ingredients mi ON mi.item_id = i.id
                                  SET i.current_qty = i.current_qty + (mi.qty * ?)
                                  WHERE mi.menu_item_id = ?');
        }
        if ($st) { $st->bind_param('ii', $qty, $itemId); $st->execute(); }
    }
    refreshMenuStockFromRecipes($conn);
}
}
