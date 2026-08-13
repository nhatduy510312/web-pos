<?php

/**
 * Tự động chốt sổ tiền mặt vào giờ đóng ca.
 * Cron bảo đảm chạy đúng giờ; lời gọi từ config.php là lớp dự phòng nếu cron trễ.
 */
function shiftGuardStartDate(): string
{
    $configuredDate = getenv('POS_SHIFT_GUARD_START_DATE');
    if ($configuredDate !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $configuredDate)) {
        return $configuredDate;
    }

    return '2026-08-13';
}

function shiftAutoCloseTime(): string
{
    $configuredTime = getenv('POS_AUTO_CLOSE_TIME');
    if ($configuredTime !== false && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $configuredTime)) {
        return $configuredTime;
    }

    return '22:00';
}

function shiftIsClosed(mysqli $conn, string $date): bool
{
    $stmt = $conn->prepare(
        "SELECT 1 FROM cashbook_history WHERE report_date = ? LIMIT 1"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function shiftHasActivity(mysqli $conn, string $date): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM (
             SELECT DATE(paid_at) AS activity_date
             FROM orders
             WHERE status = 'paid' AND paid_at IS NOT NULL
             UNION
             SELECT opening_date FROM cash_opening
             UNION
             SELECT expense_date FROM cash_expenses
             UNION
             SELECT deposit_date FROM cash_deposit
         ) activity
         WHERE activity.activity_date = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function shiftNeedsClosure(mysqli $conn, string $date): bool
{
    return shiftHasActivity($conn, $date) && !shiftIsClosed($conn, $date);
}

function shiftCutoffReached(?DateTimeImmutable $now = null): bool
{
    $now = $now ?? new DateTimeImmutable('now');
    return $now->format('H:i') >= shiftAutoCloseTime();
}

function shiftAutoCloseIsPausedForAdmin(string $date): bool
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        return false;
    }

    $until = (int)($_SESSION['shift_auto_close_pause'][$date] ?? 0);
    return $until >= time();
}

function findShiftsDueForAutoClose(mysqli $conn, DateTimeImmutable $now): array
{
    $lastDueDate = shiftCutoffReached($now)
        ? $now->format('Y-m-d')
        : $now->modify('-1 day')->format('Y-m-d');
    $startDate = shiftGuardStartDate();

    $stmt = $conn->prepare(
        "SELECT activity.activity_date
         FROM (
             SELECT DATE(paid_at) AS activity_date
             FROM orders
             WHERE status = 'paid' AND paid_at IS NOT NULL
             UNION
             SELECT opening_date FROM cash_opening
             UNION
             SELECT expense_date FROM cash_expenses
             UNION
             SELECT deposit_date FROM cash_deposit
         ) activity
         LEFT JOIN cashbook_history history
           ON history.report_date = activity.activity_date
         WHERE activity.activity_date >= ?
           AND activity.activity_date <= ?
           AND history.id IS NULL
         ORDER BY activity.activity_date ASC"
    );
    $stmt->bind_param('ss', $startDate, $lastDueDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $dates = [];

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['activity_date'];
    }

    return $dates;
}

function closeShiftSnapshot(mysqli $conn, string $date): bool
{
    $conn->begin_transaction();

    try {
        if (shiftIsClosed($conn, $date)) {
            $conn->commit();
            return false;
        }

        $stmt = $conn->prepare("SELECT amount FROM cash_opening WHERE opening_date = ? LIMIT 1");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $openingRow = $stmt->get_result()->fetch_assoc();

        if ($openingRow) {
            $openingCash = (float)$openingRow['amount'];
        } else {
            $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
            $stmt = $conn->prepare("SELECT closing_cash FROM cashbook_history WHERE report_date = ? LIMIT 1");
            $stmt->bind_param('s', $yesterday);
            $stmt->execute();
            $previous = $stmt->get_result()->fetch_assoc();
            $openingCash = (float)($previous['closing_cash'] ?? 0);
        }

        $stmt = $conn->prepare(
            "SELECT
                IFNULL(SUM(cash_amount), 0) AS cash_revenue,
                IFNULL(SUM(bank_amount), 0) AS transfer_revenue
             FROM orders
             WHERE status = 'paid' AND DATE(paid_at) = ?"
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc();
        $cashRevenue = (float)$revenue['cash_revenue'];
        $transferRevenue = (float)$revenue['transfer_revenue'];

        $stmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM cash_expenses WHERE expense_date = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $expenses = (float)$stmt->get_result()->fetch_assoc()['total'];

        $stmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM cash_deposit WHERE deposit_date = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $deposits = (float)$stmt->get_result()->fetch_assoc()['total'];
        $closingCash = $openingCash + $cashRevenue - $expenses - $deposits;

        $stmt = $conn->prepare(
            "INSERT INTO cashbook_history
                (report_date, opening_cash, cash_revenue, transfer_revenue, expenses, deposits, closing_cash)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE report_date = report_date"
        );
        $stmt->bind_param(
            'sdddddd',
            $date,
            $openingCash,
            $cashRevenue,
            $transferRevenue,
            $expenses,
            $deposits,
            $closingCash
        );
        $stmt->execute();
        $created = $stmt->affected_rows === 1;
        $conn->commit();

        return $created;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function autoCloseDueShifts(mysqli $conn, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now');
    $closedDates = [];

    foreach (findShiftsDueForAutoClose($conn, $now) as $date) {
        if (shiftAutoCloseIsPausedForAdmin($date)) {
            continue;
        }

        if (closeShiftSnapshot($conn, $date)) {
            $closedDates[] = $date;
        }
    }

    return $closedDates;
}

function enforceClosedShiftForApi(mysqli $conn): void
{
    if (!defined('API_REQUEST') || !API_REQUEST || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $today = date('Y-m-d');
    if (!shiftIsClosed($conn, $today) && !shiftCutoffReached()) {
        return;
    }

    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Ca hôm nay đã kết thúc lúc ' . shiftAutoCloseTime() . '. Không thể ghi thêm giao dịch.',
        'redirect' => 'cashbook.php?date=' . rawurlencode($today),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
