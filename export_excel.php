<?php
include 'auth.php';
require 'vendor/autoload.php';
include 'config.php';
requireRole(['admin', 'staff', 'user']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');
$fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$from);
$toDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$to);
if (!$fromDate || $fromDate->format('Y-m-d') !== $from
    || !$toDate || $toDate->format('Y-m-d') !== $to) {
    http_response_code(400);
    exit('Khoảng ngày không hợp lệ.');
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
    [$from, $to] = [$to, $from];
}
if ($fromDate->diff($toDate)->days > 366) {
    http_response_code(400);
    exit('Mỗi lần chỉ xuất tối đa 366 ngày.');
}
$fromTimestamp = $fromDate->format('Y-m-d 00:00:00');
$toExclusive = $toDate->modify('+1 day')->format('Y-m-d 00:00:00');

$countStmt = $conn->prepare(
    "SELECT COUNT(*) total FROM orders WHERE status='paid' AND paid_at>=? AND paid_at<?"
);
$countStmt->bind_param('ss', $fromTimestamp, $toExclusive);
$countStmt->execute();
$exportCount = (int)$countStmt->get_result()->fetch_assoc()['total'];
if ($exportCount > 20000) {
    http_response_code(413);
    exit('Khoảng ngày có quá 20.000 hóa đơn. Vui lòng chia nhỏ khoảng thời gian.');
}

$spreadsheet = new Spreadsheet();

/* ── Sheet 1: Doanh thu ── */
$sheet = $spreadsheet->getActiveSheet()->setTitle('Doanh Thu');

$headers = ['ID HĐ','Khách hàng','Nhân viên','Tổng tiền','Tiền mặt','Chuyển khoản','Phương thức','Thời gian'];
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue("{$col}1", $h);
    $sheet->getStyle("{$col}1")->getFont()->setBold(true);
}
$sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('FFEFF6FF');

/* BUG FIX: use prepared statement */
$stmt = $conn->prepare(
    "SELECT o.*, COALESCE(e.name, u.username) AS sold_by_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.sold_by_user_id
     LEFT JOIN employees e ON e.id = u.employee_id
     WHERE o.status='paid' AND o.paid_at>=? AND o.paid_at<?
     ORDER BY o.id DESC"
);
$stmt->bind_param("ss", $fromTimestamp, $toExclusive); $stmt->execute();
$orders = $stmt->get_result();

$row = 2;
while ($o = $orders->fetch_assoc()) {
    $sheet->setCellValue("A$row", $o['id']);
    $sheet->setCellValueExplicit("B$row", (string)($o['customer_name'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C$row", (string)($o['sold_by_name'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("D$row", (float)$o['total_amount']);
    $sheet->setCellValue("E$row", (float)$o['cash_amount']);
    $sheet->setCellValue("F$row", (float)$o['bank_amount']);
    $sheet->setCellValueExplicit("G$row", (string)$o['payment_method'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("H$row", (string)$o['paid_at'], DataType::TYPE_STRING);
    $row++;
}

$sheet->getStyle("D2:F{$row}")->getNumberFormat()->setFormatCode('#,##0');
$columnWidths = ['A'=>10,'B'=>24,'C'=>22,'D'=>16,'E'=>16,'F'=>16,'G'=>16,'H'=>20];
foreach ($columnWidths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);

/* ── Sheet 2: Top món ── */
$topSheet = $spreadsheet->createSheet()->setTitle('Top Mon');
$topSheet->setCellValue('A1', 'Tên Món');
$topSheet->setCellValue('B1', 'Số Lượng');
$topSheet->getStyle('A1:B1')->getFont()->setBold(true);

/* BUG FIX: prepared statement */
$stmt = $conn->prepare("
    SELECT p.name, SUM(oi.qty) total_qty
    FROM order_items oi
    INNER JOIN products p ON p.id=oi.product_id
    INNER JOIN orders o ON o.id=oi.order_id
    WHERE o.status='paid' AND o.paid_at>=? AND o.paid_at<?
    GROUP BY p.id,p.name ORDER BY total_qty DESC LIMIT 20
");
$stmt->bind_param("ss", $fromTimestamp, $toExclusive); $stmt->execute();
$topProducts = $stmt->get_result();

$row = 2;
while ($item = $topProducts->fetch_assoc()) {
    $topSheet->setCellValueExplicit("A$row", (string)$item['name'], DataType::TYPE_STRING);
    $topSheet->setCellValue("B$row", (int)$item['total_qty']);
    $row++;
}
$topSheet->getColumnDimension('A')->setWidth(36);
$topSheet->getColumnDimension('B')->setWidth(14);

/* ── Download ── */
$fileName = 'BaoCao_' . $from . '_Den_' . $to . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
