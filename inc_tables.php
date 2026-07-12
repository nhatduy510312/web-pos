<?php
/**
 * renderTablesHtml($conn)
 * Renders the table-selection grid used in index.php + table_status.php.
 * Uses one JOIN query to avoid N+1 and escapes all output.
 */
function renderTablesHtml($conn)
{
    $tables = $conn->query("
        SELECT
            t.id, t.table_name,
            o.id          AS order_id,
            o.customer_name,
            o.total_amount,
            (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS total_items
        FROM tables t
        LEFT JOIN orders o ON o.table_id = t.id AND o.status = 'open'
        ORDER BY t.id
    ");

    while ($t = $tables->fetch_assoc()) {
        $busy  = !empty($t['order_id']);
        $id    = (int)$t['id'];
        $name  = htmlspecialchars($t['table_name'], ENT_QUOTES);
        $cname = htmlspecialchars($t['customer_name'] ?? '', ENT_QUOTES);
        $items = (int)($t['total_items'] ?? 0);
        $amt   = $busy ? number_format($t['total_amount']) : '';

        $stateClass = $busy ? 'tbl-occupied' : 'tbl-empty';
        $stateLabel = $busy ? 'Có khách' : 'Trống';
        ?>
        <div class="table-btn <?= $stateClass ?>"
             id="table_<?= $id ?>"
             onclick="selectTable(<?= $id ?>)">
          <div class="tbl-name"><?= $name ?></div>
          <?php if ($busy): ?>
            <div class="tbl-badge"><?= $items ?> món · <?= $amt ?> đ</div>
            <?php if ($cname): ?>
              <div class="tbl-cust">👤 <?= $cname ?></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="tbl-badge"><?= $stateLabel ?></div>
          <?php endif; ?>
        </div>
        <?php
    }
}
