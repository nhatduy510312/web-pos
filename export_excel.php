<?php
include 'auth.php';
require 'vendor/autoload.php';
include 'config.php';
requireRole(['admin', 'staff', 'user']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

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
     WHERE o.status='paid' AND DATE(o.paid_at) BETWEEN ? AND ?
     ORDER BY o.id DESC"
);
$stmt->bind_param("ss", $from, $to); $stmt->execute();
$orders = $stmt->get_result();

$row = 2;
while ($o = $orders->fetch_assoc()) {
    $sheet->setCellValue("A$row", $o['id']);
    $sheet->setCellValue("B$row", $o['customer_name'] ?? '');
    $sheet->setCellValue("C$row", $o['sold_by_name'] ?? '');
    $sheet->setCellValue("D$row", (float)$o['total_amount']);
    $sheet->setCellValue("E$row", (float)$o['cash_amount']);
    $sheet->setCellValue("F$row", (float)$o['bank_amount']);
    $sheet->setCellValue("G$row", $o['payment_method']);
    $sheet->setCellValue("H$row", $o['paid_at']);
    $row++;
}

$sheet->getStyle("D2:F{$row}")->getNumberFormat()->setFormatCode('#,##0');
foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

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
    WHERE o.status='paid' AND DATE(o.paid_at) BETWEEN ? AND ?
    GROUP BY p.id,p.name ORDER BY total_qty DESC LIMIT 20
");
$stmt->bind_param("ss", $from, $to); $stmt->execute();
$topProducts = $stmt->get_result();

$row = 2;
while ($item = $topProducts->fetch_assoc()) {
    $topSheet->setCellValue("A$row", $item['name']);
    $topSheet->setCellValue("B$row", (int)$item['total_qty']);
    $row++;
}
foreach (['A','B'] as $col) $topSheet->getColumnDimension($col)->setAutoSize(true);

/* ── Download ── */
$fileName = 'BaoCao_' . $from . '_Den_' . $to . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
