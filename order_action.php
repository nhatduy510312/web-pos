<?php
include 'auth.php';
include 'config.php';

$id     = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'edit';
if ($id <= 0) { header('Location: report.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
$stmt->bind_param("i", $id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header('Location: report.php'); exit; }

/* ── Void / Delete (admin only) ── */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    requireRole('admin');
    $uid  = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("UPDATE orders SET status='voided',voided_by=?,voided_at=NOW() WHERE id=?");
    $stmt->bind_param("ii", $uid, $id); $stmt->execute();
    header('Location: report.php'); exit;
}

/* ── Update payment ── */
if (isset($_POST['save'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $pm    = in_array($_POST['payment_method'], ['cash','bank','mixed']) ? $_POST['payment_method'] : 'cash';
    $total = (float)$order['total_amount'];
    if ($pm === 'cash')     { $cash = $total; $bank = 0; }
    elseif ($pm === 'bank') { $cash = 0; $bank = $total; }
    else                    { $cash = (float)$_POST['cash_amount']; $bank = $total - $cash; }

    $stmt = $conn->prepare("UPDATE orders SET payment_method=?,cash_amount=?,bank_amount=? WHERE id=?");
    $stmt->bind_param("sddi", $pm, $cash, $bank, $id); $stmt->execute();
    header('Location: report.php?edited=' . $id); exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sửa hóa đơn #<?= $id ?> — GHÉ Coffee</title>
<style>
.pay-radio-group { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
.pay-radio-item {
  display:flex; align-items:center; gap:10px;
  padding:12px 14px; border-radius:var(--r-sm);
  border:2px solid var(--s200); cursor:pointer;
  transition:var(--tr); background:var(--s50);
}
.pay-radio-item:has(input:checked) {
  border-color:var(--p); background:var(--p-lt);
}
.pay-radio-item input { margin:0; accent-color:var(--p); }
.pay-radio-item label { cursor:pointer; font-size:14px; font-weight:500; }
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content" style="max-width:520px;">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>Sửa hóa đơn #<?= $id ?></h1>
      <p><?= date('d/m/Y H:i', strtotime($order['paid_at'] ?? $order['created_at'])) ?></p>
    </div>
    <a href="report.php" class="btn btn-ghost btn-sm">← Quay lại</a>
  </div>

  <!-- Summary -->
  <div class="card" style="margin-bottom:14px;">
    <div class="flex-b">
      <span class="text-muted fw-600">Tổng thanh toán</span>
      <span class="fw-700 c-blue money" style="font-size:22px;">
        <?= number_format($order['total_amount']) ?>đ
      </span>
    </div>
  </div>

  <!-- Edit form -->
  <div class="card">
    <div class="section-title">🏦 Cập nhật phương thức thanh toán</div>
    <form method="post">
      <?= csrf_field() ?>

      <div class="pay-radio-group" id="payGroup">
        <?php foreach (['cash' => '💵 Tiền mặt', 'bank' => '🏦 Chuyển khoản', 'mixed' => '🔀 Hỗn hợp'] as $val => $label): ?>
        <div class="pay-radio-item">
          <input type="radio" id="pm_<?= $val ?>" name="payment_method" value="<?= $val ?>"
                 <?= $order['payment_method'] === $val ? 'checked' : '' ?>
                 onchange="toggleMixed()">
          <label for="pm_<?= $val ?>"><?= $label ?></label>
        </div>
        <?php endforeach; ?>
      </div>

      <div id="mixedFields" style="display:none;margin-top:12px;">
        <div class="form-group">
          <label class="form-label">💵 Số tiền mặt</label>
          <input type="number" name="cash_amount" id="cashAmt" class="form-input money-input"
                 min="0" step="1000" placeholder="0">
        </div>
      </div>

      <div style="margin-top:16px;display:flex;gap:8px;">
        <button type="submit" name="save" class="btn btn-primary" style="flex:1;">Lưu thay đổi</button>
        <a href="order_detail.php?id=<?= $id ?>" class="btn btn-secondary">Chi tiết</a>
      </div>
    </form>
  </div>

  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
  <div class="card" style="margin-top:14px;border-color:var(--err-bd);">
    <div class="section-title" style="color:var(--err);">⚠️ Huỷ hóa đơn</div>
    <p class="text-muted text-sm" style="margin:8px 0 12px;">
      Hóa đơn sẽ bị đánh dấu "đã huỷ" và không tính vào doanh thu. Thao tác không thể hoàn tác.
    </p>
    <form method="post" action="order_action.php?action=delete&id=<?= $id ?>"
          onsubmit="return confirm('Huỷ hóa đơn #<?= $id ?>? Không thể hoàn tác.')">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger btn-sm">🗑 Huỷ hóa đơn #<?= $id ?></button>
    </form>
  </div>
  <?php endif; ?>

</div>

<script>
function toggleMixed() {
  const pm = document.querySelector('input[name="payment_method"]:checked')?.value;
  document.getElementById('mixedFields').style.display = pm === 'mixed' ? 'block' : 'none';
}
toggleMixed();
</script>
</body>
</html>
