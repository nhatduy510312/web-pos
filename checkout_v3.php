<?php

define('API_REQUEST', true);

include 'auth.php';
include 'config.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

function checkoutResponse($success, $message = '', $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

$data =
json_decode(
    file_get_contents('php://input'),
    true
);

if(!$data){
    checkoutResponse(false, 'Du lieu khong hop le');
}

// FIX (#6): chặn CSRF cho request thanh toán
csrf_require($data['csrf_token'] ?? '');

$order_id =
(int)($data['order_id'] ?? 0);

$payment_method =
$data['payment_method'] ?? '';

$cash_amount =
(float)($data['cash_amount'] ?? 0);

$bank_amount =
(float)($data['bank_amount'] ?? 0);

$discount_type =
$data['discount_type'] ?? '';

$discount_value =
(float)($data['discount_value'] ?? 0);

$sold_by_user_id =
(int)($_SESSION['user_id'] ?? 0);

if($order_id <= 0){
    checkoutResponse(false, 'Hoa don khong hop le');
}

if(!in_array($payment_method, ['cash', 'bank', 'mixed'], true)){
    checkoutResponse(false, 'Phuong thuc thanh toan khong hop le');
}

if(!in_array($discount_type, ['', 'percent', 'amount'], true)){
    checkoutResponse(false, 'Giam gia khong hop le');
}

$conn->begin_transaction();

try {
    // Khóa hóa đơn trong suốt quá trình tính tiền để hai thiết bị
    // không thể thanh toán hoặc sửa cùng một hóa đơn đồng thời.
    $stmt = $conn->prepare(
        "SELECT id FROM orders WHERE id=? AND status='open' LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $order_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $conn->rollback();
        checkoutResponse(false, 'Hoa don da thanh toan hoac khong ton tai');
    }

    $stmt = $conn->prepare(
        "SELECT qty, unit_price FROM order_items WHERE order_id=? FOR UPDATE"
    );
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $items = $stmt->get_result();
    $total = 0.0;
    while ($item = $items->fetch_assoc()) {
        $total += (float)$item['qty'] * (float)$item['unit_price'];
    }

    if ($total <= 0) {
        $conn->rollback();
        checkoutResponse(false, 'Hoa don khong co mon');
    }

    if ($discount_value < 0) {
        $discount_value = 0;
    }
    if ($discount_type === 'percent') {
        $discount_value = min($discount_value, 100);
        $discount_amount = $total * $discount_value / 100;
    } elseif ($discount_type === 'amount') {
        $discount_amount = $discount_value;
    } else {
        $discount_value = 0;
        $discount_amount = 0;
    }
    $discount_amount = min($discount_amount, $total);
    $final_total = $total - $discount_amount;

    if ($payment_method === 'cash') {
        $cash_amount = $final_total;
        $bank_amount = 0;
    } elseif ($payment_method === 'bank') {
        $cash_amount = 0;
        $bank_amount = $final_total;
    } elseif (($cash_amount + $bank_amount) < $final_total) {
        $conn->rollback();
        checkoutResponse(false, 'Khach chua thanh toan du');
    }

    $stmt = $conn->prepare("
        UPDATE orders
        SET total_amount=?, payment_method=?, cash_amount=?, bank_amount=?,
            discount_type=?, discount_value=?, discount_amount=?, status='paid',
            paid_at=NOW(), sold_by_user_id=?
        WHERE id=? AND status='open'
    ");
    $stmt->bind_param(
        'dsddsddii',
        $final_total,
        $payment_method,
        $cash_amount,
        $bank_amount,
        $discount_type,
        $discount_value,
        $discount_amount,
        $sold_by_user_id,
        $order_id
    );
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $conn->rollback();
        checkoutResponse(false, 'Hoa don da duoc xu ly tren thiet bi khac');
    }

    $conn->commit();
    checkoutResponse(true, '', ['order_id' => $order_id]);
} catch (Throwable $exception) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackException) {
        error_log('Checkout rollback failed: ' . $rollbackException->getMessage());
    }
    error_log('Checkout failed: ' . $exception->getMessage());
    http_response_code(500);
    checkoutResponse(false, 'Khong the thanh toan luc nay. Vui long thu lai.');
}
