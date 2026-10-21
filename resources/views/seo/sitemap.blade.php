{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- Every page in each language, each listing all its language versions (Google's hreflang in sitemaps). --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
@php($alternates = \App\Support\SiteMeta::alternates($url['loc']))
@foreach ($alternates as $href)
    <url>
        <loc>{{ $href }}</loc>
@if ($url['lastmod'])
        <lastmod>{{ \Illuminate\Support\Carbon::parse($url['lastmod'])->toAtomString() }}</lastmod>
@endif
@foreach ($alternates as $hreflang => $other)
        <xhtml:link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $other }}"/>
@endforeach
    </url>
@endforeach
@endforeach
</urlset>
