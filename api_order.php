<?php

define('API_REQUEST', true);

include 'auth.php';
include 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

$readActions = ['get_or_create', 'load'];
if (in_array($action, $readActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

// Đọc body 1 lần duy nhất, dùng chung cho mọi action (tránh đọc php://input
// nhiều lần và tránh việc mỗi action tự decode JSON riêng).
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

// FIX (#6): chặn CSRF cho mọi request làm thay đổi dữ liệu (POST).
// Token có thể nằm trong JSON body (đa số action) hoặc $_POST
// (action update_note dùng form-urlencoded).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $jsonInput['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    csrf_require($csrfToken);
}

function apiResponse($success, $message = '', $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function getOpenOrder($conn, $order_id)
{
    $stmt = $conn->prepare("
        SELECT *
        FROM orders
        WHERE id=?
        AND status='open'
        LIMIT 1
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/* =========================
   UPDATE TOTAL
========================= */

function updateTotal($conn, $order_id)
{
    // FIX (#7): dùng prepared statement thay vì nối chuỗi $order_id vào query
    $stmt = $conn->prepare("
        SELECT SUM(qty * unit_price) AS total
        FROM order_items
        WHERE order_id=?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $total = $row['total'] ?? 0;

    $stmt = $conn->prepare("
        UPDATE orders
        SET total_amount=?
        WHERE id=?
    ");

    $stmt->bind_param("di", $total, $order_id);
    $stmt->execute();
}

/* =========================
   GET ORDER
========================= */

if ($action == 'get_or_create') {

    $table_id = (int)($_GET['table_id'] ?? 0);

    if (!$table_id) {
        apiResponse(false, 'Thiếu table_id');
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM orders
        WHERE table_id=?
        AND status='open'
        LIMIT 1
    ");

    $stmt->bind_param("i", $table_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
    } else {
        $order_id = 0;
    }

    apiResponse(true, '', ['order_id' => $order_id]);
}

/* =========================
   CREATE ORDER
========================= */

if ($action == 'create_order') {

    $table_id = (int)($jsonInput['table_id'] ?? 0);

    if ($table_id <= 0) {
        apiResponse(false, 'Ban khong hop le');
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM orders
        WHERE table_id=?
        AND status='open'
        LIMIT 1
    ");

    $stmt->bind_param("i", $table_id);
    $stmt->execute();

    if ($row = $stmt->get_result()->fetch_assoc()) {
        apiResponse(true, '', ['order_id' => $row['id']]);
    }

    $stmt = $conn->prepare("
        INSERT INTO orders
            (table_id, customer_name, status, total_amount, cash_amount, bank_amount)
        VALUES
            (?, '', 'open', 0, 0, 0)
    ");

    $stmt->bind_param("i", $table_id);
    $stmt->execute();

    apiResponse(true, '', ['order_id' => $conn->insert_id]);
}

/* =========================
   LOAD ORDER
========================= */

if ($action == 'load') {

    $order_id = (int)($_GET['order_id'] ?? 0);

    $items = [];

    $stmt = $conn->prepare("
        SELECT oi.*, p.name
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id=?
        ORDER BY oi.id
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $row['amount'] = $row['qty'] * $row['unit_price'];
        $total += $row['amount'];
        $items[] = $row;
    }

    $stmt = $conn->prepare("
        SELECT customer_name
        FROM orders
        WHERE id=?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();

    apiResponse(true, '', [
        'items' => $items,
        'total' => $total,
        'customer_name' => $order['customer_name'] ?? ''
    ]);
}

/* =========================
   ADD ITEM
========================= */

if ($action == 'add_item') {

    $order_id = (int)($jsonInput['order_id'] ?? 0);
    $product_id = (int)($jsonInput['product_id'] ?? 0);

    if (!$order = getOpenOrder($conn, $order_id)) {
        apiResponse(false, 'Hoa don da thanh toan hoac khong ton tai');
    }

    $stmt = $conn->prepare("
        SELECT * FROM order_items
        WHERE order_id=? AND product_id=?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $order_id, $product_id);
    $stmt->execute();

    $exist = $stmt->get_result()->fetch_assoc();

    if ($exist) {

        $stmt = $conn->prepare("
            UPDATE order_items
            SET qty = qty + 1
            WHERE id=?
        ");

        $stmt->bind_param("i", $exist['id']);
        $stmt->execute();

    } else {

        $stmt = $conn->prepare("
            SELECT price FROM products
            WHERE id=? AND status=1
            LIMIT 1
        ");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            apiResponse(false, 'San pham khong ton tai hoac dang tat');
        }

        $price = $product['price'];

        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, qty, unit_price)
            VALUES (?, ?, 1, ?)
        ");

        $stmt->bind_param("iid", $order_id, $product_id, $price);
        $stmt->execute();
    }

    updateTotal($conn, $order_id);

    apiResponse(true);
}

/* =========================
   UPDATE QTY
========================= */

if ($action == 'update_qty') {

    $item_id = (int)($jsonInput['item_id'] ?? 0);
    $mode = $jsonInput['mode'] ?? '';

    if (!in_array($mode, ['plus', 'minus'], true)) {
        apiResponse(false, 'Kieu cap nhat khong hop le');
    }

    $stmt = $conn->prepare("
        SELECT oi.*
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.id=? AND o.status='open'
        LIMIT 1
    ");

    $stmt->bind_param("i", $item_id);
    $stmt->execute();

    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) {
        apiResponse(false, 'Mon khong ton tai hoac hoa don da thanh toan');
    }

    // FIX (#7): dùng prepared statement thay vì nối chuỗi $item_id vào query
    if ($mode == 'plus') {

        $stmt = $conn->prepare("
            UPDATE order_items
            SET qty = qty + 1
            WHERE id=?
        ");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();

    } else {

        if ($item['qty'] <= 1) {

            $stmt = $conn->prepare("
                DELETE FROM order_items
                WHERE id=?
            ");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();

        } else {

            $stmt = $conn->prepare("
                UPDATE order_items
                SET qty = qty - 1
                WHERE id=?
            ");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
        }
    }

    updateTotal($conn, $item['order_id']);

    apiResponse(true);
}

/* =========================
   UPDATE CUSTOMER
========================= */

if ($action == 'update_customer') {

    $order_id = (int)($jsonInput['order_id'] ?? 0);

    if (!$order = getOpenOrder($conn, $order_id)) {
        apiResponse(false, 'Hoa don da thanh toan hoac khong ton tai');
    }

    $customer_name = trim($jsonInput['customer_name'] ?? '');

    $stmt = $conn->prepare("
        UPDATE orders
        SET customer_name=?
        WHERE id=?
    ");

    $stmt->bind_param("si", $customer_name, $order_id);
    $stmt->execute();

    apiResponse(true);
}

/* =========================
   UPDATE NOTE
   FIX (#5): khối này trước đây nằm SAU đoạn catch-all "Action không hợp lệ"
   (và đoạn đó thiếu exit), nên mỗi lần gọi update_note server trả về
   2 JSON dính liền nhau. Giờ đưa lên trước catch-all để chạy đúng.
========================= */

if ($action == 'update_note') {

    $item_id = (int)($_POST['item_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (mb_strlen($note) > 500) apiResponse(false, 'Ghi chu qua dai');

    $stmt = $conn->prepare("
        UPDATE order_items oi JOIN orders o ON o.id=oi.order_id
        SET oi.note=?
        WHERE oi.id=? AND o.status='open'
    ");

    $stmt->bind_param("si", $note, $item_id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) apiResponse(false, 'Mon khong ton tai hoac hoa don da thanh toan');

    apiResponse(true);
}

/* =========================
   ACTION KHÔNG HỢP LỆ (luôn đặt cuối cùng)
========================= */

apiResponse(false, 'Action không hợp lệ');
