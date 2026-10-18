<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Jobs\TranslateContent;
use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ClubActivity;
use App\Support\Html;
use App\Support\ImageStore;
use App\Translation\ContentTranslator;
use App\Translation\LanguageGuesser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Meetups and other events, with "going" and "interested" answers. */
class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = fn () => self::visible($request)->with('user')->withCount(['attendees as going_count' => fn ($q) => $q->where('event_user.status', 'going')]);

        return Inertia::render('events/index', [
            'upcoming' => $base()->where(fn (Builder $q) => $q->where('starts_at', '>=', now()->startOfDay())->orWhere('ends_at', '>=', now()))->orderBy('starts_at')->limit(60)->get()->map(fn (Event $e) => self::card($e, $user)),
            'past' => $base()->where('starts_at', '<', now()->startOfDay())->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '<', now()))->latest('starts_at')->limit(30)->get()->map(fn (Event $e) => self::card($e, $user)),
            'groups' => $user->groups()->where('published', true)->orderBy('name')->get(['groups.id', 'groups.name']),
        ]);
    }

    public function show(Request $request, Event $event, ContentTranslator $translator): Response
    {
        abort_unless(self::visible($request)->whereKey($event->id)->exists(), 404);
        $locale = app()->getLocale();
        $event->load(['user', 'group', 'contentTranslations' => fn ($q) => $q->where('locale', $locale)]);
        $attendees = $event->attendees()->where('blocked', false)->orderByPivot('created_at')->get();

        return Inertia::render('events/show', [
            'event' => self::card($event, $request->user()) + [
                'description' => Html::clean($event->description ?? ''),
                'description_text' => $event->description,
                'author' => $event->user ? PeopleController::card($event->user) : null,
                'group' => $event->group?->only('id', 'name', 'slug'),
                'group_id' => $event->group_id,
                'mine' => $request->user()->id === $event->user_id,
                'can_delete' => $request->user()->id === $event->user_id || $request->user()->is_admin,
                'url' => route('events.show', $event),
                'translation' => TranslationPayload::for($event, $locale, $translator),
            ],
            'going' => $attendees->where('pivot.status', 'going')->values()->map(fn (User $u) => PeopleController::card($u)),
            'interested' => $attendees->where('pivot.status', 'interested')->values()->map(fn (User $u) => PeopleController::card($u)),
            'groups' => $request->user()->groups()->where('published', true)->orderBy('name')->get(['groups.id', 'groups.name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $event = Event::create($data + ['user_id' => $request->user()->id]);
        $event->attendees()->attach($request->user()->id, ['status' => 'going']);
        TranslateContent::dispatchAfterResponse($event);

        Post::create(['user_id' => $request->user()->id, 'group_id' => $event->group_id, 'type' => 'event', 'body' => '', 'event_id' => $event->id]);

        return to_route('events.show', $event);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->id === $event->user_id, 403);
        $event->update($this->validated($request, $event));
        TranslateContent::dispatchAfterResponse($event);

        return back();
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()->id === $event->user_id || $request->user()->is_admin, 403);
        Post::where('event_id', $event->id)->where('type', 'event')->delete();
        ImageStore::delete($event->cover_path);
        $event->delete();

        return to_route('events.index');
    }

    /** going, interested, or none to take the answer back. */
    public function respond(Request $request, Event $event): RedirectResponse
    {
        abort_unless(self::visible($request)->whereKey($event->id)->exists(), 404);
        $status = $request->validate(['status' => ['required', Rule::in(['going', 'interested', 'none'])]])['status'];
        $me = $request->user();
        $subject = 'event:'.$event->id;

        ClubActivity::retract($event->user, 'event_going', $me, $subject);
        ClubActivity::retract($event->user, 'event_interested', $me, $subject);
        if ($status === 'none') {
            $event->attendees()->detach($me->id);
        } else {
            $event->attendees()->syncWithoutDetaching([$me->id => ['status' => $status]]);
            ClubActivity::send($event->user, 'event_'.$status, $me, route('events.show', $event), $subject, $event->title);
        }

        return back();
    }

    /** What lists and post cards show of an event. */
    public static function card(Event $event, ?User $viewer = null): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'cover' => $event->coverUrl(),
            'going_count' => $event->going_count ?? $event->attendees()->where('event_user.status', 'going')->count(),
            'my_status' => $viewer ? $event->attendees()->whereKey($viewer->id)->first()?->pivot->status : null,
        ];
    }

    /** Events outside private groups the viewer is not in, and outside hidden groups. */
    public static function visible(Request $request): Builder
    {
        $mine = $request->user()?->groups()->pluck('groups.id') ?? collect();

        return Event::query()->where(fn (Builder $q) => $q
            ->whereNull('group_id')
            ->orWhereHas('group', fn (Builder $g) => $g->where('published', true)->where(fn (Builder $p) => $p->where('is_private', false)->orWhereIn('id', $mine))))
            ->when($request->user(), fn (Builder $q, $viewer) => $q
                ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $viewer->hiddenUserIds()))
                ->where(fn (Builder $q) => $q->whereNull('group_id')->orWhereNotIn('group_id', $viewer->blockedGroupIds())));
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'cover' => ['nullable', 'image', 'max:15360'],
            'remove_cover' => ['boolean'],
        ]);
        if ($data['group_id'] ?? null) {
            abort_unless(Group::find($data['group_id'])->members()->whereKey($request->user()->id)->exists(), 403);
        }

        if ($request->hasFile('cover')) {
            ImageStore::delete($event?->cover_path);
            $data['cover_path'] = ImageStore::save($request->file('cover'), 'events', 1640, 624);
        } elseif ($request->boolean('remove_cover')) {
            ImageStore::delete($event?->cover_path);
            $data['cover_path'] = null;
        }
        unset($data['cover'], $data['remove_cover']);
        $data['locale'] = LanguageGuesser::guess($data['title'].' '.($data['description'] ?? '')) ?? $request->user()->locale;

        return $data;
    }
}
