<?php
$_NAV_PAGE = basename($_SERVER['PHP_SELF']);
$_NAV_ROLE = $_SESSION['role'] ?? '';
?>
<style>
/* ================================================================
   GHÉ COFFEE AND TEA — Design System v2
   Injected via menu.php on every page.
================================================================ */

/* ── Fonts ── */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* ── Tokens ── */
:root {
  --p:         #2563eb;
  --p-dk:      #1d4ed8;
  --p-lt:      #eff6ff;
  --p-bd:      #bfdbfe;
  --ok:        #16a34a;
  --ok-lt:     #f0fdf4;
  --ok-bd:     #bbf7d0;
  --err:       #dc2626;
  --err-lt:    #fef2f2;
  --err-bd:    #fecaca;
  --warn:      #d97706;
  --warn-lt:   #fffbeb;
  --warn-bd:   #fde68a;
  --s50:       #f8fafc;
  --s100:      #f1f5f9;
  --s200:      #e2e8f0;
  --s300:      #cbd5e1;
  --s400:      #94a3b8;
  --s500:      #64748b;
  --s700:      #334155;
  --s900:      #0f172a;
  --white:     #ffffff;
  --bg:        #f1f5f9;
  --r-xs:      4px;
  --r-sm:      8px;
  --r:         10px;
  --r-md:      12px;
  --r-lg:      16px;
  --r-xl:      20px;
  --r-2xl:     24px;
  --sh-sm:     0 1px 2px rgba(0,0,0,.05);
  --sh:        0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
  --sh-md:     0 4px 12px rgba(0,0,0,.07);
  --sh-lg:     0 8px 24px rgba(0,0,0,.08);
  --font:      'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --tr:        .15s ease;
}

/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--s900);
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
img { max-width: 100%; display: block; }
button { font-family: inherit; cursor: pointer; }
input, select, textarea { font-family: inherit; font-size: inherit; }
h1,h2,h3,h4 { font-weight: 700; line-height: 1.2; }

/* ── Layout ── */
.page-content { padding: 16px 20px 32px; }

/* ── Navigation ── */
.nav {
  display: flex;
  align-items: center;
  gap: 2px;
  background: var(--white);
  border-radius: var(--r-xl);
  margin: 10px 12px 0;
  padding: 7px 10px;
  box-shadow: var(--sh);
  border: 1px solid var(--s200);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  position: sticky;
  top: 10px;
  z-index: 100;
}
.nav::-webkit-scrollbar { display: none; }

.nav-brand {
  font-size: 14px;
  font-weight: 700;
  color: var(--p);
  white-space: nowrap;
  padding: 6px 12px;
  letter-spacing: -.01em;
  flex-shrink: 0;
}
.nav-sep {
  width: 1px; height: 18px;
  background: var(--s200);
  flex-shrink: 0;
  margin: 0 6px;
}
.nav a {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--s500);
  padding: 6px 11px;
  border-radius: var(--r-sm);
  white-space: nowrap;
  transition: var(--tr);
  flex-shrink: 0;
}
.nav a:hover { background: var(--s100); color: var(--s700); }
.nav a.active {
  background: var(--p-lt);
  color: var(--p);
  font-weight: 600;
}
.nav-spacer { flex: 1; min-width: 8px; }
.nav-logout a { color: var(--err); }
.nav-logout a:hover { background: var(--err-lt); color: var(--err); }
.nav-time {
  font-size: 12px;
  font-weight: 600;
  color: var(--s500);
  white-space: nowrap;
  padding: 4px 10px;
  background: var(--s100);
  border-radius: var(--r-sm);
  flex-shrink: 0;
  letter-spacing: .02em;
}

/* ── Cards ── */
.card {
  background: var(--white);
  border-radius: var(--r-lg);
  padding: 20px;
  box-shadow: var(--sh);
  border: 1px solid var(--s200);
}
.card + .card { margin-top: 14px; }

