<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Jobs\TranslateContent;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use App\Support\Html;
use App\Support\ImageStore;
use App\Translation\ContentTranslator;
use App\Translation\LanguageGuesser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Videos: YouTube and Vimeo links, or files uploaded here. */
class VideoController extends Controller
{
    /** Formats people upload, including what phones and browsers record (MP4, MOV, WebM). */
    public const FILE_TYPES = 'video/mp4,video/webm,video/quicktime,video/x-m4v,video/x-matroska';

    public function index(Request $request): Response
    {
        $author = $request->integer('user') ? User::find($request->integer('user')) : null;
        $videos = self::visible($request)
            ->when($author, fn (Builder $q) => $q->where('user_id', $author->id))
            ->with('user')
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Video $v) => $v->card() + ['author' => $v->user ? PeopleController::card($v->user) : null]);

        return Inertia::render('videos/index', [
            'videos' => $videos,
            'author' => $author ? PeopleController::card($author) : null,
        ]);
    }

    public function show(Request $request, Video $video, ContentTranslator $translator): Response
    {
        abort_unless(self::visible($request)->whereKey($video->id)->exists(), 404);
        $locale = app()->getLocale();
        $video->load(['user', 'contentTranslations' => fn ($q) => $q->where('locale', $locale)])->loadCount('likes');

        return Inertia::render('videos/show', [
            'video' => $video->card() + [
                'description' => Html::clean($video->description ?? ''),
                'description_text' => $video->description,
                'author' => $video->user ? PeopleController::card($video->user) : null,
                'mine' => $request->user()->id === $video->user_id,
                'can_delete' => $request->user()->id === $video->user_id || $request->user()->is_admin,
                'likes_count' => $video->likes_count,
                'liked' => $video->likes()->where('user_id', $request->user()->id)->exists(),
                'url' => route('videos.show', $video),
                'source_url' => $video->url,
                'translation' => TranslationPayload::for($video, $locale, $translator),
            ],
            'more' => self::visible($request)->whereKeyNot($video->id)->where('user_id', $video->user_id)->latest()->limit(6)->get()->map->card(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required_without:file', 'nullable', 'url', 'max:500'],
            'file' => ['required_without:url', 'nullable', 'file', 'mimetypes:'.self::FILE_TYPES, 'max:204800'],
            'thumb' => ['nullable', 'image', 'max:5120'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'share' => ['boolean'],
        ]);

        $attributes = ['user_id' => $request->user()->id, 'description' => $data['description'] ?? null];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attributes += [
                'provider' => 'file',
                'path' => $file->storeAs('videos', Str::random(24).'.'.($file->extension() ?: 'mp4'), 'public'),
                'thumb_path' => $request->hasFile('thumb') ? ImageStore::save($request->file('thumb'), 'videos/thumbs', 960) : null,
                'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ];
        } else {
            $parsed = Video::parseUrl($data['url']);
            if (! $parsed) {
                throw ValidationException::withMessages(['url' => __('Paste a YouTube or Vimeo link.')]);
            }
            [$provider, $id] = $parsed;
            $info = self::oembed($provider, $data['url']);
            $attributes += [
                'provider' => $provider,
                'provider_id' => $id,
                'url' => $data['url'],
                'title' => ($data['title'] ?? null) ?: ($info['title'] ?? 'Video'),
                'thumb_path' => $provider === 'vimeo' ? ($info['thumbnail_url'] ?? null) : null,
            ];
        }
        $attributes['locale'] = LanguageGuesser::guess($attributes['title'].' '.$attributes['description']) ?? $request->user()->locale;

        $video = Video::create($attributes);
        TranslateContent::dispatchAfterResponse($video);

        if ($request->boolean('share', true)) {
            Post::create(['user_id' => $request->user()->id, 'type' => 'video', 'body' => '', 'video_id' => $video->id]);
        }

        return to_route('videos.show', $video);
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        abort_unless($request->user()->id === $video->user_id, 403);
        $video->update($request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000']]));
        TranslateContent::dispatchAfterResponse($video);

        return back();
    }

    public function destroy(Request $request, Video $video): RedirectResponse
    {
        abort_unless($request->user()->id === $video->user_id || $request->user()->is_admin, 403);
        Post::where('video_id', $video->id)->where('type', 'video')->get()->each(fn (Post $p) => trim(strip_tags($p->body)) === '' ? $p->delete() : null);
        if ($video->path) {
            Storage::disk('public')->delete($video->path);
        }
        if ($video->thumb_path && ! str_contains($video->thumb_path, '://')) {
            ImageStore::delete($video->thumb_path);
        }
        $video->delete();

        return to_route('videos.index');
    }

    /** Videos outside private and hidden groups. */
    public static function visible(Request $request): Builder
    {
        return Video::query()->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where('is_private', false)))
            ->when($request->user(), fn (Builder $q, $viewer) => $q
                ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $viewer->hiddenUserIds()))
                ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $viewer->blockedGroupIds())));
    }

    /** Title and picture from the video site, when it answers quickly. */
    private static function oembed(string $provider, string $url): array
    {
        $endpoint = $provider === 'youtube' ? 'https://www.youtube.com/oembed' : 'https://vimeo.com/api/oembed.json';
        try {
            return Http::timeout(5)->get($endpoint, ['url' => $url, 'format' => 'json'])->json() ?? [];
        } catch (Throwable) {
            return [];
        }
    }
}
