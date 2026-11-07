<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Support\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posts from our other sites (nofinfo.com news), sent by their servers, not by a person.
 * POST /api/autopost with "Authorization: Bearer <AUTOPOST_TOKEN>", fields text and url.
 * The post comes from the member AUTOPOST_USER_EMAIL, in the group AUTOPOST_GROUP_ID if set,
 * and the link becomes a card: from card_title/card_description/card_image when the site sends
 * them (our own sites do, so the card never depends on reading the page), else from its Open Graph tags.
 */
class AutopostController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.autopost.token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 403);

        $user = User::where('email', config('services.autopost.user'))->first();
        abort_unless($user, 503, 'AUTOPOST_USER_EMAIL is not a member');

        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'card_title' => ['nullable', 'string', 'max:500'],
            'card_description' => ['nullable', 'string', 'max:2000'],
            'card_image' => ['nullable', 'url:http,https', 'max:2000'],
        ]);

        // The same news sent twice (a retry after a timeout) stays one post.
        $existing = Post::where('user_id', $user->id)->where('link_url', $data['url'])
            ->where('created_at', '>=', now()->subDays(3))->first();
        if ($existing) {
            return response()->json(['id' => $existing->id, 'url' => route('posts.show', $existing), 'duplicate' => true]);
        }

        $post = NewsPost::publish($user, $data['text'], $data['url'], [
            'title' => $data['card_title'] ?? null,
            'description' => $data['card_description'] ?? null,
            'image' => $data['card_image'] ?? null,
        ], config('services.autopost.group') ?: null);

        return response()->json(['id' => $post->id, 'url' => route('posts.show', $post)], 201);
    }
}
