<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

$categoryError = (string)($_GET['error'] ?? '');
$categoryDeleted = isset($_GET['deleted']);
$detachedProducts = max(0, (int)($_GET['detached'] ?? 0));

if (isset($_POST['add'])) {
    $name = trim($_POST['name']);
    if ($name) {
        $stmt = $conn->prepare("INSERT INTO categories(name) VALUES(?)");
        $stmt->bind_param("s", $name); $stmt->execute();
    }
    header("Location: categories.php"); exit;
}

if (isset($_POST['update'])) {
    $id   = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $id); $stmt->execute();
    header("Location: categories.php"); exit;
}

if (isset($_POST['delete'])) {
    $id = (int)$_POST['delete'];
    $usage = $conn->prepare(
        "SELECT
            SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) active_count,
            SUM(CASE WHEN status<>1 THEN 1 ELSE 0 END) inactive_count
         FROM products WHERE category_id=?"
    );
    $usage->bind_param("i", $id);
    $usage->execute();
    $counts = $usage->get_result()->fetch_assoc();
    $activeCount = (int)($counts['active_count'] ?? 0);
    $inactiveCount = (int)($counts['inactive_count'] ?? 0);

    if ($activeCount > 0) {
        header("Location: categories.php?error=active_products"); exit;
    }

    $conn->begin_transaction();
    try {
        if ($inactiveCount > 0) {
            $detach = $conn->prepare("UPDATE products SET category_id=NULL WHERE category_id=? AND status<>1");
            $detach->bind_param("i", $id);
            $detach->execute();
        }
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không tìm thấy danh mục cần xóa.');
        }
        $conn->commit();
        header("Location: categories.php?deleted=1&detached=$inactiveCount"); exit;
    } catch (Throwable $exception) {
        $conn->rollback();
        error_log('Category deletion failed: ' . $exception->getMessage());
        header("Location: categories.php?error=delete_failed"); exit;
    }
}

$categories = $conn->query("
    SELECT c.*,
           SUM(CASE WHEN p.status=1 THEN 1 ELSE 0 END) prod_count,
           SUM(CASE WHEN p.id IS NOT NULL AND p.status<>1 THEN 1 ELSE 0 END) hidden_count
    FROM categories c
    LEFT JOIN products p ON p.category_id=c.id
    GROUP BY c.id ORDER BY c.name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Danh mục — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>📂 Danh mục</h1>
      <p>Quản lý nhóm sản phẩm</p>
    </div>
  </div>

  <?php if ($categoryDeleted): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">
    ✅ Đã xóa danh mục<?= $detachedProducts > 0 ? ' và tách ' . $detachedProducts . ' món đã ẩn khỏi danh mục' : '' ?>.
  </div>
  <?php elseif ($categoryError === 'active_products'): ?>
  <div class="alert alert-warn" style="margin-bottom:14px;">
    ⚠️ Không thể xóa vì danh mục vẫn còn món đang hoạt động. Hãy ẩn hoặc chuyển các món đó sang danh mục khác trước.
  </div>
  <?php elseif ($categoryError === 'delete_failed'): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">
    ⚠️ Không thể xóa danh mục. Vui lòng thử lại hoặc kiểm tra ràng buộc database.
  </div>
  <?php endif; ?>

  <div class="grid g2" style="gap:14px;">

    <!-- Add form -->
    <div class="card">
      <div class="section-title">➕ Thêm danh mục</div>
      <form method="post" class="form-row">
        <?= csrf_field() ?>
        <div class="form-group" style="flex:1;">
          <input type="text" name="name" class="form-input" placeholder="Tên danh mục" required>
        </div>
        <button type="submit" name="add" class="btn btn-primary">Thêm</button>
      </form>
    </div>

    <!-- Category list -->
    <div class="card">
      <div class="section-title">📋 Danh sách (<?= $categories->num_rows ?>)</div>
      <?php while ($row = $categories->fetch_assoc()): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--s100);">
        <form method="post" style="display:flex;gap:8px;flex:1;align-items:center;margin:0;">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <input type="text" name="name" class="form-input" style="flex:1;"
                 value="<?= htmlspecialchars($row['name']) ?>" required>
          <span class="badge badge-gray"><?= $row['prod_count'] ?> món</span>
          <?php if ((int)$row['hidden_count'] > 0): ?>
          <span class="badge badge-gray" title="Các món này sẽ được tách khỏi danh mục khi xóa"><?= $row['hidden_count'] ?> đã ẩn</span>
          <?php endif; ?>
          <button type="submit" name="update" class="btn btn-sm btn-success">Lưu</button>
        </form>
        <form method="post" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" name="delete" value="<?= $row['id'] ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Xóa danh mục này?')"
                  <?= $row['prod_count'] > 0 ? 'disabled title="Có món đang dùng"' : '' ?>>🗑</button>
        </form>
      </div>
      <?php endwhile; ?>
    </div>

  </div>
</div>
</body>
</html>
