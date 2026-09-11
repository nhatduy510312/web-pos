<?php
require_once __DIR__ . '/purchase_auth.php';

try {
    $purchaseDb = purchaseDatabase();
} catch (Throwable $exception) {
    error_log('Purchase portal database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Chưa kết nối được hệ thống mua hàng. Vui lòng thử lại sau.');
}
$purchaseAccount = requirePurchaseSession($purchaseDb);
$purchaseUserId = (int)$purchaseAccount['id'];
$error = '';
$successMessages = [
    'added' => 'Đã lưu thông tin mua hàng.',
    'updated' => 'Đã cập nhật thông tin mua hàng.',
    'deleted' => 'Đã xóa thông tin mua hàng.',
    'advance_added' => 'Đã lưu khoản ứng tiền.',
    'advance_updated' => 'Đã cập nhật khoản ứng tiền.',
    'advance_deleted' => 'Đã xóa khoản ứng tiền.',
];
$success = $successMessages[$_GET['success'] ?? ''] ?? '';

function purchaseDecimalInput(string $value, int $wholeDigits, int $decimalDigits): ?string
{
    $value = trim(str_replace(',', '.', $value));
    $pattern = '/^\d{1,' . $wholeDigits . '}(?:\.\d{1,' . $decimalDigits . '})?$/D';
    if (!preg_match($pattern, $value) || (float)$value <= 0) {
        return null;
    }
    return number_format((float)$value, $decimalDigits, '.', '');
}

function purchaseMoneyInput(string $value): ?string
{
    $value = trim(str_replace(' ', '', $value));
    if (!preg_match('/^(?:\d+|\d{1,3}(?:[.,]\d{3})+)$/D', $value)) {
        return null;
    }
    $digits = ltrim(str_replace(['.', ','], '', $value), '0');
    if ($digits === '' || strlen($digits) > 10) {
        return null;
    }
    return $digits . '.00';
}

