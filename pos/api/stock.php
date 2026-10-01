<?php
// POS Stock API — returns raw ingredient stock from the inventory items table
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/require_login.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Viewing stock is fine for any signed-in staff; adjusting reorder levels / quantities
// (PUT) is a stock-management action, restricted to Admin.
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    requireRole(['Admin']);
} else {
    requireLogin();
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']); exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("
        SELECT i.id, i.name, i.unit, i.current_qty, i.reorder_level, i.code,
               c.name AS category
        FROM items i
        JOIN categories c ON i.category_id = c.id
        WHERE i.is_active = 1
        ORDER BY c.name, i.name
    ");
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $items = [];
    foreach ($rows as $r) {
        $qty = (float)$r['current_qty'];
        $reorder = (float)$r['reorder_level'];
        $status = 'ok';
        if ($qty <= 0) $status = 'out';
        elseif ($qty <= $reorder) $status = 'low';

        $items[] = [
            'id'            => (int)$r['id'],
            'name'          => $r['name'],
            'unit'          => $r['unit'],
            'current_qty'   => $qty,
            'reorder_level' => $reorder,
            'code'          => $r['code'],
            'category'      => $r['category'],
            'status'        => $status
        ];
    }

    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

// PUT: Update an ingredient's reorder level (stock manager adjustment)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $qty = (float)($data['current_qty'] ?? -1);
    $reorder = (float)($data['reorder_level'] ?? -1);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID']); exit;
    }

    $sets = [];
    $params = [];
    if ($qty >= 0) { $sets[] = 'current_qty = ?'; $params[] = $qty; }
    if ($reorder >= 0) { $sets[] = 'reorder_level = ?'; $params[] = $reorder; }

    if (empty($sets)) {
        echo json_encode(['success' => false, 'message' => 'Nothing to update']); exit;
    }

    $params[] = $id;
    $sql = "UPDATE items SET " . implode(', ', $sets) . " WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);

    // Build a type string matching $params: 'd' for each decimal field, 'i' for the trailing id
    $types = str_repeat('d', count($params) - 1) . 'i';
    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Stock updated']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);

?>