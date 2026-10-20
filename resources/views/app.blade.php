<!DOCTYPE html>
<html translate="no" lang="{{ app()->getLocale() }}" dir="{{ \App\Models\Language::enabled()->firstWhere('code', app()->getLocale())?->rtl ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- The site translates itself; the browser should not offer to translate it again. --}}
        <meta name="google" content="notranslate">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        @php($meta ??= \App\Support\SiteMeta::default())
        @isset($meta)
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
        @endisset

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=rubik:400,500,600,700|noto-sans-arabic:400,500,600,700" rel="stylesheet" />
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- Installable as an app on phones and computers (public/manifest.webmanifest, public/sw.js). --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#117892">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Anahnu">

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
