<?php
include 'auth.php';
include 'config.php';

$products = $conn->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 1
    ORDER BY c.name, p.sort_order, p.name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>POS — GHÉ Coffee</title>
<style>
/* ════════════════════════════════
   DESKTOP: 3-column grid
   ════════════════════════════════ */
.pos-wrap {
  display: grid;
  grid-template-columns: 170px 1fr 300px;
  gap: 12px;
  padding: 12px 14px 20px;
  height: calc(100vh - 62px);
  overflow: hidden;
}

/* ── Tables column ── */
.col-tables {
  background: var(--white);
  border-radius: var(--r-lg);
  border: 1px solid var(--s200);
  box-shadow: var(--sh);
  overflow-y: auto;
  padding: 12px 10px;
  scrollbar-width: thin;
  scrollbar-color: var(--s200) transparent;
}
.col-tables-hdr {
  font-size: 11px; font-weight: 700;
  color: var(--s400); text-transform: uppercase;
  letter-spacing: .06em; margin-bottom: 10px; padding: 0 2px;
}
.table-btn {
  width: 100%; border-radius: var(--r-sm);
  padding: 10px; margin-bottom: 6px;
  cursor: pointer; text-align: center;
  border: 2px solid transparent; transition: var(--tr);
  user-select: none;
}
.tbl-empty    { background: var(--s100); color: var(--s500); }
.tbl-empty:hover { background: var(--s200); color: var(--s700); }
.tbl-occupied { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.tbl-occupied:hover { background: #ffedd5; }
.table-btn.selected { border-color: var(--p) !important; box-shadow: 0 0 0 2px var(--p-lt); }
.tbl-name  { font-size: 13px; font-weight: 700; }
.tbl-badge { font-size: 11px; margin-top: 3px; font-weight: 500; }
.tbl-cust  { font-size: 11px; margin-top: 2px; opacity: .8; }

/* ── Products column ── */
.col-products {
  display: flex; flex-direction: column; gap: 10px; overflow: hidden;
}
.prod-toolbar { display: flex; gap: 8px; flex-shrink: 0; }
.prod-search {
  flex: 1; padding: 9px 14px;
  border: 1px solid var(--s200); border-radius: var(--r-sm);
  font-size: 13px; outline: none; background: var(--white);
  transition: var(--tr); font-family: var(--font);
}
.prod-search:focus { border-color: var(--p); box-shadow: 0 0 0 3px var(--p-lt); }
.cat-pills {
  display: flex; gap: 5px; flex-wrap: nowrap;
  overflow-x: auto; flex-shrink: 0; padding-bottom: 2px;
  scrollbar-width: none;
}
.cat-pills::-webkit-scrollbar { display: none; }
.cat-pill {
  flex-shrink: 0; font-size: 12px; font-weight: 600;
  padding: 5px 12px; border-radius: 999px;
  background: var(--white); color: var(--s500);
  border: 1px solid var(--s200); cursor: pointer; transition: var(--tr);
  white-space: nowrap;
}
.cat-pill:hover { background: var(--s100); color: var(--s700); }
.cat-pill.active { background: var(--p); color: #fff; border-color: var(--p); }
.prod-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
  gap: 8px; overflow-y: auto; padding-right: 2px;
  scrollbar-width: thin; scrollbar-color: var(--s200) transparent;
}
.prod-card {
  background: var(--white); border: 1px solid var(--s200);
  border-radius: var(--r-md); padding: 14px 12px;
  cursor: pointer; transition: var(--tr); text-align: center; user-select: none;
}
.prod-card:hover { border-color: var(--p-bd); background: var(--p-lt); transform: translateY(-1px); box-shadow: var(--sh-md); }
.prod-card:active { transform: scale(.97); }
.prod-name  { font-size: 13px; font-weight: 600; color: var(--s900); line-height: 1.3; }
.prod-price { font-size: 14px; font-weight: 700; color: var(--ok); margin-top: 5px; }
.cat-label  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--s400); padding: 4px 0; grid-column: 1/-1; }

