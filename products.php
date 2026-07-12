<?php
include 'auth.php';
include 'config.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ── Import Excel ── */
if (isset($_POST['import_excel'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $rows = $spreadsheet->getActiveSheet()->toArray();
    $imported = 0;
    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        $category = trim($row[0]); $name = trim($row[1]);
        $price = (int)preg_replace('/[^0-9]/', '', $row[2]);
        if (empty($name)) continue;

        $stmt = $conn->prepare("SELECT id FROM categories WHERE name=?");
        $stmt->bind_param("s", $category); $stmt->execute();
        $cat_row = $stmt->get_result()->fetch_assoc();
        if ($cat_row) { $cid = $cat_row['id']; }
        else {
            $stmt = $conn->prepare("INSERT INTO categories(name) VALUES(?)");
            $stmt->bind_param("s", $category); $stmt->execute();
            $cid = $conn->insert_id;
        }
        $stmt = $conn->prepare("SELECT id FROM products WHERE name=?");
        $stmt->bind_param("s", $name); $stmt->execute();
        if ($stmt->get_result()->num_rows) continue;
        $stmt = $conn->prepare("INSERT INTO products(name,price,category_id) VALUES(?,?,?)");
        $stmt->bind_param("sii", $name, $price, $cid); $stmt->execute();
        $imported++;
    }
    header("Location: products.php?imported=$imported"); exit;
}

/* ── Add ── */
if (isset($_POST['add'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $name  = trim($_POST['name']);
    $price = (int)str_replace('.', '', $_POST['price']);
    $cid   = (int)$_POST['category_id'];
    $stmt  = $conn->prepare("INSERT INTO products(name,price,category_id) VALUES(?,?,?)");
    $stmt->bind_param("sii", $name, $price, $cid);
    $stmt->execute();
    header("Location: products.php"); exit;
}

/* ── Update ── */
if (isset($_POST['update'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $id    = (int)$_POST['id'];
    $name  = trim($_POST['name']);
    $price = (int)str_replace('.', '', $_POST['price']);
    $cid   = (int)$_POST['category_id'];
    $sort  = (int)$_POST['sort_order'];
    $stmt  = $conn->prepare("UPDATE products SET name=?,price=?,category_id=?,sort_order=? WHERE id=?");
    $stmt->bind_param("siiii", $name, $price, $cid, $sort, $id);
    $stmt->execute();
    header("Location: products.php"); exit;
}

/* ── Delete (soft) ── */
if (isset($_POST['delete'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $id   = (int)$_POST['delete'];
    $stmt = $conn->prepare("UPDATE products SET status=0 WHERE id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: products.php"); exit;
}

/* ── FIX: load categories once, cache in PHP array — removes N+1 query ── */
$catList = [];
$catResult = $conn->query("SELECT * FROM categories ORDER BY name");
while ($c = $catResult->fetch_assoc()) $catList[$c['id']] = $c['name'];

$products = $conn->query("
    SELECT p.*, c.name category_name
    FROM products p LEFT JOIN categories c ON p.category_id=c.id
    WHERE p.status=1 ORDER BY c.name, p.sort_order, p.name
");

$imported = (int)($_GET['imported'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thực đơn — GHÉ Coffee</title>
<style>
.prod-toolbar { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.edit-select  { font-size:12px; padding:4px 24px 4px 8px; border-radius:6px; border:1px solid var(--s200); background:var(--s50); appearance:none; cursor:pointer; }
.edit-input   { font-size:12px; padding:5px 8px; border-radius:6px; border:1px solid var(--s200); width:100%; background:var(--s50); }
.edit-input:focus, .edit-select:focus { border-color:var(--p); outline:none; background:var(--white); }
.money-input  { text-align:right; font-weight:600; }
.sort-input   { text-align:center; width:50px; }
.row-btns     { display:flex; gap:4px; }
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🍹 Quản lý thực đơn</h1>
      <p>Thêm, sửa, ẩn sản phẩm · <?= $products->num_rows ?> món đang hiển thị</p>
    </div>
  </div>

  <?php if ($imported > 0): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">✅ Import thành công <?= $imported ?> món mới</div>
  <?php endif; ?>

  <div class="grid g2" style="gap:14px;margin-bottom:20px;">

    <!-- Add form -->
    <div class="card">
      <div class="section-title">➕ Thêm món mới</div>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="form-group" style="flex:2;">
            <label class="form-label">Tên món</label>
            <input type="text" name="name" class="form-input" placeholder="Tên món" required>
          </div>
          <div class="form-group">
            <label class="form-label">Giá bán (đ)</label>
            <input type="text" name="price" class="form-input money-input" placeholder="Giá" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Danh mục</label>
          <select name="category_id" class="form-select">
            <?php foreach ($catList as $cid => $cname): ?>
            <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" name="add" class="btn btn-primary btn-block">Thêm món</button>
      </form>
    </div>

    <!-- Import Excel -->
    <div class="card">
      <div class="section-title">📊 Import từ Excel</div>
      <p class="text-muted text-sm" style="margin-bottom:12px;">
        File .xlsx có 3 cột: <b>Danh mục</b> · <b>Tên món</b> · <b>Giá</b><br>
        Hàng đầu là tiêu đề, bỏ qua tự động. Tên trùng sẽ được bỏ qua.
      </p>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Chọn file Excel</label>
          <input type="file" name="excel_file" class="form-input" accept=".xlsx,.xls" required style="padding:7px;">
        </div>
        <button type="submit" name="import_excel" class="btn btn-secondary btn-block">📥 Import menu</button>
      </form>
    </div>
  </div>

  <!-- Filter toolbar -->
  <div class="prod-toolbar">
    <select id="catFilter" class="form-select" style="width:200px;" onchange="filterProducts()">
      <option value="">📂 Tất cả danh mục</option>
      <?php foreach ($catList as $cid => $cname): ?>
      <option value="<?= htmlspecialchars($cname) ?>"><?= htmlspecialchars($cname) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" id="searchProd" class="form-input" placeholder="🔍 Tìm món..." style="flex:1;max-width:320px;" oninput="filterProducts()">
    <span id="countLabel" class="text-sm text-muted" style="align-self:center;"></span>
  </div>

  <!-- Products table -->
  <div class="tbl-wrap">
    <table class="tbl" id="productTable">
      <thead>
        <tr>
          <th style="width:160px;">Danh mục</th>
          <th>Tên món</th>
          <th style="width:110px;">Giá</th>
          <th style="width:60px;text-align:center;">Thứ tự</th>
          <th style="width:90px;">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php $products->data_seek(0); while ($row = $products->fetch_assoc()): ?>
        <tr class="product-row" data-cat="<?= htmlspecialchars($row['category_name']) ?>" data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <td>
              <select name="category_id" class="edit-select">
                <?php foreach ($catList as $cid => $cname): ?>
                <option value="<?= $cid ?>" <?= $cid == $row['category_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cname) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <input type="text" name="name" class="edit-input"
                     value="<?= htmlspecialchars($row['name']) ?>" required>
            </td>
            <td>
              <input type="text" name="price" class="edit-input money-input"
                     value="<?= number_format($row['price']) ?>">
            </td>
            <td>
              <input type="number" name="sort_order" class="edit-input sort-input"
                     value="<?= $row['sort_order'] ?>" min="0">
            </td>
            <td>
              <div class="row-btns">
                <button type="submit" name="update" class="btn btn-sm btn-success" title="Lưu">💾</button>
                <button type="submit" name="delete" value="<?= $row['id'] ?>"
                        class="btn btn-sm btn-danger" title="Ẩn"
                        onclick="return confirm('Ẩn món «<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>»?')">🗑</button>
              </div>
            </td>
          </form>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
/* Format money inputs */
document.querySelectorAll('.money-input').forEach(el => {
  el.addEventListener('input', function() {
    const v = this.value.replace(/\D/g,'');
    this.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
});

/* Filter */
function filterProducts() {
  const kw  = document.getElementById('searchProd').value.trim().toLowerCase();
  const cat = document.getElementById('catFilter').value.trim().toLowerCase();
  const rows = document.querySelectorAll('.product-row');
  let visible = 0;
  rows.forEach(r => {
    const matchCat  = !cat || (r.dataset.cat || '').toLowerCase().includes(cat);
    const matchName = !kw  || r.dataset.name.includes(kw);
    const show = matchCat && matchName;
    r.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('countLabel').textContent = visible + ' món';
}
filterProducts();
</script>
</body>
</html>
