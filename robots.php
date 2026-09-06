<?php
require __DIR__ . '/site/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
// Exclude data and build folders. Private PHP pages use noindex headers and authentication.
foreach (['site/', 'tools/', '.git/', 'DB/', 'data/', 'vendor/', 'web-pos-'] as $path) {
    echo 'Disallow: ' . ghe_link($path) . "\n";
}
echo "\nSitemap: " . ghe_url('sitemap.xml') . "\n";