/* ── Cart column ── */
.col-cart {
  background: var(--white); border-radius: var(--r-lg);
  border: 1px solid var(--s200); box-shadow: var(--sh);
  display: flex; flex-direction: column; overflow: hidden;
}
.cart-hdr {
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--s200); flex-shrink: 0;
}
.cart-hdr h2 { font-size: 14px; font-weight: 700; color: var(--s900); }
.customer-input {
  width: 100%; padding: 7px 10px; font-size: 12.5px;
  border: 1px solid var(--s200); border-radius: var(--r-sm);
  margin-top: 8px; outline: none; font-family: var(--font); transition: var(--tr);
}
.customer-input:focus { border-color: var(--p); box-shadow: 0 0 0 3px var(--p-lt); }
.customer-input::placeholder { color: var(--s400); }
.cart-items { flex: 1; overflow-y: auto; padding: 0 14px; scrollbar-width: thin; }
.cart-item { padding: 10px 0; border-bottom: 1px solid var(--s100); }
.cart-item:last-child { border-bottom: none; }
.cart-item-name { font-size: 13px; font-weight: 600; color: var(--s900); }
.cart-item-note {
  width: 100%; margin-top: 5px; padding: 5px 8px; font-size: 12px;
  border: 1px solid var(--s200); border-radius: var(--r-xs);
  color: var(--s700); outline: none; font-family: var(--font);
  background: var(--s50); transition: var(--tr);
}
.cart-item-note:focus { border-color: var(--p); background: var(--white); }
.cart-item-note::placeholder { color: var(--s400); }
.cart-item-row { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; gap: 6px; }
.qty-ctrl { display: flex; align-items: center; gap: 4px; background: var(--s100); border-radius: 999px; padding: 2px; }
.qty-btn {
  width: 28px; height: 28px; border: none; border-radius: 50%;
  background: var(--white); font-size: 16px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--s700); box-shadow: var(--sh-sm); transition: var(--tr);
}
.qty-btn:hover { background: var(--p); color: #fff; }
.qty-num { font-size: 13px; font-weight: 700; min-width: 22px; text-align: center; color: var(--s900); }
.cart-item-price { font-size: 13px; font-weight: 600; color: var(--s700); }
.cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 120px; color: var(--s400); font-size: 13px; gap: 6px; }
.cart-footer { border-top: 1px solid var(--s200); padding: 10px 14px 14px; flex-shrink: 0; }
.total-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
.total-label { font-size: 12px; font-weight: 600; color: var(--s500); text-transform: uppercase; letter-spacing: .04em; }
.total-value { font-size: 22px; font-weight: 700; color: var(--p); letter-spacing: -.02em; }
.discount-row { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
.discount-row select {
  flex: 1; padding: 7px 10px; font-size: 12px;
  border: 1px solid var(--s200); border-radius: var(--r-sm);
  outline: none; font-family: var(--font); appearance: none; background: var(--s50); cursor: pointer;
}
.discount-row input {
  width: 90px; padding: 7px 10px; font-size: 12px;
  border: 1px solid var(--s200); border-radius: var(--r-sm);
  text-align: right; outline: none; font-family: var(--font); background: var(--s50);
}
.discount-row select:focus, .discount-row input:focus { border-color: var(--p); background: var(--white); }
.discount-info { display: flex; justify-content: space-between; font-size: 12px; color: var(--s500); margin-bottom: 6px; }
.discount-info .d-val { color: var(--err); font-weight: 600; }
.final-total { display: flex; justify-content: space-between; align-items: baseline; padding: 8px 10px; background: var(--p-lt); border-radius: var(--r-sm); margin-bottom: 10px; }
.final-total-label { font-size: 13px; font-weight: 700; color: var(--p); }
.final-total-val { font-size: 20px; font-weight: 700; color: var(--p); letter-spacing: -.02em; }
.pay-methods { display: flex; gap: 5px; margin-bottom: 8px; }
.pay-method { flex: 1; padding: 7px 4px; text-align: center; font-size: 12px; font-weight: 600; border-radius: var(--r-sm); background: var(--s100); color: var(--s500); border: 2px solid transparent; cursor: pointer; transition: var(--tr); user-select: none; }
.pay-method.active { background: var(--p-lt); color: var(--p); border-color: var(--p); }
.pay-section { margin-bottom: 8px; }
.pay-input { width: 100%; padding: 8px 11px; font-size: 13px; border: 1px solid var(--s200); border-radius: var(--r-sm); outline: none; text-align: right; font-weight: 600; font-family: var(--font); background: var(--s50); transition: var(--tr); }
.pay-input:focus { border-color: var(--p); background: var(--white); box-shadow: 0 0 0 3px var(--p-lt); }
.pay-input::placeholder { text-align: left; font-weight: 400; color: var(--s400); }
.change-box { display: flex; justify-content: space-between; align-items: center; padding: 7px 10px; background: var(--ok-lt); border-radius: var(--r-sm); margin-bottom: 8px; font-size: 12px; }
.change-box .c-label { color: var(--ok); font-weight: 600; }
.change-box .c-val { font-size: 15px; font-weight: 700; color: var(--ok); }
.pay-row { display: flex; gap: 6px; margin-bottom: 6px; }
.pay-row label { font-size: 12px; color: var(--s500); font-weight: 600; margin-bottom: 4px; display: block; }
.pay-row > div { flex: 1; }
.checkout-btn {
  width: 100%; padding: 13px; background: var(--p); color: #fff;
  font-size: 15px; font-weight: 700; border: none; border-radius: var(--r-md);
  cursor: pointer; transition: var(--tr); font-family: var(--font); letter-spacing: .01em;
}
.checkout-btn:hover { background: var(--p-dk); }
.checkout-btn:active { transform: scale(.99); }

/* ════════════════════════════════
   MOBILE: tab-based layout
   ════════════════════════════════ */
.mob-tab-bar { display: none; }

@media (max-width: 768px) {
  /* Reset desktop layout */
  .pos-wrap {
    display: block;
    height: auto;
    overflow: visible;
    padding: 8px 8px 80px; /* bottom padding for tab bar */
  }

  /* Each column becomes a panel, hidden by default */
  .col-tables,
  .col-products,
  .col-cart {
    display: none;
    border-radius: var(--r-lg);
    height: auto;
    overflow: visible;
    max-height: none;
  }

  /* Active panel is shown */
  .col-tables.mob-active,
  .col-products.mob-active,
  .col-cart.mob-active  { display: flex; flex-direction: column; }

  .col-tables.mob-active  { display: block; max-height: calc(100vh - 160px); overflow-y: auto; }
  .col-products.mob-active { min-height: calc(100vh - 160px); }
  .col-cart.mob-active    { min-height: calc(100vh - 160px); }

  /* Products grid: 2 cols on mobile */
  .prod-grid {
    grid-template-columns: repeat(2, 1fr);
    overflow-y: visible;
    max-height: none;
  }

  /* Cart items scroll inside fixed layout */
  .cart-items { max-height: 40vh; }

  /* Tab bar at bottom */
  .mob-tab-bar {
    display: flex;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: var(--white);
    border-top: 1px solid var(--s200);
    box-shadow: 0 -4px 16px rgba(0,0,0,.08);
    z-index: 200;
    padding: 4px 0 env(safe-area-inset-bottom, 4px);
  }
  .mob-tab {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 3px; padding: 8px 4px;
    font-size: 11px; font-weight: 600; color: var(--s400);
    cursor: pointer; transition: var(--tr); position: relative;
    border: none; background: none; font-family: var(--font);
  }
  .mob-tab .tab-icon { font-size: 20px; line-height: 1; }
  .mob-tab.active { color: var(--p); }
  .mob-tab.active .tab-icon { transform: scale(1.1); }
  .mob-tab-badge {
    position: absolute; top: 4px; right: 22%;
    background: var(--err); color: #fff;
    font-size: 10px; font-weight: 700;
    min-width: 16px; height: 16px;
    border-radius: 999px; display: none;
    align-items: center; justify-content: center;
    padding: 0 4px;
  }
  .mob-tab-badge.show { display: flex; }

  /* Bigger touch targets on mobile */
  .qty-btn { width: 34px; height: 34px; font-size: 18px; }
  .prod-card { padding: 12px 8px; }
  .prod-name { font-size: 12px; }
  .prod-price { font-size: 13px; }
  .checkout-btn { padding: 15px; font-size: 16px; }

  /* Table grid: 2 cols */
  .col-tables { padding: 12px; }
  .table-btn { display: inline-block; width: calc(50% - 4px); margin: 3px; vertical-align: top; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>

<!-- Mobile tab bar -->
<div class="mob-tab-bar">
  <button class="mob-tab active" id="tabTables" onclick="mobTab('tables')">
    <span class="tab-icon">🪑</span>
    Bàn
  </button>
  <button class="mob-tab" id="tabProducts" onclick="mobTab('products')">
    <span class="tab-icon">🍹</span>
    Thực đơn
  </button>
  <button class="mob-tab" id="tabCart" onclick="mobTab('cart')">
    <span class="tab-icon">🛒</span>
    <span id="cartTabBadge" class="mob-tab-badge"></span>
    Đơn
  </button>
</div>

<div class="pos-wrap">

  <!-- ── Tables ── -->
  <div class="col-tables mob-active" id="colTables">
    <div class="col-tables-hdr">🪑 Bàn</div>
    <?php
    include 'inc_tables.php';
    renderTablesHtml($conn);
    ?>
  </div>

  <!-- ── Products ── -->
  <div class="col-products" id="colProducts">
    <div class="prod-toolbar">
      <input type="text" id="search" class="prod-search" placeholder="🔍 Tìm món...">
    </div>

    <div class="cat-pills" id="catPills">
      <button class="cat-pill active" onclick="filterCat('all')">Tất cả</button>
      <?php
      $cats = $conn->query("SELECT * FROM categories ORDER BY name");
      while ($cat = $cats->fetch_assoc()):
      ?>
      <button class="cat-pill" onclick="filterCat('<?= $cat['id'] ?>')">
        <?= htmlspecialchars($cat['name']) ?>
      </button>
      <?php endwhile; ?>
    </div>

    <div class="prod-grid" id="prodGrid">
      <?php
      $curCat = '';
      while ($row = $products->fetch_assoc()):
        if ($curCat !== $row['category_name']) {
          $curCat = $row['category_name'];
          echo '<div class="cat-label">' . htmlspecialchars($curCat) . '</div>';
        }
      ?>
      <div class="prod-card product-item"
           data-cat="<?= $row['category_id'] ?>"
           data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
           onclick="addItem(<?= $row['id'] ?>,'<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>',<?= $row['price'] ?>)">
        <div class="prod-name"><?= htmlspecialchars($row['name']) ?></div>
        <div class="prod-price"><?= number_format($row['price']) ?>đ</div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- ── Cart ── -->
  <div class="col-cart" id="colCart">
    <div class="cart-hdr">
      <div class="flex-b">
        <h2>🛒 Đơn hàng</h2>
        <span id="tableLabel" class="badge badge-gray">Chưa chọn bàn</span>
      </div>
      <input type="text" id="customerName" class="customer-input"
             placeholder="Tên khách..." oninput="saveCustomerName()">
    </div>

    <div class="cart-items" id="cartItems">
      <div class="cart-empty">🛒<br>Chưa có món nào</div>
    </div>

    <div class="cart-footer">
      <div class="total-row">
        <span class="total-label">Tổng</span>
        <span class="total-value money" id="total">0đ</span>
      </div>

      <div class="discount-row">
        <select id="discountType" onchange="calcDiscount()">
          <option value="amount">💵 Giảm tiền</option>
          <option value="percent">% Giảm %</option>
        </select>
        <input type="number" id="discountValue" min="0" placeholder="0" oninput="calcDiscount()">
      </div>
      <div class="discount-info">
        <span>Giảm giá</span>
        <span class="d-val money" id="discountAmt">0đ</span>
      </div>
      <div class="final-total">
        <span class="final-total-label">Thanh toán</span>
        <span class="final-total-val money" id="finalTotal">0đ</span>
      </div>

      <div class="pay-methods">
        <div class="pay-method active" data-val="cash" onclick="selectPay('cash')">💵 TM</div>
        <div class="pay-method" data-val="bank" onclick="selectPay('bank')">🏦 CK</div>
        <div class="pay-method" data-val="mixed" onclick="selectPay('mixed')">🔀 Hỗn hợp</div>
      </div>

      <div class="pay-section" id="cashSection">
        <input type="text" id="cashReceived" class="pay-input"
               placeholder="Tiền khách đưa" oninput="formatMoney(this);calcChange()">
        <div class="change-box mt-8">
          <span class="c-label">Tiền thừa</span>
          <span class="c-val money" id="changeAmt">0đ</span>
        </div>
      </div>

      <div class="pay-section" id="mixedSection" style="display:none">
        <div class="pay-row">
          <div>
            <label>💵 Tiền mặt</label>
            <input type="text" id="mixedCash" class="pay-input" placeholder="0" oninput="formatMoney(this)">
          </div>
          <div>
            <label>🏦 Chuyển khoản</label>
            <input type="text" id="mixedBank" class="pay-input" placeholder="0" oninput="formatMoney(this)">
          </div>
        </div>
      </div>

      <button class="checkout-btn" onclick="checkout()">Thanh toán ›</button>
    </div>
  </div>

</div><!-- .pos-wrap -->

<script>
const CSRF = '<?= csrf_token() ?>';
let tableId = null;
let orderId = null;
let payMode = 'cash';
const isMobile = () => window.innerWidth <= 768;

/* ── Mobile tabs ── */
function mobTab(tab) {
  document.querySelectorAll('.mob-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
  document.getElementById('colTables').classList.toggle('mob-active', tab === 'tables');
  document.getElementById('colProducts').classList.toggle('mob-active', tab === 'products');
  document.getElementById('colCart').classList.toggle('mob-active', tab === 'cart');
  window.scrollTo(0, 0);
}

/* ── Helpers ── */
function esc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}
function fmt(n)  { return Number(n).toLocaleString('vi-VN'); }
function parseMoney(s) { return parseFloat(String(s).replace(/\./g,'').replace(/,/g,'')) || 0; }
function formatMoney(el) {
  const v = el.value.replace(/[^0-9]/g,'');
  el.value = v === '' ? '' : Number(v).toLocaleString('vi-VN');
}
function $id(id) { return document.getElementById(id); }

/* ── Update cart tab badge ── */
function updateCartBadge(count) {
  const badge = $id('cartTabBadge');
  if (!badge) return;
  badge.textContent = count > 0 ? count : '';
  badge.classList.toggle('show', count > 0);
}

/* ── Table selection ── */
function selectTable(id) {
  tableId = id;
  document.querySelectorAll('.table-btn').forEach(e => e.classList.remove('selected'));
  const el = $id('table_' + id);
  if (el) el.classList.add('selected');
  $id('tableLabel').textContent = 'Bàn ' + id;
  $id('tableLabel').className = 'badge badge-blue';

  fetch('api_order.php?action=get_or_create&table_id=' + id)
    .then(r => r.json())
    .then(d => {
      orderId = d.order_id;
      renderCart();
      // On mobile: switch to products tab after selecting a table
      if (isMobile()) mobTab('products');
    });
}

function refreshTables() {
  fetch('table_status.php?v=' + Date.now())
    .then(r => r.text())
    .then(html => {
      document.querySelector('.col-tables').innerHTML = html;
      if (tableId) {
        const el = $id('table_' + tableId);
        if (el) el.classList.add('selected');
      }
    });
}

/* ── Add item ── */
function addItem(pid, name, price) {
  if (!tableId) {
    if (isMobile()) {
      alert('Chọn bàn trước (tab Bàn)');
      mobTab('tables');
    } else {
      alert('Vui lòng chọn bàn trước');
    }
    return;
  }

  const doAdd = () => {
    fetch('api_order.php?action=add_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ order_id: orderId, product_id: pid, csrf_token: CSRF })
    }).then(r => r.json()).then(() => { renderCart(); refreshTables(); });
  };

  if (!orderId) {
    fetch('api_order.php?action=create_order', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ table_id: tableId, csrf_token: CSRF })
    }).then(r => r.json()).then(d => { orderId = d.order_id; doAdd(); });
  } else {
    doAdd();
  }
}

