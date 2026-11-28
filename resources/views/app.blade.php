{{--
    Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
    Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
    Developed with the use of Claude.
--}}
<!DOCTYPE html>
<html translate="no" lang="{{ app()->getLocale() }}" dir="{{ \App\Models\Language::enabled()->firstWhere('code', app()->getLocale())?->rtl ? 'rtl' : 'ltr' }}" data-skin="{{ \App\Support\Skins::current() }}">
    <head>
        <meta charset="utf-8">
        {{-- Pinch-zoom stays allowed: IS 5568 / WCAG 1.4.4. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
        {{-- The accessibility menu's settings (resources/js/lib/accessibility.ts), before the page draws. --}}
        <script>
            try {
                (function (s) {
                    if (!s) return;
                    var h = document.documentElement, steps = [1, 1.125, 1.25, 1.5, 1.75];
                    if (steps[s.text] > 1) h.style.fontSize = steps[s.text] * 100 + '%';
                    if (s.palette && s.palette !== 'normal') h.classList.add('a11y-' + s.palette);
                    ['links', 'readable', 'spacing', 'still', 'cursor'].forEach(function (k) { if (s[k]) h.classList.add('a11y-' + k); });
                })(JSON.parse(localStorage.getItem('a11y') || 'null'));
            } catch (e) {}
        </script>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- The site translates itself; the browser should not offer to translate it again. --}}
        <meta name="google" content="notranslate">

        @php($meta ??= \App\Support\SiteMeta::default())
        <title inertia>{{ $meta['title'] }}</title>
        @isset($meta)
            {{-- Read by Google and other search engines: this page's address, the same page in each language. --}}
            @if (! \App\Support\SiteMeta::indexable() || ! empty($meta['noindex']))
                <meta name="robots" content="noindex, nofollow">
            @else
                <link rel="canonical" href="{{ $meta['url'] }}">
                @foreach ($meta['alternates'] ?? \App\Support\SiteMeta::alternates() as $hreflang => $href)
                    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
                @endforeach
            @endif
            {{-- Read by Facebook, Telegram, WhatsApp and others when a link to this page is shared. --}}
            <meta property="og:site_name" content="anahnu.club">
            <meta property="og:type" content="article">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta name="description" content="{{ $meta['description'] }}">
            @if ($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
                @if (str_contains($meta['image'], '/images/invite/'))
                    <meta property="og:image:width" content="1200">
                    <meta property="og:image:height" content="630">
                @endif
                <meta name="twitter:card" content="summary_large_image">
            @else
                <meta name="twitter:card" content="summary">
            @endif
            {{-- What the page is, in schema.org terms, for search results: the club and the site on every
                 page, and the page's own content (a post with its comments, an event). --}}
            <script type="application/ld+json">{!! \App\Support\SiteMeta::jsonLd(\App\Support\SiteMeta::graph($meta['jsonld'] ?? [])) !!}</script>
        @endisset

        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
        {{-- Installable as an app on phones and computers (public/manifest.webmanifest, public/sw.js). --}}
        <link rel="manifest" href="/manifest.webmanifest?v=2">
        <meta name="theme-color" content="#117892">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Anahnu">

        @routes(auth()->user()?->is_admin ? null : 'member')
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @isset($meta['content'])
            {{-- The page's text right in the HTML, for search engines and link previews that don't run
                 JavaScript; React replaces it as soon as it starts. (The site has no SSR server.) --}}
            <div id="app" data-page="{{ json_encode($page) }}">@include('partials.page-text', ['content' => $meta['content']])</div>
        @else
            @inertia
        @endisset
    </body>
</html>
