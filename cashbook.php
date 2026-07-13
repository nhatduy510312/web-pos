<?php
include 'auth.php';
include 'config.php';
requireRole(['admin', 'staff', 'user']);
requireCsrfForFormPost();

$today = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) $today = date('Y-m-d');

/* ── BUG FIX: use prepared statements throughout ── */
function cbQuery($conn, $sql, $types, ...$params) {
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

$closed = cbQuery($conn,
    "SELECT id FROM cashbook_history WHERE report_date=? LIMIT 1", "s", $today
)->num_rows > 0;

/* ── POST handlers ── */
if (isset($_POST['close_day'])) {
    $stmt = $conn->prepare("INSERT INTO cashbook_history(report_date,opening_cash,cash_revenue,transfer_revenue,expenses,deposits,closing_cash) VALUES(?,0,0,0,0,0,0) ON DUPLICATE KEY UPDATE report_date=report_date");
    $stmt->bind_param("s", $today);
    $stmt->execute();
}

if (isset($_POST['delete_expense']) && !$closed) {
    $id = (int)$_POST['delete_expense'];
    $stmt = $conn->prepare("DELETE FROM cash_expenses WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: cashbook.php?date=$today"); exit;
}

if (isset($_POST['delete_deposit']) && !$closed) {
    $id = (int)$_POST['delete_deposit'];
    $stmt = $conn->prepare("DELETE FROM cash_deposit WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: cashbook.php?date=$today"); exit;
}

if (isset($_POST['save_opening']) && !$closed) {
    $amount = (float)str_replace('.', '', $_POST['amount']);
    $stmt = $conn->prepare("INSERT INTO cash_opening(opening_date,amount) VALUES(?,?) ON DUPLICATE KEY UPDATE amount=?");
    $stmt->bind_param("sdd", $today, $amount, $amount);
    $stmt->execute();
}

if (isset($_POST['add_expense']) && !$closed) {
    $amount = (float)str_replace('.', '', $_POST['amount']);
    $note   = trim($_POST['note']);
    $stmt = $conn->prepare("INSERT INTO cash_expenses(expense_date,amount,note) VALUES(?,?,?)");
    $stmt->bind_param("sds", $today, $amount, $note);
    $stmt->execute();
}

if (isset($_POST['add_deposit']) && !$closed) {
    $amount = (float)str_replace('.', '', $_POST['amount']);
    $note   = trim($_POST['note']);
    $stmt = $conn->prepare("INSERT INTO cash_deposit(deposit_date,amount,note) VALUES(?,?,?)");
    $stmt->bind_param("sds", $today, $amount, $note);
    $stmt->execute();
}

if (isset($_POST['reopen_day'])) {
    $stmt = $conn->prepare("DELETE FROM cashbook_history WHERE report_date=?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    header("Location: cashbook.php?date=$today"); exit;
}

/* ── Fetch data ── */
$openingRow = cbQuery($conn, "SELECT amount FROM cash_opening WHERE opening_date=?", "s", $today)->fetch_assoc();
$openingCash = 0;
if ($openingRow) {
    $openingCash = (float)$openingRow['amount'];
} else {
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $last = cbQuery($conn, "SELECT closing_cash FROM cashbook_history WHERE report_date=? LIMIT 1", "s", $yesterday)->fetch_assoc();
    $openingCash = (float)($last['closing_cash'] ?? 0);
}

$cashRevenue     = (float)(cbQuery($conn, "SELECT IFNULL(SUM(cash_amount),0) s FROM orders WHERE status='paid' AND DATE(paid_at)=?", "s", $today)->fetch_assoc()['s']);
$transferRevenue = (float)(cbQuery($conn, "SELECT IFNULL(SUM(bank_amount),0) s FROM orders WHERE status='paid' AND DATE(paid_at)=?", "s", $today)->fetch_assoc()['s']);
$expenses        = (float)(cbQuery($conn, "SELECT IFNULL(SUM(amount),0) s FROM cash_expenses WHERE expense_date=?", "s", $today)->fetch_assoc()['s']);
$deposits        = (float)(cbQuery($conn, "SELECT IFNULL(SUM(amount),0) s FROM cash_deposit WHERE deposit_date=?", "s", $today)->fetch_assoc()['s']);
$totalRevenue    = $cashRevenue + $transferRevenue;
$closingCash     = $openingCash + $cashRevenue - $expenses - $deposits;

if (isset($_POST['close_day'])) {
    $stmt = $conn->prepare("UPDATE cashbook_history SET opening_cash=?,cash_revenue=?,transfer_revenue=?,expenses=?,deposits=?,closing_cash=? WHERE report_date=?");
    $stmt->bind_param("dddddds", $openingCash, $cashRevenue, $transferRevenue, $expenses, $deposits, $closingCash, $today);
    $stmt->execute();
    header("Location: cashbook.php?date=$today"); exit;
}

$listExpense = cbQuery($conn, "SELECT * FROM cash_expenses WHERE expense_date=? ORDER BY id DESC", "s", $today);
$listDeposit = cbQuery($conn, "SELECT * FROM cash_deposit WHERE deposit_date=? ORDER BY id DESC", "s", $today);

/* ── Date nav ── */
$prevDay = date('Y-m-d', strtotime($today . ' -1 day'));
$nextDay = date('Y-m-d', strtotime($today . ' +1 day'));
$isToday = $today === date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chốt ca — GHÉ Coffee</title>
<style>
.cb-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.cb-summary { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
.sum-item { background:var(--s50); border-radius:var(--r-sm); padding:12px 14px; border:1px solid var(--s200); }
.sum-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--s400); }
.sum-val { font-size:18px; font-weight:700; margin-top:4px; }
.locked-banner {
  display:flex;align-items:center;gap:10px;
  background:var(--ok-lt);color:var(--ok);
  border:1px solid var(--ok-bd);
  border-radius:var(--r-sm);padding:10px 14px;
  font-size:13px;font-weight:600;margin-bottom:14px;
}
.inline-form { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-top:12px; }
.inline-form .form-input { width:auto; }
.money-input { text-align:right; font-weight:600; }
.list-item {
  display:flex;align-items:center;justify-content:space-between;
  padding:9px 0;border-bottom:1px solid var(--s100);
  font-size:13px;gap:10px;
}
.list-item:last-child { border-bottom:none; }
.li-amt { font-weight:700;color:var(--s900);white-space:nowrap; }
.li-note { color:var(--s500);flex:1; }
.date-nav { display:flex;align-items:center;gap:8px; }
.date-nav a {
  padding:5px 10px;border-radius:var(--r-sm);background:var(--white);
  border:1px solid var(--s200);color:var(--s700);font-size:12px;
  font-weight:600;transition:var(--tr);
}
.date-nav a:hover { background:var(--s100); }
@media(max-width:640px){ .cb-grid{grid-template-columns:1fr;} }
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <!-- Header -->
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>
        📒 Chốt ca — <?= date('d/m/Y', strtotime($today)) ?>
        <?php if ($closed): ?>
        <span class="badge badge-green" style="font-size:12px;vertical-align:middle;">✅ Đã chốt</span>
        <?php endif; ?>
      </h1>
    </div>
    <div class="flex gap-8">
      <div class="date-nav">
        <a href="?date=<?= $prevDay ?>">‹ Hôm trước</a>
        <form method="get" style="margin:0;">
          <input type="date" name="date" class="form-input" style="width:auto;padding:5px 10px;font-size:13px;"
                 value="<?= $today ?>" onchange="this.form.submit()">
        </form>
        <?php if (!$isToday): ?>
        <a href="?date=<?= $nextDay ?>">Hôm sau ›</a>
        <?php endif; ?>
      </div>
      <a href="cashbook_history.php" class="btn btn-secondary btn-sm">📚 Lịch sử</a>
    </div>
  </div>

  <?php if ($closed): ?>
  <div class="locked-banner">🔒 Ca này đã được chốt. Dữ liệu chỉ đọc.</div>
  <?php endif; ?>

  <!-- Summary cards -->
  <div class="cb-summary">
    <div class="sum-item">
      <div class="sum-label">TM đầu ca</div>
      <div class="sum-val money"><?= number_format($openingCash) ?>đ</div>
    </div>
    <div class="sum-item">
      <div class="sum-label">DT tiền mặt</div>
      <div class="sum-val c-green money"><?= number_format($cashRevenue) ?>đ</div>
    </div>
    <div class="sum-item">
      <div class="sum-label">DT chuyển khoản</div>
      <div class="sum-val c-blue money"><?= number_format($transferRevenue) ?>đ</div>
    </div>
    <div class="sum-item">
      <div class="sum-label">Tổng doanh thu</div>
      <div class="sum-val c-green money"><?= number_format($totalRevenue) ?>đ</div>
    </div>
    <div class="sum-item">
      <div class="sum-label">Chi tiền</div>
      <div class="sum-val c-red money"><?= number_format($expenses) ?>đ</div>
    </div>
    <div class="sum-item">
      <div class="sum-label">Nộp DT</div>
      <div class="sum-val c-orange money"><?= number_format($deposits) ?>đ</div>
    </div>
    <div class="sum-item" style="background:var(--p-lt);border-color:var(--p-bd);">
      <div class="sum-label" style="color:var(--p);">TM cuối ca</div>
      <div class="sum-val c-blue money"><?= number_format($closingCash) ?>đ</div>
    </div>
  </div>

  <div class="cb-grid">

    <!-- Opening cash -->
    <div class="card">
      <div class="section-title">💵 Tiền mặt đầu ca</div>
      <?php if (!$closed): ?>
      <form method="post">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Số tiền</label>
            <input type="text" name="amount" class="form-input money-input"
                   value="<?= number_format($openingCash) ?>" required style="width:160px;">
          </div>
          <button type="submit" name="save_opening" class="btn btn-primary">Lưu</button>
        </div>
      </form>
      <?php else: ?>
      <div class="fw-700 money" style="font-size:18px;"><?= number_format($openingCash) ?>đ</div>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center;gap:10px;">
      <?php if (!$closed): ?>
      <form method="post" onsubmit="return confirm('Chốt ca ngày <?= date('d/m/Y', strtotime($today)) ?>?')">
        <button type="submit" name="close_day" class="btn btn-success btn-lg">
          📒 Chốt ca ngày này
        </button>
      </form>
      <p class="text-muted text-sm">TM cuối ca sẽ được ghi lại</p>
      <?php else: ?>
      <form method="post" onsubmit="return confirm('Mở lại để chỉnh sửa?')">
        <button type="submit" name="reopen_day" class="btn btn-danger btn-lg">🔓 Mở lại ca</button>
      </form>
      <p class="text-muted text-sm">Cho phép chỉnh sửa lại</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Expenses & Deposits -->
  <div class="cb-grid">

    <!-- Expenses -->
    <div class="card">
      <div class="section-title">💸 Chi tiền</div>
      <?php if ($listExpense->num_rows > 0): ?>
      <?php while ($e = $listExpense->fetch_assoc()): ?>
      <div class="list-item">
        <span class="li-amt money"><?= number_format($e['amount']) ?>đ</span>
        <span class="li-note"><?= htmlspecialchars($e['note']) ?></span>
        <?php if (!$closed): ?>
        <form method="post" onsubmit="return confirm('Xóa khoản này?')" style="margin:0;">
          <input type="hidden" name="delete_expense" value="<?= $e['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">🗑</button>
        </form>
        <?php else: ?>
        <span class="badge badge-gray">🔒</span>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>
      <?php else: ?>
      <div class="text-muted text-sm" style="padding:8px 0;">Chưa có khoản chi</div>
      <?php endif; ?>

      <?php if (!$closed): ?>
      <form method="post" class="inline-form" style="border-top:1px solid var(--s100);margin-top:10px;padding-top:10px;">
        <div>
          <input type="text" name="amount" class="form-input money-input" placeholder="Số tiền" required style="width:130px;">
        </div>
        <div style="flex:1;">
          <input type="text" name="note" class="form-input" placeholder="Nội dung chi">
        </div>
        <button type="submit" name="add_expense" class="btn btn-primary">+ Thêm</button>
      </form>
      <?php else: ?>
      <div class="alert alert-warn mt-8">🔒 Ca đã chốt, không thể thêm</div>
      <?php endif; ?>
    </div>

    <!-- Deposits -->
    <div class="card">
      <div class="section-title">🏦 Nộp doanh thu</div>
      <?php if ($listDeposit->num_rows > 0): ?>
      <?php while ($d = $listDeposit->fetch_assoc()): ?>
      <div class="list-item">
        <span class="li-amt money"><?= number_format($d['amount']) ?>đ</span>
        <span class="li-note"><?= htmlspecialchars($d['note']) ?></span>
        <?php if (!$closed): ?>
        <form method="post" onsubmit="return confirm('Xóa khoản này?')" style="margin:0;">
          <input type="hidden" name="delete_deposit" value="<?= $d['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">🗑</button>
        </form>
        <?php else: ?>
        <span class="badge badge-gray">🔒</span>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>
      <?php else: ?>
      <div class="text-muted text-sm" style="padding:8px 0;">Chưa có khoản nộp</div>
      <?php endif; ?>

      <?php if (!$closed): ?>
      <form method="post" class="inline-form" style="border-top:1px solid var(--s100);margin-top:10px;padding-top:10px;">
        <div>
          <input type="text" name="amount" class="form-input money-input" placeholder="Số tiền" required style="width:130px;">
        </div>
        <div style="flex:1;">
          <input type="text" name="note" class="form-input" placeholder="Nội dung nộp">
        </div>
        <button type="submit" name="add_deposit" class="btn btn-primary">+ Thêm</button>
      </form>
      <?php else: ?>
      <div class="alert alert-warn mt-8">🔒 Ca đã chốt, không thể thêm</div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
document.querySelectorAll('.money-input').forEach(el => {
  el.addEventListener('input', function() {
    const v = this.value.replace(/\D/g,'');
    this.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
});
</script>
</body>
</html>