/* ── Render cart ── */
function renderCart() {
  if (!orderId) {
    $id('cartItems').innerHTML = '<div class="cart-empty">🛒<br>Chưa có món nào</div>';
    $id('total').textContent = '0đ';
    $id('customerName').value = '';
    updateCartBadge(0);
    calcDiscount();
    return;
  }

  fetch('api_order.php?action=load&order_id=' + orderId)
    .then(r => r.json())
    .then(data => {
      $id('customerName').value = data.customer_name || '';

      if (!data.items.length) {
        $id('cartItems').innerHTML = '<div class="cart-empty">🛒<br>Chưa có món nào</div>';
        updateCartBadge(0);
      } else {
        $id('cartItems').innerHTML = data.items.map(item => `
          <div class="cart-item">
            <div class="cart-item-name">${esc(item.name)}</div>
            <input class="cart-item-note" type="text"
                   placeholder="Ghi chú..."
                   value="${esc(item.note ?? '')}"
                   onchange="updateNote(${item.id},this.value)">
            <div class="cart-item-row">
              <div class="qty-ctrl">
                <button class="qty-btn" onclick="updateQty(${item.id},'minus')">−</button>
                <span class="qty-num">${item.qty}</span>
                <button class="qty-btn" onclick="updateQty(${item.id},'plus')">+</button>
              </div>
              <span class="cart-item-price money">${fmt(item.qty * item.unit_price)}đ</span>
            </div>
          </div>
        `).join('');
        const totalQty = data.items.reduce((s, i) => s + i.qty, 0);
        updateCartBadge(totalQty);
      }

      $id('total').textContent = fmt(data.total) + 'đ';
      calcDiscount();
    });
}

