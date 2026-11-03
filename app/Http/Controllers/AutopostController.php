<?php

namespace App\Http\Controllers;

use App\Jobs\FetchLinkPreview;
use App\Jobs\SubmitToIndexNow;
use App\Jobs\TranslateContent;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\User;
use App\Support\Background;
use App\Support\Html;
use App\Translation\LanguageGuesser;
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

        // The card as the site describes its own page: stored first, so nothing has to read the page.
        if (! empty($data['card_title']) || ! empty($data['card_image'])) {
            LinkPreview::updateOrCreate(['url' => $data['url']], [
                'title' => $data['card_title'] ?? null,
                'description' => $data['card_description'] ?? null,
                'image' => $data['card_image'] ?? null,
                'site_name' => parse_url($data['url'], PHP_URL_HOST),
            ]);
        }

        $text = trim($data['text'])."\n\n".$data['url'];
        $groupId = config('services.autopost.group') ?: null;
        $post = Post::create([
            'user_id' => $user->id,
            'group_id' => $groupId,
            'type' => 'status',
            'body' => Html::fromPlainText($text),
            'locale' => LanguageGuesser::guess($text) ?? $user->locale,
        ]);
        if (! $groupId) {
            $post->update(['audience' => 'public']);
        }
        // The card is always for the link sent, even when the text itself carries another one
        // (news descriptions often end with the source's address).
        if ($post->link_url !== $data['url']) {
            $post->forceFill(['link_url' => $data['url']])->saveQuietly();
            if (! LinkPreview::where('url', $data['url'])->exists()) {
                Background::run(new FetchLinkPreview($data['url']));
            }
        }

        Background::run(new TranslateContent($post));
        Background::run(new SubmitToIndexNow($post));

        return response()->json(['id' => $post->id, 'url' => route('posts.show', $post)], 201);
    }
}
