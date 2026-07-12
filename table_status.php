<?php
include 'auth.php';
include 'config.php';
include 'inc_tables.php';
echo '<h3 style="font-size:13px;font-weight:700;color:var(--s500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">🪑 Bàn</h3>';
renderTablesHtml($conn);