/* ── Qty / Note ── */
function updateQty(itemId, mode) {
  fetch('api_order.php?action=update_qty', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ item_id: itemId, mode, csrf_token: CSRF })
  }).then(r => r.json()).then(() => { renderCart(); refreshTables(); });
}

function updateNote(itemId, note) {
  fetch('api_order.php?action=update_note', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'item_id=' + itemId + '&note=' + encodeURIComponent(note) + '&csrf_token=' + encodeURIComponent(CSRF)
  });
}

function saveCustomerName() {
  if (!orderId) return;
  const name = $id('customerName').value;
  fetch('api_order.php?action=update_customer', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ order_id: orderId, customer_name: name, csrf_token: CSRF })
  }).then(r => r.json()).then(() => {
    refreshTables();
    setTimeout(() => {
      const el = $id('table_' + tableId);
      if (el) el.classList.add('selected');
    }, 200);
  });
}

/* ── Payment ── */
function selectPay(mode) {
  payMode = mode;
  document.querySelectorAll('.pay-method').forEach(e => {
    e.classList.toggle('active', e.dataset.val === mode);
  });
  $id('cashSection').style.display  = mode === 'cash'  ? 'block' : 'none';
  $id('mixedSection').style.display = mode === 'mixed' ? 'block' : 'none';
}

