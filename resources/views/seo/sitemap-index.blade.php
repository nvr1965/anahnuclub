{{--
    Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
    Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
    Developed with the use of Claude.
--}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as $file)
    <sitemap><loc>{{ $file }}</loc></sitemap>
@endforeach
</sitemapindex>
