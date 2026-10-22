<!DOCTYPE html>
<html translate="no" lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>anahnu.club</title>
    <style>
        body{margin:0;font:15px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#65676b}
        div{max-width:550px;border:1px solid #dddfe2;border-radius:8px;padding:16px;background:#fff}
        a{color:#117892;font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    {{-- The post was deleted or is no longer public. --}}
    <div>{{ __('This post is no longer available.') }} <a href="{{ route('home') }}" target="_blank">anahnu.club</a></div>
</body>
</html>