function calcDiscount() {
  const raw   = parseMoney($id('total').textContent);
  const type  = $id('discountType').value;
  const val   = parseFloat($id('discountValue').value) || 0;
  let disc    = type === 'percent' ? raw * val / 100 : val;
  if (disc > raw) disc = raw;
  const final = raw - disc;
  $id('discountAmt').textContent = fmt(Math.round(disc)) + 'đ';
  $id('finalTotal').textContent  = fmt(Math.round(final)) + 'đ';
}

function calcChange() {
  const total = parseMoney($id('finalTotal').textContent);
  const recv  = parseMoney($id('cashReceived').value);
  $id('changeAmt').textContent = fmt(Math.max(0, recv - total)) + 'đ';
}

/* ── Search & filter ── */
function removeAccents(s) {
  return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'')
          .replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase();
}
$id('search').addEventListener('keyup', function() {
  const kw = removeAccents(this.value.trim());
  let lastLabel = null;
  document.querySelectorAll('#prodGrid > *').forEach(el => {
    if (el.classList.contains('cat-label')) {
      lastLabel = el;
      el.style.display = kw ? 'none' : '';
      return;
    }
    const match = !kw || removeAccents(el.dataset.name || '').includes(kw);
    el.style.display = match ? '' : 'none';
    if (match && lastLabel && kw) lastLabel.style.display = '';
  });
});

