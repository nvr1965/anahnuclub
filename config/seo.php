<?php

return [
    // Search engines may index the site only at its real address (anahnu.club). A test copy such as
    // test.anahnu.club keeps this off, so robots.txt shuts everything and pages say "noindex".
    'indexable' => (bool) env('SEO_INDEXABLE', false),

    // Addresses in one sitemap file; each post counts once per language and once without one.
    // Google allows 50,000, but a file that big (some 25 MB) is more than shared hosting builds in
    // one request, so the posts are split into many small files instead.
    'sitemap_urls' => 5000,
];