/* ── Stat cards ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.stat-card {
  background: var(--white);
  border-radius: var(--r-lg);
  padding: 18px 20px;
  box-shadow: var(--sh);
  border: 1px solid var(--s200);
}
.stat-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--s400);
}
.stat-value {
  font-size: 26px;
  font-weight: 700;
  margin-top: 6px;
  line-height: 1;
  letter-spacing: -.02em;
}
.stat-sub { font-size: 12px; color: var(--s400); margin-top: 5px; }
.c-blue   { color: var(--p); }
.c-green  { color: var(--ok); }
.c-red    { color: var(--err); }
.c-orange { color: var(--warn); }
.c-purple { color: #7c3aed; }

/* ── Page header ── */
.page-hdr {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.page-hdr-left h1 {
  font-size: 18px;
  font-weight: 700;
  color: var(--s900);
}
.page-hdr-left p {
  font-size: 13px;
  color: var(--s500);
  margin-top: 2px;
}

/* ── Buttons ── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: var(--r-sm);
  border: 1px solid transparent;
  transition: var(--tr);
  white-space: nowrap;
  cursor: pointer;
  line-height: 1;
  appearance: none;
}
.btn-primary  { background: var(--p);       color: #fff; border-color: var(--p); }
.btn-primary:hover  { background: var(--p-dk); border-color: var(--p-dk); }
.btn-secondary{ background: var(--white);   color: var(--s700); border-color: var(--s200); }
.btn-secondary:hover{ background: var(--s100); }
.btn-danger   { background: var(--err-lt);  color: var(--err); border-color: var(--err-bd); }
.btn-danger:hover   { background: #fecaca; }
.btn-success  { background: var(--ok-lt);   color: var(--ok); border-color: var(--ok-bd); }
.btn-success:hover  { background: #bbf7d0; }
.btn-ghost    { background: transparent;    color: var(--s700); }
.btn-ghost:hover    { background: var(--s100); }
.btn-sm { font-size: 12px; padding: 5px 10px; border-radius: var(--r-xs); }
.btn-lg { font-size: 15px; padding: 12px 24px; border-radius: var(--r-md); }
.btn-xl { font-size: 16px; padding: 14px 28px; border-radius: var(--r-md); font-weight: 700; }
.btn-block { width: 100%; justify-content: center; }
.btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }

/* ── Badges ── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}
.badge-blue   { background: var(--p-lt);   color: var(--p); }
.badge-green  { background: var(--ok-lt);  color: var(--ok); }
.badge-red    { background: var(--err-lt); color: var(--err); }
.badge-yellow { background: var(--warn-lt);color: var(--warn); }
.badge-gray   { background: var(--s100);   color: var(--s500); }

/* ── Forms ── */
.form-group { margin-bottom: 14px; }
.form-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--s500);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-bottom: 5px;
}
.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 9px 12px;
  font-size: 13.5px;
  color: var(--s900);
  background: var(--white);
  border: 1px solid var(--s200);
  border-radius: var(--r-sm);
  transition: border-color var(--tr), box-shadow var(--tr);
  outline: none;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  border-color: var(--p);
  box-shadow: 0 0 0 3px var(--p-lt);
}
.form-input::placeholder { color: var(--s400); }
.form-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2394a3b8'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 16px;
  padding-right: 32px;
  cursor: pointer;
}
.form-row {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.form-row .form-group { flex: 1; min-width: 120px; margin-bottom: 0; }
.form-row .btn { flex-shrink: 0; height: 38px; }

/* ── Tables ── */
.tbl-wrap {
  border-radius: var(--r-lg);
  border: 1px solid var(--s200);
  overflow: hidden;
  overflow-x: auto;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  background: var(--white);
}
.tbl thead th {
  background: var(--s50);
  color: var(--s500);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 10px 14px;
  text-align: left;
  border-bottom: 1px solid var(--s200);
  white-space: nowrap;
}
.tbl td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--s100);
  color: var(--s700);
  vertical-align: middle;
}
.tbl tbody tr:last-child td { border-bottom: none; }
.tbl tbody tr:hover { background: var(--s50); }

/* ── Divider ── */
.divider { height: 1px; background: var(--s200); margin: 16px 0; }

/* ── Section title ── */
.section-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--s900);
  margin-bottom: 12px;
}

/* ── Alerts ── */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  border-radius: var(--r-sm);
  font-size: 13px;
  font-weight: 500;
  border: 1px solid transparent;
}
.alert-err  { background: var(--err-lt); color: var(--err); border-color: var(--err-bd); }
.alert-ok   { background: var(--ok-lt);  color: var(--ok);  border-color: var(--ok-bd); }
.alert-info { background: var(--p-lt);   color: var(--p);   border-color: var(--p-bd); }
.alert-warn { background: var(--warn-lt);color: var(--warn);border-color: var(--warn-bd); }