function filterCat(catId) {
  document.querySelectorAll('.cat-pill').forEach(b => {
    b.classList.toggle('active', b.getAttribute('onclick').includes("'" + catId + "'"));
  });
  document.querySelectorAll('.product-item').forEach(el => {
    el.style.display = catId === 'all' || el.dataset.cat == catId ? '' : 'none';
  });
  document.querySelectorAll('.cat-label').forEach(el => {
    let next = el.nextElementSibling;
    let visible = false;
    while (next && !next.classList.contains('cat-label')) {
      if (next.style.display !== 'none') visible = true;
      next = next.nextElementSibling;
    }
    el.style.display = visible ? '' : 'none';
  });
}

/* ── Checkout ── */
function checkout() {
  if (!orderId) {
    alert('Vui lòng chọn bàn trước');
    if (isMobile()) mobTab('tables');
    return;
  }

  const raw  = parseMoney($id('total').textContent);
  const type = $id('discountType').value;
  const dVal = parseFloat($id('discountValue').value) || 0;
  let disc   = type === 'percent' ? raw * dVal / 100 : dVal;
  if (disc > raw) disc = raw;
  const total = raw - disc;

  let cash = 0, bank = 0;
  if (payMode === 'cash')      { cash = total; }
  else if (payMode === 'bank') { bank = total; }
  else {
    cash = parseMoney($id('mixedCash').value);
    bank = parseMoney($id('mixedBank').value);
    if (cash + bank < total) { alert('Chưa đủ số tiền thanh toán'); return; }
  }

  fetch('checkout_v3.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      order_id: orderId, payment_method: payMode,
      cash_amount: cash, bank_amount: bank,
      discount_type: type, discount_value: dVal,
      discount_amount: Math.round(disc), csrf_token: CSRF
    })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) window.location.href = 'receipt.php?id=' + d.order_id;
    else alert('Lỗi: ' + (d.message || 'Không thể thanh toán'));
  })
  .catch(() => alert('Lỗi kết nối'));
}
</script>
</body>
</html>
