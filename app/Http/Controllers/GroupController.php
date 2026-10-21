<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Jobs\TranslateContent;
use App\Models\Group;
use App\Models\User;
use App\Support\Background;
use App\Support\Html;
use App\Support\ImageStore;
use App\Support\SiteMeta;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = Group::query()
            ->where('published', true)
            ->when($request->user(), fn ($q, $viewer) => $q->whereNotIn('id', $viewer->blockedGroupIds()))
            ->withCount(['members' => fn ($q) => $q->where('group_user.approved', true), 'posts'])
            ->orderByDesc('posts_count')
            ->paginate(30)
            ->through(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'avatar' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null,
                'members_count' => $g->members_count,
                'posts_count' => $g->posts_count,
            ]);

        return Inertia::render('groups/index', ['groups' => $groups, 'sidebar' => CommunitySidebar::for($request->user())]);
    }

    public function show(Request $request, Group $group): Response
    {
        $member = $request->user() && $group->members()->whereKey($request->user()->id)->exists();
        abort_if(! $group->published && ! $request->user()?->is_admin, 404);
        // Someone invited may look before deciding.
        $invited = $request->user() && $group->invitations()->where('user_id', $request->user()->id)->exists();
        abort_if($group->is_private && ! $member && ! $invited && ! $request->user()?->is_admin, 403);

        $posts = FeedController::postQuery($request)->where('group_id', $group->id)
            // A closed group shows its posts to members only, not to someone still invited or waiting.
            ->when($group->is_private && ! $request->user()?->is_admin && ! $group->roleOf($request->user()), fn ($q) => $q->whereRaw('1 = 0'))
            ->paginate(20);

        return Inertia::render('groups/show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => Html::clean($group->description),
                'cover' => $group->cover_path ? asset('storage/'.$group->cover_path) : null,
                'avatar' => $group->avatar_path ? asset('storage/'.$group->avatar_path) : null,
                'is_private' => $group->is_private,
                // The text as typed, for the settings form.
                'description_text' => trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>\R?|</p>~i', "\n", (string) $group->description)))),
                'members_count' => $group->members()->wherePivot('approved', true)->count(),
                'is_member' => $member,
                'blocked' => (bool) $request->user()?->blockedGroupIds()->contains($group->id),
                'role' => $role = $group->roleOf($request->user()),
                // An offer waiting for this member's answer.
                'offer' => ($offer = $group->roleOffers()->with('from')->where('user_id', $request->user()?->id)->first()) ? [
                    'id' => $offer->id,
                    'role' => $offer->role,
                    'from' => PeopleController::card($offer->from),
                ] : null,
                'invitation' => ! $role && ($inv = $group->invitations()->with('from')->where('user_id', $request->user()?->id)->first()) ? [
                    'id' => $inv->id,
                    'from' => PeopleController::card($inv->from),
                ] : null,
            ],
            // The team and the members to pick from, for those who run the group.
            'team' => in_array($role, ['owner', 'admin'], true) ? $this->team($group) : null,
            'posts' => PostResource::collection($posts),
            'sidebar' => CommunitySidebar::for($request->user()),
        ])->withViewData(['meta' => [
            'url' => route('groups.show', $group),
            'title' => $group->name.' · anahnu.club',
            'description' => mb_strimwidth(trim(strip_tags((string) $group->description)), 0, 200, '…') ?: __('The best social network without censorship'),
            'image' => $group->cover_path ? asset('storage/'.$group->cover_path) : SiteMeta::image(),
        ]]);
    }

    /** Any member can start a group and becomes its owner. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $me = $request->user();
        $group = Group::create([
            'owner_id' => $me->id,
            'name' => $data['name'],
            'slug' => (string) Str::uuid(),
            'description' => Html::fromPlainText((string) ($data['description'] ?? '')),
            'locale' => LanguageGuesser::guess($data['name'].' '.($data['description'] ?? '')) ?? $me->locale,
            'is_private' => (bool) ($data['is_private'] ?? false),
        ]);
        // As the imported groups: "12-name", readable and unique.
        $group->update(['slug' => trim($group->id.'-'.Str::limit(Str::slug($data['name'], '-', $group->locale ?? 'en'), 60, ''), '-')]);
        $group->members()->attach($me->id, ['role' => 'owner', 'approved' => true]);
        $this->pictures($request, $group);
        Background::run(new TranslateContent($group));

        return to_route('groups.show', $group);
    }

    /** Name, description, pictures and privacy, for the owner and admins. */
    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless(in_array($group->roleOf($request->user()), ['owner', 'admin'], true), 403);
        $data = $this->validated($request);
        $group->update([
            'name' => $data['name'],
            'description' => Html::fromPlainText((string) ($data['description'] ?? '')),
            'is_private' => (bool) ($data['is_private'] ?? false),
        ]);
        foreach (['avatar_path' => 'remove_avatar', 'cover_path' => 'remove_cover'] as $column => $flag) {
            if ($request->boolean($flag) && $group->{$column}) {
                ImageStore::delete($group->{$column});
                $group->update([$column => null]);
            }
        }
        $this->pictures($request, $group);
        if ($group->wasChanged(['name', 'description'])) {
            Background::run(new TranslateContent($group));
        }

        return back();
    }

    /** Only the owner closes a group; its posts go with it. */
    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($group->roleOf($request->user()) === 'owner', 403);
        ImageStore::delete($group->avatar_path);
        ImageStore::delete($group->cover_path);
        $group->delete();

        return to_route('groups.index');
    }

    /** Lets someone who asked into a closed group. */
    public function approve(Request $request, Group $group, User $user): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        $group->members()->updateExistingPivot($user->id, ['approved' => true]);
        Group::forgetRoles();

        return back();
    }

    /** Turns down a request or removes a member: each role only those below it. */
    public function removeMember(Request $request, Group $group, User $user): RedirectResponse
    {
        $mine = $group->roleOf($request->user());
        $theirs = $group->roleOf($user) ?? 'pending';
        $allowed = match ($mine) {
            'owner' => ['admin', 'moderator', 'member', 'pending'],
            'admin' => ['moderator', 'member', 'pending'],
            'moderator' => ['member', 'pending'],
            default => [],
        };
        abort_unless(in_array($theirs, $allowed, true), 403);
        $group->members()->detach($user->id);
        $group->roleOffers()->where('user_id', $user->id)->delete();
        Group::forgetRoles();

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_private' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:10240'],
            'cover' => ['nullable', 'image', 'max:10240'],
        ]);
    }

    private function pictures(Request $request, Group $group): void
    {
        foreach (['avatar' => ['avatar_path', 'group-avatars', 400, 400], 'cover' => ['cover_path', 'group-covers', 1640, 624]] as $field => [$column, $folder, $w, $h]) {
            if ($request->hasFile($field)) {
                $old = $group->{$column};
                $group->update([$column => ImageStore::save($request->file($field), $folder, $w, $h)]);
                ImageStore::delete($old);
            }
        }
    }

    private function team(Group $group): array
    {
        $offers = $group->roleOffers()->get()->keyBy('user_id');

        return $group->members()
            ->where('blocked', false)
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (User $u) => PeopleController::card($u) + [
                'role' => match (true) {
                    $group->owner_id === $u->id => 'owner',
                    ! $u->pivot->approved => 'pending',
                    in_array($u->pivot->role, ['admin', 'moderator'], true) => $u->pivot->role,
                    default => 'member',
                },
                'offer' => ($o = $offers->get($u->id)) ? ['id' => $o->id, 'role' => $o->role] : null,
            ])
            // Invited, not in yet: shown so the owner knows why they cannot be given a role.
            ->concat($group->invitations()->with('user')->get()->filter->user->map(fn ($i) => PeopleController::card($i->user) + [
                'role' => 'invited',
                'offer' => null,
                'invitation' => $i->id,
            ]))
            ->sortBy(fn ($m) => array_search($m['role'], ['pending', 'owner', 'admin', 'moderator', 'member', 'invited'], true))
            ->values()
            ->all();
    }

    public function join(Request $request, Group $group)
    {
        abort_if($request->user()->blockedGroupIds()->contains($group->id), 422);
        $group->members()->syncWithoutDetaching([$request->user()->id => ['approved' => ! $group->is_private]]);
        Group::forgetRoles();

        return back();
    }
}
