<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

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
    $id   = (int)$_POST['delete'];
    $used = $conn->prepare("SELECT id FROM products WHERE category_id=? LIMIT 1");
    $used->bind_param("i", $id); $used->execute();
    if (!$used->get_result()->num_rows) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id); $stmt->execute();
    }
    header("Location: categories.php"); exit;
}

$categories = $conn->query("
    SELECT c.*, COUNT(p.id) prod_count
    FROM categories c
    LEFT JOIN products p ON p.category_id=c.id AND p.status=1
    GROUP BY c.id ORDER BY c.name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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

  <div class="grid g2" style="gap:14px;">

    <!-- Add form -->
    <div class="card">
      <div class="section-title">➕ Thêm danh mục</div>
      <form method="post" class="form-row">
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
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <input type="text" name="name" class="form-input" style="flex:1;"
                 value="<?= htmlspecialchars($row['name']) ?>" required>
          <span class="badge badge-gray"><?= $row['prod_count'] ?> món</span>
          <button type="submit" name="update" class="btn btn-sm btn-success">Lưu</button>
        </form>
        <form method="post" style="margin:0;">
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
