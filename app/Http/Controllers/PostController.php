<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Jobs\FetchLinkPreview;
use App\Jobs\SubmitToIndexNow;
use App\Jobs\TranslateContent;
use App\Models\Album;
use App\Models\Group;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Notifications\ClubActivity;
use App\Support\Background;
use App\Support\Embed;
use App\Support\Html;
use App\Support\ImageStore;
use App\Support\LinkPreviewFetcher;
use App\Support\PostTags;
use App\Support\SiteMeta;
use App\Translation\LanguageGuesser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required_without_all:images,video,feeling,life_event,place', 'nullable', 'string', 'max:20000'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'images' => ['nullable', 'array', 'max:'.AlbumController::MAX_PHOTOS],
            'images.*' => ['image', 'max:15360'],
            'video' => ['nullable', 'file', 'mimetypes:'.VideoController::FILE_TYPES, 'max:204800'],
            'video_thumb' => ['nullable', 'image', 'max:5120'],
            'no_link_preview' => ['nullable', 'boolean'],
            // A clip: a vertical video of up to a minute and a half, also shown in Clips.
            'clip' => ['nullable', 'boolean'],
            'video_width' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'video_height' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'video_duration' => ['nullable', 'numeric', 'min:0', 'max:'.(Video::CLIP_SECONDS + 1), 'required_if_accepted:clip'],
            ...self::tagRules(),
            ...self::extrasRules(),
            ...self::audienceRules(),
        ]);
        $extras = self::extras($data);
        $clip = ($data['clip'] ?? false) && $request->hasFile('video');
        if ($clip && $request->file('video')->getSize() > 50 * 1024 * 1024) {
            throw ValidationException::withMessages(['video' => __('A clip can be up to 50 MB.')]);
        }
        // "Embed" code copied from Facebook, Instagram, X… becomes the post's address, shown as a player.
        $text = Embed::unwrap((string) ($data['body'] ?? ''));
        $images = $request->file('images', []);
        $file = $request->file('video');
        abort_if($text === '' && ! $images && ! $file && array_filter($extras) === [], 422);

        $group = ($data['group_id'] ?? null) ? Group::find($data['group_id']) : null;
        if ($group) {
            abort_unless($group->members()->whereKey($request->user()->id)->exists(), 403);
        }
        // Friends tagged "with" the author, and those named with @ in the text.
        $taggable = self::taggable($request->user(), $group, $text, $data['tagged_ids'] ?? []);
        [$html, $mentioned] = self::markUp($text, $taggable);

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
                'is_clip' => $clip,
                'width' => $data['video_width'] ?? null,
                'height' => $data['video_height'] ?? null,
                'duration' => isset($data['video_duration']) ? (int) ceil($data['video_duration']) : null,
            ]);
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'group_id' => $data['group_id'] ?? null,
            'type' => $text === '' && ($video || $images) ? ($video ? 'video' : 'photo') : 'status',
            'video_id' => $video?->id,
            'body' => $html,
            'locale' => LanguageGuesser::guess($text) ?? $request->user()->locale,
            ...$extras,
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
        self::tagPeople($post, $request->user(), $taggable, $data['tagged_ids'] ?? [], $mentioned);

        // Translated right after the response is sent, so readers get it within seconds.
        Background::run(new TranslateContent($post));
        // Then Yandex and Bing hear of it, in the languages it was translated into.
        Background::run(new SubmitToIndexNow($post));

        return back();
    }

    /**
     * Shares a post inside the club, as on Facebook: to the member's own feed, to a group they are in,
     * or onto a friend's profile, with a few words of their own above it. A share of a share carries
     * the original post.
     */
    public function share(Request $request, Post $post): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate([
            'target' => ['required', 'in:feed,group,friend'],
            'body' => ['nullable', 'string', 'max:20000'],
            'group_id' => ['required_if:target,group', 'nullable', 'integer'],
            'user_id' => ['required_if:target,friend', 'nullable', 'integer'],
            ...self::audienceRules(),
        ]);

        $original = $post->shareRoot();
        abort_unless($original->canBeSharedBy($me) && $original->isVisibleTo($me), 403);
        abort_if($original->user && $me->isBlockedWith($original->user), 403);

        $group = null;
        $wall = null;
        if ($data['target'] === 'group') {
            $group = Group::where('published', true)->find($data['group_id']);
            abort_unless($group && $group->members()->whereKey($me->id)->wherePivot('approved', true)->exists(), 403);
        } elseif ($data['target'] === 'friend') {
            $wall = User::where('blocked', false)->find($data['user_id']);
            abort_unless($wall && ! $wall->is($me) && $me->friendIds()->contains($wall->id) && ! $me->isBlockedWith($wall), 403);
        }

        $text = trim((string) ($data['body'] ?? ''));
        $taggable = self::taggable($me, $group, $text, []);
        [$html, $mentioned] = self::markUp($text, $taggable);
        $share = Post::create([
            'user_id' => $me->id,
            'group_id' => $group?->id,
            'wall_user_id' => $wall?->id,
            'shared_post_id' => $original->id,
            'type' => 'share',
            'body' => $html,
            'locale' => LanguageGuesser::guess($text) ?? $me->locale,
        ]);
        // The shared post shows as itself, not as a card of its address typed in the words.
        $share->forceFill(['link_url' => ''])->saveQuietly();
        if (! $group) {
            $this->applyAudience($request, $share, $data);
        }
        self::tagPeople($share, $me, $taggable, [], $mentioned);

        $url = route('posts.show', $share);
        if ($wall) {
            ClubActivity::send($wall, 'wall_post', $me, $url, 'post:'.$share->id, $text !== '' ? $text : $original->body);
        }
        if (! $original->isOfficial() && ! $original->user?->is($wall)) {
            ClubActivity::send($original->user, 'post_shared', $me, $url, 'post:'.$original->id, $original->body);
        }
        if ($text !== '') {
            Background::run(new TranslateContent($share));
        }

        return back();
    }

    /** The groups the member may share into: those they belong to, the latest joined first. */
    public function shareTargets(Request $request): JsonResponse
    {
        return response()->json($request->user()->groups()
            ->wherePivot('approved', true)->where('published', true)
            ->orderByDesc('group_user.created_at')->limit(500)->get()
            ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name, 'avatar' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null, 'is_private' => $g->is_private])->values());
    }

    /** The card for a link being typed into a new post, read now if it is not known yet. */
    public function linkPreview(Request $request, LinkPreviewFetcher $fetcher): JsonResponse
    {
        $url = LinkPreviewFetcher::firstUrl(Embed::unwrap((string) $request->validate(['url' => ['required', 'string', 'max:2048']])['url']));
        // A post of Facebook, Instagram, YouTube… shows as its player, as it will in the post.
        if ($embed = Embed::fromUrl($url)) {
            return response()->json(['link' => null, 'embed' => $embed]);
        }
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

    /** "Add to your post": a feeling, a place, a life event. */
    private static function extrasRules(): array
    {
        return [
            'feeling' => ['nullable', 'in:'.implode(',', Post::FEELINGS)],
            'place' => ['nullable', 'string', 'max:150'],
            'life_event' => ['nullable', 'in:'.implode(',', Post::LIFE_EVENTS)],
        ];
    }

    /** @return array{feeling: ?string, place: ?string, life_event: ?string} */
    private static function extras(array $data): array
    {
        $place = trim(preg_replace('/\s+/u', ' ', (string) ($data['place'] ?? '')) ?? '');

        return [
            'feeling' => $data['feeling'] ?? null,
            'place' => $place !== '' ? $place : null,
            'life_event' => $data['life_event'] ?? null,
        ];
    }

    private static function tagRules(): array
    {
        return [
            'tagged_ids' => ['nullable', 'array', 'max:'.PostTags::MAX_PEOPLE],
            'tagged_ids.*' => ['integer'],
        ];
    }

    /**
     * The friends the author may tag in a post: not blocked either way and, in a private group, its members
     * (no one else could open the post). Asked of the database only when someone is tagged or named.
     *
     * @return Collection<int, User>
     */
    private static function taggable(User $author, ?Group $group, string $text, array $ids): Collection
    {
        if (! str_contains($text, '@') && $ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $author->friendIds())
            ->where('blocked', false)
            ->whereNotIn('id', $author->hiddenUserIds())
            ->when($group?->is_private, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group->id)->where('group_user.approved', true)))
            ->get(['id', 'name']);
    }

    /**
     * The text as the post's HTML, with its hashtags and the @names of friends as links, and the friends named.
     *
     * @param  Collection<int, User>  $taggable
     * @return array{string, Collection<int, User>}
     */
    private static function markUp(string $text, Collection $taggable): array
    {
        $mentioned = PostTags::mentioned($text, $taggable)->take(PostTags::MAX_PEOPLE);

        return [PostTags::link(Html::fromPlainText($text), $mentioned), $mentioned];
    }

    /**
     * Tags the friends picked "with" the author ($withIds; null keeps those tagged before) and those named in
     * the text. Each one newly tagged hears of it, once.
     *
     * @param  Collection<int, User>  $taggable
     * @param  Collection<int, User>  $mentioned
     */
    private static function tagPeople(Post $post, User $author, Collection $taggable, ?array $withIds, Collection $mentioned): void
    {
        $current = DB::table('post_user_tags')->where('post_id', $post->id)->get(['user_id', 'kind']);
        $with = $withIds === null
            ? $current->where('kind', 'with')->pluck('user_id')
            : $taggable->pluck('id')->intersect(array_map('intval', $withIds))->take(PostTags::MAX_PEOPLE);
        $want = $with->map(fn ($id) => "$id:with")->merge($mentioned->map(fn (User $u) => "{$u->id}:mention"))->unique()->values();
        $have = $current->map(fn ($row) => "{$row->user_id}:{$row->kind}");

        foreach ($have->diff($want) as $gone) {
            [$id, $kind] = explode(':', $gone);
            DB::table('post_user_tags')->where('post_id', $post->id)->where('user_id', $id)->where('kind', $kind)->delete();
        }
        $new = $want->diff($have)->map(fn ($tag) => explode(':', $tag));
        if ($new->isEmpty()) {
            return;
        }
        DB::table('post_user_tags')->insertOrIgnore($new->map(fn ($tag) => [
            'post_id' => $post->id, 'user_id' => (int) $tag[0], 'kind' => $tag[1], 'created_at' => now(), 'updated_at' => now(),
        ])->all());

        // One notice each: "tagged you" when picked "with", else "mentioned you".
        $url = route('posts.show', $post);
        $people = User::whereIn('id', $new->pluck(0))->get()->keyBy('id');
        $new->groupBy(0)->each(function ($tags, $id) use ($people, $author, $url, $post) {
            $kind = $tags->contains(fn ($tag) => $tag[1] === 'with') ? 'post_tagged' : 'post_mentioned';
            ClubActivity::send($people->get($id), $kind, $author, $url, 'post:'.$post->id, $post->body);
        });
    }

    /** A member tagged in a post takes their tag off: the post leaves their profile. */
    public function untag(Request $request, Post $post): RedirectResponse
    {
        DB::table('post_user_tags')->where('post_id', $post->id)->where('user_id', $request->user()->id)->delete();

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
        // Search engines read the post in the language of the page (?lang=he), when it is translated.
        $body = Html::clean($post->cachedTranslation('body', app()->getLocale()) ?? $post->body);
        $title = $post->cachedTranslation('title', app()->getLocale()) ?? $post->title;
        // News our sites post through /api/autopost are not the member's own words: no "Name:" before them.
        $autopost = $post->user && $post->user->email === config('services.autopost.user')
            && (string) $post->group_id === (string) (config('services.autopost.group') ?: '');
        $plain = $post->isOfficial() || $autopost;
        $author = $post->isOfficial() ? 'anahnu.club' : ($post->user?->name ?? 'anahnu.club');
        preg_match('~<img[^>]+src=["\']([^"\']+)~i', $body, $image);
        // Links are not words: a post that is only a YouTube link is named by the clip, not its address.
        $words = trim(preg_replace(['~https?://[^\s<>"\']+~i', '/\s+/u'], ['', ' '], SiteMeta::plain($body, 20000)) ?? '');
        $clip = $post->link_url ? Video::parseUrl($post->link_url) : null;
        $linkTitle = $post->video?->title ?: ($clip ? self::clipTitle($clip) : null) ?: $post->linkPreview?->title ?: self::linkSite($post->link_url);
        // Search engines are pointed only to the languages the post is written or translated in.
        $languages = $post->languages();
        $meta = [
            'url' => SiteMeta::canonical($languages),
            'alternates' => SiteMeta::alternates(route('posts.show', $post), $languages),
            // Facebook's card shows only the title, so a post without one is named by its own words:
            // "Author: the beginning of the text" (the club's own posts by the text alone); a post with
            // no words of its own by the title of its link or video.
            'title' => $title ?: self::cardTitle($author, mb_strimwidth($words, 0, $plain ? 110 : 80, '…') ?: (string) $linkTitle, $plain),
            'description' => mb_strimwidth($words, 0, 200, '…') ?: ($post->linkPreview?->description ?: SiteMeta::slogan()),
            // The post's own picture, large enough for Facebook's big card; else that of its video or link;
            // else the club's invitation.
            'image' => $post->photos->first()?->card()['url']
                ?? (isset($image[1]) ? url($image[1]) : null)
                ?? $post->video?->thumbUrl()
                ?? $post->linkPreview?->imageUrl()
                ?? ($clip ? (new Video(['provider' => $clip[0], 'provider_id' => $clip[1]]))->thumbUrl() : null)
                ?? SiteMeta::image(),
            'content' => [
                'heading' => $title ?: $author.': '.(mb_strimwidth($words, 0, 70, '…') ?: $linkTitle),
                'html' => $body,
                'links' => [],
            ],
        ];

        return Inertia::render('posts/show', [
            'post' => PostResource::make($post),
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => $meta + ['jsonld' => self::schema($post, $meta, $author, $languages)]]);
    }

    /**
     * The post in schema.org terms, and the event it announces, if any: search engines can show
     * the date, author and picture, and an event with its date and place.
     *
     * @param  list<string>  $languages
     * @return list<array<string, mixed>>
     */
    private static function schema(Post $post, array $meta, string $author, array $languages): array
    {
        $url = route('posts.show', $post);
        $nodes = [array_filter([
            '@type' => 'SocialMediaPosting',
            '@id' => $url,
            'url' => $meta['url'],
            'headline' => mb_strimwidth($meta['content']['heading'], 0, 110, '…'),
            'description' => $meta['description'],
            'image' => $meta['image'],
            'datePublished' => $post->created_at?->toAtomString(),
            'dateModified' => ($post->updated_at ?? $post->created_at)?->toAtomString(),
            'inLanguage' => in_array(app()->getLocale(), $languages, true) ? app()->getLocale() : ($post->locale ?: null),
            // Google asks for the author's page: the member's profile, or the club's home for its own posts.
            'author' => $post->isOfficial() || ! $post->user
                ? ['@type' => 'Organization', 'name' => $author, 'url' => route('home').'/']
                : ['@type' => 'Person', 'name' => $author, 'url' => route('people.show', $post->user)],
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'anahnu.club', 'url' => route('home').'/'],
        ])];

        // Google shows an event only with its start and its place.
        $event = $post->event;
        if ($event?->starts_at && trim((string) $event->location) !== '') {
            $nodes[] = array_filter([
                '@type' => 'Event',
                'name' => $event->cachedTranslation('title', app()->getLocale()) ?? $event->title,
                'description' => SiteMeta::plain($event->cachedTranslation('description', app()->getLocale()) ?? $event->description) ?: null,
                'startDate' => $event->starts_at->toAtomString(),
                'endDate' => $event->ends_at?->toAtomString(),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'location' => ['@type' => 'Place', 'name' => $event->location, 'address' => $event->location],
                'image' => $event->coverUrl(),
                'url' => $url,
                'organizer' => ['@type' => 'Organization', 'name' => 'anahnu.club', 'url' => route('home').'/'],
            ]);
        }

        return $nodes;
    }

    /** "Post from Facebook" for a link whose page told us nothing (Facebook shows robots only a sign-in page). */
    private static function linkSite(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $site = match (Embed::fromUrl($url)['provider'] ?? null) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'telegram' => 'Telegram',
            'x' => 'X',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'vimeo' => 'Vimeo',
            default => preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)),
        };

        return $site !== '' ? __('Post from :site', ['site' => $site]) : null;
    }

    /** The name of a YouTube or Vimeo clip, asked of the video site once and remembered. */
    private static function clipTitle(array $clip): ?string
    {
        // The clip's own page: the video sites don't answer for the player's address (/embed/…).
        $url = $clip[0] === 'youtube' ? 'https://www.youtube.com/watch?v='.$clip[1] : 'https://vimeo.com/'.$clip[1];
        $title = Cache::remember('clip-title:'.implode(':', $clip), now()->addWeek(), fn () => (string) (VideoController::oembed($clip[0], $url)['title'] ?? ''));

        return $title !== '' ? $title : null;
    }

    private static function cardTitle(string $author, string $text, bool $official): string
    {
        if ($text === '') {
            return $author;
        }

        return $official ? $text : $author.': '.$text;
    }

    /** Authors can rewrite what they typed; the new text is translated again. */
    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id && in_array($post->type, ['status', 'photo', 'video', 'share'], true) && $post->legacy_source === null, 403);
        $data = $request->validate(['body' => [$post->type === 'status' && ! $post->feeling && ! $post->place && ! $post->life_event ? 'required' : 'nullable', 'string', 'max:20000'], ...self::tagRules()]);
        $body = Embed::unwrap((string) $data['body']);
        // Without tagged_ids the friends tagged "with" stay as they were; the @names follow the new text.
        $with = $request->has('tagged_ids') ? ($data['tagged_ids'] ?? []) : null;
        $taggable = self::taggable($request->user(), $post->group, $body, $with ?? []);
        [$html, $mentioned] = self::markUp($body, $taggable);

        $post->update(['body' => $html, 'locale' => LanguageGuesser::guess($body) ?? $post->locale]);
        self::tagPeople($post, $request->user(), $taggable, $with, $mentioned);
        Background::run(new TranslateContent($post));
        // Then Yandex and Bing hear of it, in the languages it was translated into.
        Background::run(new SubmitToIndexNow($post));

        return back();
    }

    /** Pins the post at the top of the feed, the author's profile or its group, or takes the pin off. */
    public function pin(Request $request, Post $post, string $place): RedirectResponse
    {
        abort_unless(array_key_exists($place, Post::PIN_PLACES) && $post->canPin($request->user(), $place), 403);
        $column = Post::PIN_PLACES[$place];
        // Saved quietly: a pin is not an edit, the post keeps its date and is not translated again.
        $post->timestamps = false;
        $post->forceFill([$column => $post->$column ? null : now()])->saveQuietly();

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        // Also the member on whose profile a friend put it.
        abort_unless($post->user_id === $request->user()->id || $post->wall_user_id === $request->user()->id || $request->user()->is_admin || $post->group?->canModerate($request->user()), 403);
        // Pictures added with the post go with it; ones uploaded to an album stay there.
        $post->photos()->whereHas('album', fn ($q) => $q->where('kind', 'timeline'))->get()->each(fn ($photo) => AlbumController::deletePhoto($photo));
        $post->delete();

        return back();
    }
}
