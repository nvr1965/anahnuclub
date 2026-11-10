@php
    $author = $official ? 'anahnu.club' : ($post->user?->name ?? __('Former member'));
    $avatar = $official ? asset('images/logo-mark.png') : $post->user?->avatar;
    $date = $post->created_at && $post->created_at->gt(now()->subWeek())
        ? $post->created_at->locale(app()->getLocale())->diffForHumans()
        : $post->created_at?->locale(app()->getLocale())->isoFormat('LL');
    $photos = $post->photos->take(4);
    $more = $post->photos->count() - $photos->count();
    $link = ! $post->video && $post->photos->isEmpty() ? $post->linkPreview?->card() : null;
@endphp
<!DOCTYPE html>
<html translate="no" lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?: $author }} · anahnu.club</title>
    <base target="_blank">
    {{-- Self-contained: the page loads inside other sites, so it carries its own small style. --}}
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;background:transparent}
        body{font:15px/1.4 Rubik,"Noto Sans Arabic",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#050505}
        a{color:inherit;text-decoration:none}
        .card{max-width:550px;background:#fff;border:1px solid #dddfe2;border-radius:8px;overflow:hidden}
        .head{display:flex;align-items:center;gap:10px;padding:12px 12px 0}
        .avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;background:#117892;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600}
        .who{flex:1;min-width:0}
        .name{font-weight:600;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .name:hover,.more:hover,.foot a:hover{text-decoration:underline}
        .date{color:#65676b;font-size:13px}
        .logo{width:28px;height:28px;flex-shrink:0}
        .text{padding:10px 12px 0;overflow-wrap:anywhere}
        .text.clamp{max-height:calc(16.8em + 10px);overflow:hidden}
        .text p{margin:0 0 .5em}
        .text img{max-width:100%;height:auto}
        .text a{color:#117892}
        .title{font-weight:600;font-size:17px;margin-bottom:4px}
        .more{display:inline-block;padding:2px 12px 0;color:#65676b;font-weight:600}
        .more[hidden]{display:none}
        .media{margin-top:10px;display:grid;gap:2px}
        .media.n2,.media.n4{grid-template-columns:1fr 1fr}
        .media.n3{grid-template-columns:1fr 1fr}
        .media.n3 a:first-child{grid-column:span 2}
        .media a{position:relative;display:block;background:#f0f2f5}
        .media img{display:block;width:100%;height:100%;max-height:520px;object-fit:cover}
        .media.n2 img,.media.n3 img,.media.n4 img{aspect-ratio:1/1}
        .media .plus{position:absolute;inset:0;background:rgba(0,0,0,.45);color:#fff;font-size:28px;display:flex;align-items:center;justify-content:center}
        .video{margin-top:10px;background:#000}
        .video video,.video iframe{display:block;width:100%;aspect-ratio:16/9;border:0}
        .link{display:block;margin-top:10px;background:#f0f2f5;border-top:1px solid #dddfe2;border-bottom:1px solid #dddfe2}
        .link img{display:block;width:100%;aspect-ratio:1.91/1;object-fit:cover}
        .link div{padding:8px 12px}
        .link small{color:#65676b;text-transform:uppercase;font-size:12px}
        .link b{display:block}
        .foot{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;color:#65676b;font-size:14px}
        .foot a{color:#117892;font-weight:600}
        @media (prefers-color-scheme: dark){
            body{color:#e4e6eb}.card{background:#242526;border-color:#3a3b3c}
            .date,.more,.foot,.link small{color:#b0b3b8}.link,.media a{background:#3a3b3c;border-color:#3a3b3c}
            .text a,.foot a{color:#5ec2d8}
        }
    </style>
</head>
<body>
<article class="card">
    <header class="head">
        <a href="{{ $official || ! $post->user ? $url : route('people.show', $post->user->id) }}">
            @if ($avatar)
                <img class="avatar" src="{{ $avatar }}" alt="">
            @else
                <span class="avatar">{{ mb_strtoupper(mb_substr($author, 0, 1)) }}</span>
            @endif
        </a>
        <div class="who">
            <a class="name" href="{{ $official || ! $post->user ? $url : route('people.show', $post->user->id) }}">
                {{ $author }}@if ($post->group) <span class="date">▸</span> {{ $post->group->name }}@endif
            </a>
            <a class="date" href="{{ $url }}" title="{{ $post->created_at?->toIso8601String() }}">{{ $date }}</a>
        </div>
        <a href="{{ $url }}" title="anahnu.club"><img class="logo" src="{{ asset('images/logo-mark.png') }}" alt="anahnu.club"></a>
    </header>

    @if ($title || trim(strip_tags($body, '<img>')) !== '')
        <div class="text clamp" dir="auto" id="text">
            @if ($title)<div class="title">{{ $title }}</div>@endif
            {!! $body !!}
        </div>
        <a class="more" id="more" href="{{ $url }}" hidden>{{ __('See more') }}</a>
    @endif

    @if ($photos->isNotEmpty())
        <div class="media n{{ $photos->count() }}">
            @foreach ($photos as $i => $photo)
                <a href="{{ $url }}">
                    <img src="{{ $photos->count() > 1 ? $photo->card()['thumb'] : $photo->card()['url'] }}" alt="{{ $photo->caption }}" loading="lazy">
                    @if ($more > 0 && $loop->last)<span class="plus">+{{ $more }}</span>@endif
                </a>
            @endforeach
        </div>
    @elseif ($post->video)
        <div class="video">
            @if ($post->video->embedUrl())
                <iframe src="{{ $post->video->embedUrl() }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
            @elseif ($post->video->fileUrl())
                <video src="{{ $post->video->fileUrl() }}" poster="{{ $post->video->thumbUrl() }}" controls playsinline preload="metadata"></video>
            @endif
        </div>
    @elseif ($link)
        <a class="link" href="{{ $link['url'] }}" rel="noopener nofollow ugc">
            @if ($link['image'])<img src="{{ $link['image'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
            <div dir="auto"><small>{{ $link['domain'] }}</small>@if ($link['title'])<b>{{ $link['title'] }}</b>@endif</div>
        </a>
    @endif

    <footer class="foot">
        <span>
            @if ($post->likes_count) ♥ {{ $post->likes_count }} @endif
            @if ($post->comments_count && ! $official) &nbsp; {{ __(':count comments', ['count' => $post->comments_count]) }} @endif
        </span>
        <a href="{{ $url }}">{{ __('View on anahnu.club') }}</a>
    </footer>
</article>
<script>
    // "See more" only when the text is cut; the page tells the site around it how tall it is.
    (function () {
        var text = document.getElementById('text'), more = document.getElementById('more');
        if (text && more && text.scrollHeight > text.clientHeight + 2) more.hidden = false;
        var last = 0;
        function tell() {
            var height = Math.ceil(document.querySelector('.card').getBoundingClientRect().height) + 2;
            if (height !== last && window.parent !== window) {
                last = height;
                window.parent.postMessage({ type: 'anahnu-embed', post: {{ $post->id }}, height: height }, '*');
            }
        }
        window.addEventListener('load', tell);
        if (window.ResizeObserver) new ResizeObserver(tell).observe(document.body);
        tell();
    })();
</script>
</body>
</html>