$form = [
    'id' => 0,
    'purchase_date' => date('Y-m-d'),
    'supplier' => '',
    'item_name' => '',
    'quantity' => '1',
    'unit' => '',
    'total_amount' => '',
    'note' => '',
];
$advanceForm = [
    'id' => 0,
    'advance_date' => date('Y-m-d'),
    'amount' => '',
    'note' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete_advance') {
        $id = (int)($_POST['id'] ?? 0);
        $returnMonth = (string)($_POST['month'] ?? date('Y-m'));
        if (!purchaseValidMonth($returnMonth)) {
            $returnMonth = date('Y-m');
        }
        $stmt = $purchaseDb->prepare('DELETE FROM purchase_advances WHERE id = ? AND created_by = ?');
        $stmt->bind_param('ii', $id, $purchaseUserId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $error = 'Không tìm thấy khoản ứng hoặc bạn không có quyền xóa.';
        } else {
            header('Location: purchases.php?month=' . rawurlencode($returnMonth) . '&success=advance_deleted');
            exit;
        }
    }

    if ($action === 'save_advance') {
        $advanceForm = [
            'id' => (int)($_POST['id'] ?? 0),
            'advance_date' => trim((string)($_POST['advance_date'] ?? '')),
            'amount' => trim((string)($_POST['amount'] ?? '')),
            'note' => trim((string)($_POST['advance_note'] ?? '')),
        ];
        $advanceAmount = purchaseMoneyInput($advanceForm['amount']);
        if (!purchaseValidDate($advanceForm['advance_date'])) {
            $error = 'Ngày ứng tiền không hợp lệ.';
        } elseif ($advanceAmount === null) {
            $error = 'Khoản ứng phải là số lớn hơn 0.';
        } elseif (mb_strlen($advanceForm['note'], 'UTF-8') > 1000) {
            $error = 'Ghi chú khoản ứng tối đa 1.000 ký tự.';
        } elseif ((int)$advanceForm['id'] > 0) {
            $id = (int)$advanceForm['id'];
            $stmt = $purchaseDb->prepare(
                'UPDATE purchase_advances SET advance_date = ?, amount = ?, note = ?
                 WHERE id = ? AND created_by = ?'
            );
            $stmt->bind_param('sssii', $advanceForm['advance_date'], $advanceAmount, $advanceForm['note'], $id, $purchaseUserId);
            $stmt->execute();
            $check = $purchaseDb->prepare('SELECT id FROM purchase_advances WHERE id = ? AND created_by = ?');
            $check->bind_param('ii', $id, $purchaseUserId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $error = 'Không tìm thấy khoản ứng hoặc bạn không có quyền sửa.';
            } else {
                header('Location: purchases.php?month=' . rawurlencode(substr($advanceForm['advance_date'], 0, 7)) . '&success=advance_updated');
                exit;
            }
        } else {
            $stmt = $purchaseDb->prepare(
                'INSERT INTO purchase_advances (advance_date, amount, note, created_by) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('sssi', $advanceForm['advance_date'], $advanceAmount, $advanceForm['note'], $purchaseUserId);
            $stmt->execute();
            header('Location: purchases.php?month=' . rawurlencode(substr($advanceForm['advance_date'], 0, 7)) . '&success=advance_added');
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $returnMonth = (string)($_POST['month'] ?? date('Y-m'));
        if (!purchaseValidMonth($returnMonth)) {
            $returnMonth = date('Y-m');
        }
        $stmt = $purchaseDb->prepare('DELETE FROM purchase_entries WHERE id = ? AND created_by = ?');
        $stmt->bind_param('ii', $id, $purchaseUserId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $error = 'Không tìm thấy thông tin mua hàng hoặc bạn không có quyền xóa.';
        } else {
            header('Location: purchases.php?month=' . rawurlencode($returnMonth) . '&success=deleted');
            exit;
        }
    }

    if ($action === 'save') {
        $form = [
            'id' => (int)($_POST['id'] ?? 0),
            'purchase_date' => trim((string)($_POST['purchase_date'] ?? '')),
            'supplier' => trim((string)($_POST['supplier'] ?? '')),
            'item_name' => trim((string)($_POST['item_name'] ?? '')),
            'quantity' => trim((string)($_POST['quantity'] ?? '')),
            'unit' => trim((string)($_POST['unit'] ?? '')),
            'total_amount' => trim((string)($_POST['total_amount'] ?? '')),
            'note' => trim((string)($_POST['note'] ?? '')),
        ];
        $quantity = purchaseDecimalInput($form['quantity'], 9, 3);
        $totalAmount = purchaseMoneyInput($form['total_amount']);

        if (!purchaseValidDate($form['purchase_date'])) {
            $error = 'Ngày mua không hợp lệ.';
        } elseif ($form['item_name'] === '' || mb_strlen($form['item_name'], 'UTF-8') > 150) {
            $error = 'Vui lòng nhập nội dung mua hàng, tối đa 150 ký tự.';
        } elseif (mb_strlen($form['supplier'], 'UTF-8') > 150) {
            $error = 'Tên nhà cung cấp tối đa 150 ký tự.';
        } elseif ($quantity === null) {
            $error = 'Số lượng phải lớn hơn 0 và có tối đa 3 chữ số thập phân.';
        } elseif (mb_strlen($form['unit'], 'UTF-8') > 30) {
            $error = 'Đơn vị tính tối đa 30 ký tự.';
        } elseif ($totalAmount === null) {
            $error = 'Tổng tiền phải là số lớn hơn 0.';
        } elseif (mb_strlen($form['note'], 'UTF-8') > 1000) {
            $error = 'Ghi chú tối đa 1.000 ký tự.';
        } else {
            if ((int)$form['id'] > 0) {
                $id = (int)$form['id'];
                $stmt = $purchaseDb->prepare(
                    'UPDATE purchase_entries
                     SET purchase_date = ?, supplier = ?, item_name = ?, quantity = ?, unit = ?,
                         total_amount = ?, note = ?
                     WHERE id = ? AND created_by = ?'
                );
                $stmt->bind_param(
                    'sssssssii',
                    $form['purchase_date'],
                    $form['supplier'],
                    $form['item_name'],
                    $quantity,
                    $form['unit'],
                    $totalAmount,
                    $form['note'],
                    $id,
                    $purchaseUserId
                );
                $stmt->execute();
                if ($stmt->affected_rows < 0) {
                    $error = 'Không thể cập nhật thông tin mua hàng.';
                } else {
                    $check = $purchaseDb->prepare('SELECT id FROM purchase_entries WHERE id = ? AND created_by = ?');
                    $check->bind_param('ii', $id, $purchaseUserId);
                    $check->execute();
                    if (!$check->get_result()->fetch_assoc()) {
                        $error = 'Không tìm thấy thông tin mua hàng hoặc bạn không có quyền sửa.';
                    } else {
                        header('Location: purchases.php?month=' . rawurlencode(substr($form['purchase_date'], 0, 7)) . '&success=updated');
                        exit;
                    }
                }
            } else {
                $stmt = $purchaseDb->prepare(
                    'INSERT INTO purchase_entries
                        (purchase_date, supplier, item_name, quantity, unit, total_amount, note, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'sssssssi',
                    $form['purchase_date'],
                    $form['supplier'],
                    $form['item_name'],
                    $quantity,
                    $form['unit'],
                    $totalAmount,
                    $form['note'],
                    $purchaseUserId
                );
                $stmt->execute();
                header('Location: purchases.php?month=' . rawurlencode(substr($form['purchase_date'], 0, 7)) . '&success=added');
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $purchaseDb->prepare(
        'SELECT id, purchase_date, supplier, item_name, quantity, unit, total_amount, note
         FROM purchase_entries WHERE id = ? AND created_by = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $editId, $purchaseUserId);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    if ($edit) {
        $form = $edit;
        $form['total_amount'] = number_format((float)$edit['total_amount'], 0, ',', '.');
    } else {
        $error = 'Không tìm thấy thông tin mua hàng hoặc bạn không có quyền sửa.';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit_advance'])) {
    $editAdvanceId = (int)$_GET['edit_advance'];
    $stmt = $purchaseDb->prepare(
        'SELECT id, advance_date, amount, note
         FROM purchase_advances WHERE id = ? AND created_by = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $editAdvanceId, $purchaseUserId);
    $stmt->execute();
    $editAdvance = $stmt->get_result()->fetch_assoc();
    if ($editAdvance) {
        $advanceForm = $editAdvance;
        $advanceForm['amount'] = number_format((float)$editAdvance['amount'], 0, ',', '.');
    } else {
        $error = 'Không tìm thấy khoản ứng hoặc bạn không có quyền sửa.';
    }
}

$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));
if (!purchaseValidMonth($selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$from = $selectedMonth . '-01';
$until = (new DateTimeImmutable($from))->format('Y-m-t');
$stmt = $purchaseDb->prepare(
    'SELECT id, purchase_date, supplier, item_name, quantity, unit, total_amount, note, created_at, updated_at
     FROM purchase_entries
     WHERE created_by = ? AND purchase_date BETWEEN ? AND ?
     ORDER BY purchase_date DESC, id DESC'
);
$stmt->bind_param('iss', $purchaseUserId, $from, $until);
$stmt->execute();
$purchaseEntries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$monthTotal = 0.0;
foreach ($purchaseEntries as $entry) {
    $monthTotal += (float)$entry['total_amount'];
}

$stmt = $purchaseDb->prepare(
    'SELECT id, advance_date, amount, note, created_at, updated_at
     FROM purchase_advances
     WHERE created_by = ? AND advance_date BETWEEN ? AND ?
     ORDER BY advance_date DESC, id DESC'
);
$stmt->bind_param('iss', $purchaseUserId, $from, $until);
$stmt->execute();
$advanceEntries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$monthAdvanceTotal = 0.0;
foreach ($advanceEntries as $entry) {
    $monthAdvanceTotal += (float)$entry['amount'];
}

$stmt = $purchaseDb->prepare(
    'SELECT
        (SELECT COALESCE(SUM(amount), 0) FROM purchase_advances WHERE created_by = ?) AS advance_total,
        (SELECT COALESCE(SUM(total_amount), 0) FROM purchase_entries WHERE created_by = ?) AS spent_total'
);
$stmt->bind_param('ii', $purchaseUserId, $purchaseUserId);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc();
$advanceTotal = (float)$balance['advance_total'];
$spentTotal = (float)$balance['spent_total'];
$remainingTotal = $advanceTotal - $spentTotal;

$activity = [];
foreach ($advanceEntries as $entry) {
    $entry['type'] = 'advance';
    $entry['event_date'] = $entry['advance_date'];
    $activity[] = $entry;
}
foreach ($purchaseEntries as $entry) {
    $entry['type'] = 'purchase';
    $entry['event_date'] = $entry['purchase_date'];
    $activity[] = $entry;
}
usort($activity, function (array $a, array $b): int {
    $dateCompare = strcmp($b['event_date'], $a['event_date']);
    if ($dateCompare !== 0) return $dateCompare;
    return strcmp((string)$b['created_at'], (string)$a['created_at']);
});
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="assets/purchasing.css">
<title>Nhập mua hàng — GHÉ</title>
</head>
<body>
<div class="purchase-shell">
  <nav class="purchase-nav">
    <span class="purchase-nav-brand">🧾 GHÉ · Mua hàng</span>
    <span class="purchase-nav-user">Tài khoản: <strong><?= purchaseH($purchaseAccount['username']) ?></strong></span>
    <a href="purchase_logout.php">Đăng xuất</a>
  </nav>

  <header class="purchase-header">
    <div><h1>Sổ ứng tiền &amp; mua hàng</h1><p>Theo dõi tiền đã nhận, tiền đã chi và số dư còn lại ngay trên một trang.</p></div>
  </header>

  <?php if ($error !== ''): ?><div class="purchase-alert purchase-alert-error"><?= purchaseH($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="purchase-alert purchase-alert-success"><?= purchaseH($success) ?></div><?php endif; ?>

  <section class="purchase-balance-grid" aria-label="Tổng hợp tiền">
    <div class="purchase-balance-card advance"><span>Tổng tiền đã ứng</span><strong><?= purchaseMoney($advanceTotal) ?></strong><small>Tất cả các lần nhận tiền</small></div>
    <div class="purchase-balance-card spent"><span>Tổng tiền đã chi</span><strong><?= purchaseMoney($spentTotal) ?></strong><small>Tất cả khoản mua hàng</small></div>
    <div class="purchase-balance-card remaining <?= $remainingTotal < 0 ? 'negative' : '' ?>"><span>Số tiền còn lại</span><strong><?= purchaseMoney($remainingTotal) ?></strong><small>Tiền ứng − tiền đã chi</small></div>
  </section>

  <?php if ($remainingTotal < 0): ?><div class="purchase-alert purchase-alert-error">Số tiền đã chi đang vượt quá tiền ứng <?= purchaseMoney(abs($remainingTotal)) ?>.</div><?php endif; ?>

  <div class="purchase-grid">
    <div class="purchase-form-stack">
    <section class="purchase-card purchase-advance-card">
      <h2 class="purchase-section-title"><?= (int)$advanceForm['id'] > 0 ? 'Sửa khoản ứng tiền' : 'Nhập khoản ứng tiền' ?></h2>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_advance">
        <input type="hidden" name="id" value="<?= (int)$advanceForm['id'] ?>">
        <div class="purchase-form-grid">
          <div>
            <label class="purchase-label" for="advance_date">Ngày nhận tiền</label>
            <input class="purchase-input" type="date" id="advance_date" name="advance_date" required value="<?= purchaseH($advanceForm['advance_date']) ?>">
          </div>
          <div>
            <label class="purchase-label" for="advance_amount">Số tiền ứng</label>
            <input class="purchase-input" type="text" id="advance_amount" name="amount" data-money-input inputmode="numeric" pattern="[0-9.]+" maxlength="13" required placeholder="0" autocomplete="off" value="<?= purchaseH((string)$advanceForm['amount']) ?>">
          </div>
          <div class="purchase-span-2">
            <label class="purchase-label" for="advance_note">Ghi chú</label>
            <input class="purchase-input" id="advance_note" name="advance_note" maxlength="1000" placeholder="Ví dụ: Ứng tiền đợt 1" value="<?= purchaseH($advanceForm['note']) ?>">
          </div>
        </div>
        <button class="purchase-button purchase-button-advance purchase-button-block" type="submit"><?= (int)$advanceForm['id'] > 0 ? 'Lưu khoản ứng' : 'Thêm khoản ứng tiền' ?></button>
        <?php if ((int)$advanceForm['id'] > 0): ?><div class="purchase-cancel"><a href="purchases.php?month=<?= purchaseH($selectedMonth) ?>">Hủy sửa</a></div><?php endif; ?>
      </form>
    </section>

    <section class="purchase-card">
      <h2 class="purchase-section-title"><?= (int)$form['id'] > 0 ? 'Sửa thông tin mua hàng' : 'Thêm thông tin mua hàng' ?></h2>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="purchase-form-grid">
          <div>
            <label class="purchase-label" for="purchase_date">Ngày mua</label>
            <input class="purchase-input" type="date" id="purchase_date" name="purchase_date" required value="<?= purchaseH($form['purchase_date']) ?>">
          </div>
          <div>
            <label class="purchase-label" for="supplier">Nhà cung cấp</label>
            <input class="purchase-input" id="supplier" name="supplier" maxlength="150" placeholder="Tên cửa hàng / người bán" value="<?= purchaseH($form['supplier']) ?>">
          </div>
          <div class="purchase-span-2">
            <label class="purchase-label" for="item_name">Nội dung mua hàng</label>
            <input class="purchase-input" id="item_name" name="item_name" maxlength="150" required placeholder="Ví dụ: 5 kg cà phê hạt" value="<?= purchaseH($form['item_name']) ?>">
          </div>
          <div>
            <label class="purchase-label" for="quantity">Số lượng</label>
            <input class="purchase-input" type="number" id="quantity" name="quantity" min="0.001" max="999999999" step="0.001" required value="<?= purchaseH((string)$form['quantity']) ?>">
          </div>
          <div>
            <label class="purchase-label" for="unit">Đơn vị</label>
            <input class="purchase-input" id="unit" name="unit" maxlength="30" placeholder="kg, thùng, chai..." value="<?= purchaseH($form['unit']) ?>">
          </div>
          <div class="purchase-span-2">
            <label class="purchase-label" for="total_amount">Tổng tiền</label>
            <input class="purchase-input" type="text" id="total_amount" name="total_amount" data-money-input inputmode="numeric" pattern="[0-9.]+" maxlength="13" required placeholder="0" autocomplete="off" value="<?= purchaseH((string)$form['total_amount']) ?>">
            <p class="purchase-help">Ví dụ: gõ 1000000, ô sẽ tự hiển thị 1.000.000</p>
          </div>
          <div class="purchase-span-2">
            <label class="purchase-label" for="note">Ghi chú</label>
            <textarea class="purchase-textarea" id="note" name="note" maxlength="1000" placeholder="Số hóa đơn, cách thanh toán hoặc ghi chú khác"><?= purchaseH($form['note']) ?></textarea>
          </div>
        </div>
        <button class="purchase-button purchase-button-primary purchase-button-block" type="submit"><?= (int)$form['id'] > 0 ? 'Lưu thay đổi' : 'Thêm khoản mua hàng' ?></button>
        <?php if ((int)$form['id'] > 0): ?><div class="purchase-cancel"><a href="purchases.php?month=<?= purchaseH($selectedMonth) ?>">Hủy sửa</a></div><?php endif; ?>
      </form>
    </section>
    </div>

    <section>
      <form class="purchase-filter" method="get">
        <div><label class="purchase-label" for="month">Tháng</label><input class="purchase-input" type="month" id="month" name="month" value="<?= purchaseH($selectedMonth) ?>" required></div>
        <button class="purchase-button purchase-button-secondary" type="submit">Xem</button>
      </form>
      <div class="purchase-month-summary">
        <div><span>Ứng trong tháng</span><strong class="positive">+<?= purchaseMoney($monthAdvanceTotal) ?></strong></div>
        <div><span>Chi trong tháng</span><strong class="negative">−<?= purchaseMoney($monthTotal) ?></strong></div>
        <div><span>Chênh lệch tháng</span><strong class="<?= $monthAdvanceTotal-$monthTotal < 0 ? 'negative' : 'positive' ?>"><?= $monthAdvanceTotal-$monthTotal >= 0 ? '+' : '−' ?><?= purchaseMoney(abs($monthAdvanceTotal-$monthTotal)) ?></strong></div>
      </div>
      <div class="purchase-table-wrap">
        <table class="purchase-table">
          <thead><tr><th>Ngày</th><th>Loại</th><th>Thông tin</th><th>Ghi chú</th><th class="money">Số tiền</th><th>Thao tác</th></tr></thead>
          <tbody>
          <?php if (!$activity): ?><tr><td colspan="6"><div class="purchase-empty">Chưa có khoản ứng hoặc mua hàng trong tháng này.</div></td></tr><?php endif; ?>
          <?php foreach ($activity as $entry): ?>
            <tr>
              <td><?= purchaseH((new DateTimeImmutable($entry['event_date']))->format('d/m/Y')) ?></td>
              <td><span class="purchase-type <?= $entry['type'] ?>"><?= $entry['type'] === 'advance' ? 'Ứng tiền' : 'Mua hàng' ?></span></td>
              <td><?php if ($entry['type'] === 'advance'): ?><div class="purchase-supplier">Nhận tiền ứng</div><?php else: ?><div class="purchase-supplier"><?= purchaseH($entry['item_name']) ?></div><div class="purchase-meta"><?= purchaseH(rtrim(rtrim((string)$entry['quantity'], '0'), '.')) ?> <?= purchaseH($entry['unit']) ?><?= trim((string)$entry['supplier']) !== '' ? ' · ' . purchaseH($entry['supplier']) : '' ?></div><?php endif; ?></td>
              <td class="note"><?= purchaseH($entry['note'] !== '' ? $entry['note'] : '—') ?></td>
              <td class="money <?= $entry['type'] === 'advance' ? 'money-in' : 'money-out' ?>"><?= $entry['type'] === 'advance' ? '+' . purchaseMoney($entry['amount']) : '−' . purchaseMoney($entry['total_amount']) ?></td>
              <td><div class="actions">
                <a class="purchase-button purchase-button-secondary purchase-button-small" href="purchases.php?month=<?= purchaseH($selectedMonth) ?>&amp;<?= $entry['type'] === 'advance' ? 'edit_advance' : 'edit' ?>=<?= (int)$entry['id'] ?>">Sửa</a>
                <form class="purchase-inline-form" method="post" onsubmit="return confirm('Xóa <?= $entry['type'] === 'advance' ? 'khoản ứng' : 'thông tin mua hàng' ?> này?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="<?= $entry['type'] === 'advance' ? 'delete_advance' : 'delete' ?>"><input type="hidden" name="id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="month" value="<?= purchaseH($selectedMonth) ?>">
                  <button class="purchase-button purchase-button-danger purchase-button-small" type="submit">Xóa</button>
                </form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<script>
(function () {
  function formatMoney(amount) {
    let digits = amount.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    amount.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }
  document.querySelectorAll('[data-money-input]').forEach(function (amount) {
    amount.addEventListener('input', function () { formatMoney(amount); });
    formatMoney(amount);
  });
})();
</script>
</body>
</html>
