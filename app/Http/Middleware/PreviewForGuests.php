<?php

namespace App\Http\Middleware;

use App\Http\Controllers\EventController;
use App\Http\Controllers\VideoController;
use App\Models\Event;
use App\Models\Group;
use App\Models\Video;
use App\Support\SiteMeta;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Videos, events and groups are for members, but their links get shared on Facebook, WhatsApp
 * and Telegram. A guest (and the robot that builds the link's card) gets the sign-in page with
 * the card of what the link points to; after signing in the member lands on it. Anything a guest
 * may not see (a private group, a clip of a friends-only post) gets the usual sign-in redirect.
 * It stands in for 'auth' on those pages.
 */
class PreviewForGuests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }
        // What 'auth' would do: off to sign in, and back here afterwards.
        if (! $request->isMethod('GET') || ! ($meta = $this->meta($request))) {
            throw new AuthenticationException;
        }

        redirect()->setIntendedUrl($request->fullUrl());

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => null,
        ])->withViewData(['meta' => $meta + ['noindex' => true]])->toResponse($request);
    }

    private function meta(Request $request): ?array
    {
        $item = collect($request->route()?->parameters())->first();

        if ($item instanceof Video && VideoController::visible($request)->whereKey($item->id)->exists()) {
            return [
                'url' => route('videos.show', $item),
                'title' => $item->title,
                'description' => SiteMeta::plain($item->description) ?: SiteMeta::slogan(),
                'image' => $item->thumbUrl() ?? SiteMeta::image(),
            ];
        }
        if ($item instanceof Event && EventController::visible($request)->whereKey($item->id)->exists()) {
            $when = $item->starts_at?->translatedFormat('j F Y, H:i');

            return [
                'url' => route('events.show', $item),
                'title' => $item->title,
                'description' => collect([$when, $item->location, SiteMeta::plain($item->description, 150)])->filter()->implode(' · '),
                'image' => $item->coverUrl() ?? SiteMeta::image(),
            ];
        }
        if ($item instanceof Group && $item->published && ! $item->is_private) {
            return [
                'url' => route('groups.show', $item),
                'title' => $item->name.' · anahnu.club',
                'description' => SiteMeta::plain($item->description) ?: __('The best social network without censorship'),
                'image' => $item->cover_path ? asset('storage/'.$item->cover_path) : SiteMeta::image(),
            ];
        }

        return null;
    }
}
