<?php

return [
    // Search engines may index the site only at its real address (anahnu.club). A test copy such as
    // test.anahnu.club keeps this off, so robots.txt shuts everything and pages say "noindex".
    'indexable' => (bool) env('SEO_INDEXABLE', false),

    // Google reads at most 50,000 addresses from one sitemap file; each post counts once per language.
    'sitemap_urls' => 40000,
];
