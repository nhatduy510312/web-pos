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

$stmt =
$conn->prepare("
    SELECT id
    FROM orders
    WHERE id=?
    AND status='open'
    LIMIT 1
");

$stmt->bind_param(
    'i',
    $order_id
);

$stmt->execute();

if(!$stmt->get_result()->fetch_assoc()){
    checkoutResponse(false, 'Hoa don da thanh toan hoac khong ton tai');
}

if($discount_value < 0){
    $discount_value = 0;
}

$stmt =
$conn->prepare("
    SELECT
        SUM(qty*unit_price)
        AS total
    FROM order_items
    WHERE order_id=?
");

$stmt->bind_param(
    'i',
    $order_id
);

$stmt->execute();

$row =
$stmt->get_result()
->fetch_assoc();

$total =
(float)($row['total'] ?? 0);

if($total <= 0){
    checkoutResponse(false, 'Hoa don khong co mon');
}

if($discount_type == 'percent'){

    if($discount_value > 100){
        $discount_value = 100;
    }

    $discount_amount =
        $total
        *
        $discount_value
        /
        100;

}else if($discount_type == 'amount'){

    $discount_amount =
        $discount_value;

}else{

    $discount_value = 0;
    $discount_amount = 0;
}

if($discount_amount > $total){
    $discount_amount = $total;
}

$final_total =
    $total
    -
    $discount_amount;

if($payment_method == 'cash'){

    $cash_amount = $final_total;
    $bank_amount = 0;

}else if($payment_method == 'bank'){

    $cash_amount = 0;
    $bank_amount = $final_total;

}else if(($cash_amount + $bank_amount) < $final_total){

    checkoutResponse(false, 'Khach chua thanh toan du');
}

$stmt =
$conn->prepare("
    UPDATE orders
    SET
        total_amount=?,
        payment_method=?,
        cash_amount=?,
        bank_amount=?,
        discount_type=?,
        discount_value=?,
        discount_amount=?,
        status='paid',
        paid_at=NOW(),
        sold_by_user_id=?
    WHERE id=?
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

checkoutResponse(true, '', [
    'order_id' => $order_id
]);
