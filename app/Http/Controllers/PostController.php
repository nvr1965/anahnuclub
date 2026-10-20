<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Jobs\FetchLinkPreview;
use App\Jobs\TranslateContent;
use App\Models\Album;
use App\Models\Group;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\Video;
use App\Support\Html;
use App\Support\ImageStore;
use App\Support\LinkPreviewFetcher;
use App\Support\SiteMeta;
use App\Translation\LanguageGuesser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required_without_all:images,video', 'nullable', 'string', 'max:20000'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'images' => ['nullable', 'array', 'max:'.AlbumController::MAX_PHOTOS],
            'images.*' => ['image', 'max:15360'],
            'video' => ['nullable', 'file', 'mimetypes:'.VideoController::FILE_TYPES, 'max:204800'],
            'video_thumb' => ['nullable', 'image', 'max:5120'],
            'no_link_preview' => ['nullable', 'boolean'],
            ...self::audienceRules(),
        ]);
        $text = (string) ($data['body'] ?? '');
        $images = $request->file('images', []);
        $file = $request->file('video');
        abort_if($text === '' && ! $images && ! $file, 422);

        if ($data['group_id'] ?? null) {
            abort_unless(Group::find($data['group_id'])->members()->whereKey($request->user()->id)->exists(), 403);
        }

        // A clip attached here (often straight from the camera) also goes to the member's videos.
        $video = null;
        if ($file) {
            $video = Video::create([
                'user_id' => $request->user()->id,
                'group_id' => $data['group_id'] ?? null,
                'provider' => 'file',
                'path' => $file->storeAs('videos', Str::random(24).'.'.($file->extension() ?: 'mp4'), 'public'),
                'thumb_path' => $request->hasFile('video_thumb') ? ImageStore::save($request->file('video_thumb'), 'videos/thumbs', 960) : null,
                'title' => Str::limit(trim(strtok($text, "\n") ?: '') ?: __('Video').' · '.$request->user()->name, 190, '…'),
                'locale' => LanguageGuesser::guess($text) ?? $request->user()->locale,
            ]);
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'group_id' => $data['group_id'] ?? null,
            'type' => $text === '' ? ($video ? 'video' : 'photo') : 'status',
            'video_id' => $video?->id,
            'body' => Html::fromPlainText($text),
            'locale' => LanguageGuesser::guess($text) ?? $request->user()->locale,
        ]);
        // The author closed the link card while writing: the link stays in the text only.
        if ($data['no_link_preview'] ?? false) {
            $post->forceFill(['link_url' => ''])->saveQuietly();
        }
        // In a group the group decides who reads; elsewhere the author picks the audience.
        if (! $post->group_id) {
            $this->applyAudience($request, $post, $data);
        }

        if ($images) {
            AlbumController::savePhotos($images, Album::timelineOf($request->user()), $request->user(), $post);
        }

        // Translated right after the response is sent, so readers get it within seconds.
        TranslateContent::dispatchAfterResponse($post);

        return back();
    }

    /** The card for a link being typed into a new post, read now if it is not known yet. */
    public function linkPreview(Request $request, LinkPreviewFetcher $fetcher): JsonResponse
    {
        $url = LinkPreviewFetcher::firstUrl((string) $request->validate(['url' => ['required', 'string', 'max:2048']])['url']);
        if ($url) {
            (new FetchLinkPreview($url))->handle($fetcher);
        }

        return response()->json(['link' => $url ? LinkPreview::where('url', $url)->first()?->card() : null]);
    }

    /** Changes who sees a post, from its menu. */
    public function audience(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id && $post->group_id === null, 403);
        $this->applyAudience($request, $post, $request->validate(self::audienceRules()));

        return back();
    }

    private static function audienceRules(): array
    {
        return [
            'audience' => ['nullable', 'in:'.implode(',', Post::AUDIENCES)],
            'audience_ids' => ['nullable', 'array', 'max:500', 'required_if:audience,specific'],
            'audience_ids.*' => ['integer'],
            'audience_default' => ['nullable', 'boolean'],
        ];
    }

    /** Sets the audience; the people named must be the author's friends. Optionally the new default. */
    private function applyAudience(Request $request, Post $post, array $data): void
    {
        $user = $request->user();
        $audience = $data['audience'] ?? $user->default_audience ?? 'public';
        $ids = in_array($audience, ['except', 'specific'], true)
            ? $user->friendIds()->intersect(array_map('intval', $data['audience_ids'] ?? []))->values()->all()
            : [];
        // "Specific friends" with nobody valid left would hide the post from everyone but the author.
        if ($audience === 'specific' && $ids === []) {
            $audience = 'only_me';
        }

        $post->update(['audience' => $audience]);
        $post->audienceUsers()->sync($ids);
        if ($data['audience_default'] ?? false) {
            $user->update(['default_audience' => in_array($audience, ['except', 'specific'], true) ? 'friends' : $audience]);
        }
    }

    /** A single post, open to anyone with the link unless it sits in a private or hidden group. */
    public function show(Request $request, Post $post): Response
    {
        $group = $post->group;
        if ($group) {
            abort_if(! $group->published && ! $request->user()?->is_admin, 404);
            $member = $request->user() && $group->members()->whereKey($request->user()->id)->exists();
            abort_if($group->is_private && ! $member && ! $request->user()?->is_admin, 404);
        }

        $post = FeedController::postQuery($request)->whereKey($post->id)->firstOrFail();
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', $post->body)))));
        preg_match('~<img[^>]+src=["\']([^"\']+)~i', Html::clean($post->body), $image);

        return Inertia::render('posts/show', [
            'post' => PostResource::make($post),
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => [
            'url' => route('posts.show', $post),
            'title' => $post->title ?: ($post->isOfficial() ? 'anahnu.club' : ($post->user?->name ?? 'anahnu.club')),
            'description' => mb_strimwidth($text, 0, 200, '…'),
            // The post's own picture, large enough for Facebook's big card; else the club's invitation.
            'image' => $post->photos->first()?->card()['url'] ?? (isset($image[1]) ? url($image[1]) : SiteMeta::image()),
        ]]);
    }

    /** Authors can rewrite what they typed; the new text is translated again. */
    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id && in_array($post->type, ['status', 'photo', 'video'], true) && $post->legacy_source === null, 403);
        $body = (string) $request->validate(['body' => [$post->type === 'status' ? 'required' : 'nullable', 'string', 'max:20000']])['body'];

        $post->update(['body' => Html::fromPlainText($body), 'locale' => LanguageGuesser::guess($body) ?? $post->locale]);
        TranslateContent::dispatchAfterResponse($post);

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->is_admin || $post->group?->canModerate($request->user()), 403);
        // Pictures added with the post go with it; ones uploaded to an album stay there.
        $post->photos()->whereHas('album', fn ($q) => $q->where('kind', 'timeline'))->get()->each(fn ($photo) => AlbumController::deletePhoto($photo));
        $post->delete();

        return back();
    }
}
