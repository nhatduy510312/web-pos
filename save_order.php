<?php

define('API_REQUEST', true);

include 'auth.php';
include 'config.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

function jsonResponse($success, $message = '', $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

$data = json_decode(
    file_get_contents('php://input'),
    true
);

if(!is_array($data)){
    jsonResponse(false, 'Du lieu khong hop le');
}

csrf_require($data['csrf_token'] ?? '');

$items = $data['items'] ?? [];

$payment_method =
    $data['payment_method'] ?? 'cash';

$cash_amount =
    (float)($data['cash_amount'] ?? 0);

$bank_amount =
    (float)($data['bank_amount'] ?? 0);

if(!in_array($payment_method, ['cash', 'bank', 'mixed'], true)){
    jsonResponse(false, 'Phuong thuc thanh toan khong hop le');
}

if(!is_array($items) || count($items) === 0 || count($items) > 100){
    jsonResponse(false, 'Gio hang trong');
}

$total = 0;
$order_items = [];

foreach($items as $item){

    $product_id =
        (int)($item['id'] ?? 0);

    $qty =
        (int)($item['qty'] ?? 0);

    if($product_id <= 0 || $qty <= 0 || $qty > 1000){
        jsonResponse(false, 'San pham hoac so luong khong hop le');
    }

    $stmt = $conn->prepare("
        SELECT id, price
        FROM products
        WHERE id=?
        AND status=1
        LIMIT 1
    ");

    $stmt->bind_param(
        'i',
        $product_id
    );

    $stmt->execute();

    $product =
        $stmt->get_result()
        ->fetch_assoc();

    if(!$product){
        jsonResponse(false, 'San pham khong ton tai hoac dang tat');
    }

    $price =
        (float)$product['price'];

    $total +=
        $price
        *
        $qty;

    $order_items[] = [
        'product_id' => $product_id,
        'qty' => $qty,
        'price' => $price
    ];
}

if(($cash_amount + $bank_amount) < $total){
    jsonResponse(false, 'Khach chua thanh toan du');
}

$conn->begin_transaction();

try{

    $stmt = $conn->prepare("
        INSERT INTO orders(
            status,
            total_amount,
            cash_amount,
            bank_amount,
            payment_method,
            paid_at
        )
        VALUES(
            'paid',
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    $stmt->bind_param(
        'ddds',
        $total,
        $cash_amount,
        $bank_amount,
        $payment_method
    );

    $stmt->execute();

    $order_id = $conn->insert_id;

    foreach($order_items as $item){

        $product_id =
            $item['product_id'];

        $qty =
            $item['qty'];

        $price =
            $item['price'];

        $stmt = $conn->prepare("
            INSERT INTO order_items(
                order_id,
                product_id,
                qty,
                unit_price
            )
            VALUES(
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->bind_param(
            'iiid',
            $order_id,
            $product_id,
            $qty,
            $price
        );

        $stmt->execute();
    }

    $conn->commit();

    jsonResponse(true, '', [
        'order_id' => $order_id
    ]);

}catch(Throwable $e){

    $conn->rollback();

    jsonResponse(false, 'Khong the luu hoa don');
}
