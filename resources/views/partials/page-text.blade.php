{{--
    Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
    Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
    Developed with the use of Claude.
--}}
{{-- A plain copy of the page: heading, text (already cleaned HTML) and links to more of the site. --}}
<main style="max-width:42rem;margin:2rem auto;padding:0 1rem;font-family:sans-serif;line-height:1.5">
    <h1>{{ $content['heading'] }}</h1>
    @if (! empty($content['html']))
        <div>{!! $content['html'] !!}</div>
    @endif
    @if (! empty($content['comments']))
        <section>
            <h2>{{ __('Comments') }}</h2>
            @foreach ($content['comments'] as $comment)
                <article id="comment-{{ $comment['id'] }}">
                    <p><strong>{{ $comment['author'] }}</strong>@if ($comment['date']) · <time datetime="{{ $comment['date']->toAtomString() }}">{{ $comment['date']->toDateString() }}</time>@endif</p>
                    <div>{!! $comment['html'] !!}</div>
                </article>
            @endforeach
        </section>
    @endif
    @if (! empty($content['links']))
        <ul>
            @foreach ($content['links'] as $link)
                <li><a href="{{ $link['href'] }}">{{ $link['text'] }}</a></li>
            @endforeach
        </ul>
    @endif
    <p><a href="{{ route('home') }}">anahnu.club</a> · <a href="{{ route('register') }}">{{ __('Register') }}</a> · <a href="{{ route('contact') }}">{{ __('Contact us') }}</a></p>
</main>
