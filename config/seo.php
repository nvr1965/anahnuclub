<?php

return [
    // Search engines may index the site only at its real address (anahnu.club). A test copy such as
    // test.anahnu.club keeps this off, so robots.txt shuts everything and pages say "noindex".
    'indexable' => (bool) env('SEO_INDEXABLE', false),

    // Addresses in one sitemap file; each post counts once per language and once without one.
    // Google allows 50,000, but a file that big (some 25 MB) is more than shared hosting builds in
    // one request, so the posts are split into many small files instead.
    'sitemap_urls' => 5000,

    // IndexNow (Yandex, Bing…) learns of each new public post at once. The key is made from APP_KEY
    // when this is empty; set it only to keep one key across sites or after changing APP_KEY.
    'indexnow_key' => env('INDEXNOW_KEY'),
];