/* ── Grids / helpers ── */
.grid   { display: grid; gap: 14px; }
.g2     { grid-template-columns: repeat(2, 1fr); }
.g3     { grid-template-columns: repeat(3, 1fr); }
.g4     { grid-template-columns: repeat(4, 1fr); }
.g-auto { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
.flex   { display: flex; gap: 8px; align-items: center; }
.flex-b { display: flex; justify-content: space-between; align-items: center; }
.flex-e { display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
.gap-4  { gap: 4px; } .gap-8 { gap: 8px; } .gap-12 { gap: 12px; }
.mt-4   { margin-top: 4px; }
.mt-8   { margin-top: 8px; }
.mt-12  { margin-top: 12px; }
.mt-16  { margin-top: 16px; }
.mt-20  { margin-top: 20px; }
.text-sm    { font-size: 12px; }
.text-muted { color: var(--s500); }
.text-right { text-align: right; }
.fw-600 { font-weight: 600; }
.fw-700 { font-weight: 700; }

/* ── Money display ── */
.money { font-feature-settings: "tnum"; font-variant-numeric: tabular-nums; }

/* ── Payment method pills ── */
.pm-cash   { color: var(--ok);   background: var(--ok-lt);  }
.pm-bank   { color: var(--p);    background: var(--p-lt);   }
.pm-mixed  { color: var(--warn); background: var(--warn-lt);}

/* ── Empty state ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: var(--s400);
  font-size: 13px;
  gap: 8px;
}
.empty-state-icon { font-size: 36px; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .g3, .g4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .g2, .g3, .g4 { grid-template-columns: 1fr; }
  .page-content  { padding: 12px 12px 24px; }
  .nav { margin: 8px 8px 0; }
}
/* ── Print ── */
@media print {
  .nav, .no-print { display: none !important; }
  body { background: white; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    if (!form.querySelector('input[name="csrf_token"]')) {
      const token = document.createElement('input');
      token.type = 'hidden'; token.name = 'csrf_token';
      token.value = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';
      form.appendChild(token);
    }
  });
});
</script>

<nav class="nav">
  <span class="nav-brand">☕ GHÉ</span>
  <div class="nav-sep"></div>

  <a href="index.php"            class="<?= $_NAV_PAGE === 'index.php'            ? 'active' : '' ?>">🛒 POS</a>
  <a href="dashboard.php"        class="<?= $_NAV_PAGE === 'dashboard.php'        ? 'active' : '' ?>">📊 Dashboard</a>
  <a href="report.php"           class="<?= $_NAV_PAGE === 'report.php'           ? 'active' : '' ?>">📈 Báo cáo</a>
  <a href="cashbook.php"         class="<?= $_NAV_PAGE === 'cashbook.php'         ? 'active' : '' ?>">📒 Chốt ca</a>
  <a href="cashbook_history.php" class="<?= $_NAV_PAGE === 'cashbook_history.php' ? 'active' : '' ?>">📚 Lịch sử</a>
  <a href="products.php"         class="<?= in_array($_NAV_PAGE, ['products.php', 'recipes.php'], true) ? 'active' : '' ?>">🍹 Thực đơn</a>
  <a href="ingredients.php"      class="<?= $_NAV_PAGE === 'ingredients.php'      ? 'active' : '' ?>">🧪 Nguyên liệu</a>
  <a href="categories.php"       class="<?= $_NAV_PAGE === 'categories.php'       ? 'active' : '' ?>">📂 Danh mục</a>
  <a href="attendance.php"       class="<?= $_NAV_PAGE === 'attendance.php'       ? 'active' : '' ?>">🕒 Chấm công</a>
  <?php if ($_NAV_ROLE === 'admin'): ?>
  <a href="employees.php"        class="<?= $_NAV_PAGE === 'employees.php'        ? 'active' : '' ?>">👥 Nhân viên</a>
  <a href="salary_report.php"    class="<?= $_NAV_PAGE === 'salary_report.php'    ? 'active' : '' ?>">💰 Lương</a>
  <?php endif; ?>

  <div class="nav-spacer"></div>
  <span class="nav-time" id="navClock"></span>
  <div class="nav-sep"></div>
  <div class="nav-logout">
    <a href="logout.php" onclick="return confirm('Đăng xuất khỏi hệ thống?')">🚪 Đăng xuất</a>
  </div>
</nav>

<script>
(function(){
  function tick(){
    const d = new Date();
    const p = n => String(n).padStart(2,'0');
    document.getElementById('navClock').textContent =
      p(d.getHours()) + ':' + p(d.getMinutes()) + ' · ' +
      p(d.getDate()) + '/' + p(d.getMonth()+1);
  }
  tick();
  setInterval(tick, 30000);
})();
</script>
