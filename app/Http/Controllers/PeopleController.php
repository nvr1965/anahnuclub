<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Photo;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Member profiles: cover, picture, about, friends, photos and the member's own posts. */
class PeopleController extends Controller
{
    public const TABS = ['posts', 'about', 'friends', 'photos', 'videos'];

    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        $me = $viewer->is($user);
        abort_if($user->blocked && ! $me && ! $viewer->is_admin, 404);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'posts';
        $friends = $user->friends()->where('blocked', false)->orderByDesc('last_seen_at');
        $photos = Photo::query()->where('user_id', $user->id)->whereNotNull('thumb_path')
            ->whereDoesntHave('album', fn (Builder $q) => $q->whereNotNull('group_id'))
            ->latest('id');

        $posts = null;
        if ($tab === 'posts') {
            $posts = FeedController::postQuery($request)
                ->where('user_id', $user->id)
                // Posts in private groups stay inside those groups.
                ->when(! $me, fn (Builder $q) => $q->whereDoesntHave('group', fn (Builder $g) => $g->where('is_private', true)))
                ->paginate(20)
                ->withQueryString();
        }

        return Inertia::render('people/show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'cover' => $user->cover,
                'status' => $user->status,
                'about' => $user->about,
                'location' => $user->location,
                'website' => $user->website,
                'birthdate' => $user->birthdate?->format('Y-m-d'),
                'gender' => $user->gender,
                'joined_at' => $user->created_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'friends_count' => (clone $friends)->count(),
                'posts_count' => $user->posts()->count(),
                'is_me' => $me,
                'friendship' => $me ? null : $viewer->friendshipWith($user),
            ],
            'tab' => $tab,
            'posts' => $posts ? PostResource::collection($posts) : null,
            'friends' => (clone $friends)->limit($tab === 'friends' ? 500 : 9)->get()->map(fn (User $u) => self::card($u)),
            'photos' => (clone $photos)->limit($tab === 'photos' ? 120 : 9)->get()
                ->map(fn (Photo $p) => $p->card() + ['album_id' => $p->album_id]),
            'albums' => $tab === 'photos' ? AlbumController::listFor($user, $me) : [],
            'videos' => $tab === 'videos' ? VideoController::visible($request)->where('user_id', $user->id)->latest()->limit(60)->get()->map->card() : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:500'],
            'about' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
        ]);

        $request->user()->update($data);

        return back();
    }

    public function avatar(Request $request): RedirectResponse
    {
        return $this->picture($request, 'avatar_path', 'avatars', 400, 400);
    }

    public function cover(Request $request): RedirectResponse
    {
        return $this->picture($request, 'cover_path', 'covers', 1640, 624);
    }

    private function picture(Request $request, string $column, string $folder, int $width, int $height): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('remove')) {
            ImageStore::delete($user->{$column});
            $user->update([$column => null]);

            return back();
        }

        $request->validate(['image' => ['required', 'image', 'max:10240']]);
        $old = $user->{$column};
        $user->update([$column => ImageStore::save($request->file('image'), $folder, $width, $height)]);
        ImageStore::delete($old);

        return back();
    }

    /** A member as shown in friend lists and suggestions. */
    public static function card(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatar];
    }
}
