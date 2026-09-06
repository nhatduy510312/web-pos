<?php
// Verified business identity: Google Maps link supplied by the owner, 2026-09-06.
// Override GHE_SITE_URL when installing under another domain or subdirectory.
return [
    'url' => rtrim(getenv('GHE_SITE_URL') ?: 'https://ghecoffeedl.io.vn', '/'),
    'name' => 'Ghé',
    'address' => '30 Võ Trường Toản, Đà Lạt, Lâm Đồng',
    'phone' => '0705 926 614',
    'telephone' => '+84705926614',
    'maps' => 'https://maps.app.goo.gl/ySJSyKgaPvpcgA549',
    'directions' => 'https://www.google.com/maps/dir/?api=1&destination=11.9594496%2C108.4451549',
    'latitude' => 11.9594496,
    'longitude' => 108.4451549,
    // Operating hours and content are managed in the private CMS store.
];
