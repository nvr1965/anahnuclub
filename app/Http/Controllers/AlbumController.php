<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/** Photo albums: a member's own, plus the timeline album that collects pictures added to posts. */
class AlbumController extends Controller
{
    public const MAX_PHOTOS = 20;

    public function show(Request $request, Album $album): Response
    {
        abort_if($album->group_id && $album->group?->is_private && ! $album->group->members()->whereKey($request->user()->id)->exists(), 404);
        $owner = $album->user;

        return Inertia::render('albums/show', [
            'album' => [
                'id' => $album->id,
                'name' => $album->name,
                'description' => $album->description,
                'kind' => $album->kind,
                'owner' => $owner ? PeopleController::card($owner) : null,
                'mine' => $request->user()->is($owner),
                'created_at' => $album->created_at?->toIso8601String(),
            ],
            'photos' => $album->photos()
                // Pictures of a post follow its audience.
                ->where(fn ($q) => $q->whereNull('post_id')->orWhereHas('post', fn ($p) => $p->visibleTo($request->user())))
                ->withCount('likes')
                ->withExists(['likes as liked' => fn ($q) => $q->where('user_id', $request->user()->id)])
                ->latest('id')
                ->get()
                ->map(fn (Photo $p) => $p->card() + ['likes_count' => $p->likes_count, 'liked' => (bool) $p->liked, 'post_url' => $p->post_id ? route('posts.show', $p->post_id) : null]),
            'open' => $request->integer('photo') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $album = Album::create($data + ['user_id' => $request->user()->id]);

        return to_route('albums.show', $album);
    }

    public function update(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeOwner($request, $album);
        $album->update($request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]));

        return back();
    }

    public function destroy(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeOwner($request, $album);
        $album->photos->each(fn (Photo $photo) => self::deletePhoto($photo));
        $album->delete();

        return to_route('people.show', ['user' => $request->user()->id, 'tab' => 'photos']);
    }

    /** Adds pictures to the album and, unless asked not to, tells friends in the feed. */
    public function upload(Request $request, Album $album): RedirectResponse
    {
        $this->authorizeOwner($request, $album);
        $request->validate([
            'images' => ['required', 'array', 'max:'.self::MAX_PHOTOS],
            'images.*' => ['image', 'max:15360'],
            'share' => ['boolean'],
        ]);

        $post = $request->boolean('share', true)
            ? Post::create(['user_id' => $request->user()->id, 'type' => 'photo', 'body' => '', 'title' => null])
            : null;
        self::savePhotos($request->file('images'), $album, $request->user(), $post);

        return back();
    }

    public function updatePhoto(Request $request, Photo $photo): RedirectResponse
    {
        abort_unless($request->user()->id === $photo->user_id, 403);
        $photo->update($request->validate(['caption' => ['nullable', 'string', 'max:250']]));

        return back();
    }

    public function destroyPhoto(Request $request, Photo $photo): RedirectResponse
    {
        abort_unless($request->user()->id === $photo->user_id || $request->user()->is_admin, 403);
        $post = $photo->post;
        self::deletePhoto($photo);
        // A photo post with nothing left to show goes too.
        if ($post && $post->type === 'photo' && trim(strip_tags($post->body)) === '' && ! $post->photos()->exists()) {
            $post->delete();
        }

        return back();
    }

    /** @param  list<UploadedFile>  $files */
    public static function savePhotos(array $files, Album $album, User $user, ?Post $post = null): void
    {
        foreach ($files as $file) {
            $album->photos()->create([
                'user_id' => $user->id,
                'post_id' => $post?->id,
                'path' => ImageStore::save($file, 'photos', 2048),
                'thumb_path' => ImageStore::save($file, 'photos/thumbs', 480, 480),
            ]);
        }
        $album->touch();
    }

    public static function deletePhoto(Photo $photo): void
    {
        ImageStore::delete($photo->path);
        ImageStore::delete($photo->thumb_path);
        $photo->delete();
    }

    /** The albums shown on a profile, newest first, leaving out empty ones made by the old site. */
    public static function listFor(User $user, bool $mine): array
    {
        return Album::query()
            ->where('user_id', $user->id)
            ->whereNull('group_id')
            ->when(! $mine, fn ($q) => $q->has('photos'))
            ->where(fn ($q) => $q->has('photos')->orWhere('created_at', '>', now()->subYear()))
            ->withCount('photos')
            ->with('latestPhoto')
            ->latest('updated_at')
            ->get()
            ->map(fn (Album $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'kind' => $a->kind,
                'photos_count' => $a->photos_count,
                'cover' => $a->latestPhoto?->card()['thumb'],
            ])
            ->all();
    }

    private function authorizeOwner(Request $request, Album $album): void
    {
        abort_unless($request->user()->id === $album->user_id, 403);
    }
}
