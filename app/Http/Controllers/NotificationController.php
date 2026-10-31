<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** The bell: likes, comments, friend requests and event answers that concern the member. */
class NotificationController extends Controller
{
    /** Under the bell a read notice stays this long, under "Earlier", as on Facebook; the full page keeps them all. */
    public const KEEP_READ_DAYS = 7;

    public function index(Request $request): Response
    {
        return Inertia::render('notifications/index', [
            'notifications' => $this->latest($request, 100),
        ]);
    }

    /** The newest ones, for the menu under the bell. */
    public function latest(Request $request, int $limit = 30): JsonResponse|array
    {
        $notifications = $request->user()->notifications()
            ->when($request->routeIs('notifications.latest'), fn ($q) => $q->where(fn ($q) => $q->whereNull('read_at')->orWhere('read_at', '>', now()->subDays(self::KEEP_READ_DAYS))))
            // Several people reacting to the same post come in as one row, so a few more are read than shown.
            ->latest()->limit($limit * 4)->get();
        // Names and pictures as they are now, not as they were when the notice was made.
        $actors = User::whereIn('id', $notifications->pluck('data.actor.id')->filter()->unique())->get()->keyBy('id');
        $card = fn (DatabaseNotification $n) => ($actor = $actors->get($n->data['actor']['id'] ?? 0)) ? PeopleController::card($actor) : ($n->data['actor'] ?? null);

        $rows = $notifications
            ->groupBy(fn (DatabaseNotification $n) => self::group($n))
            ->take($limit)
            ->map(function (Collection $group) use ($card) {
                /** @var DatabaseNotification $n */
                $n = $group->first();
                $people = $group->unique(fn (DatabaseNotification $m) => $m->data['actor']['id'] ?? $m->id)->values();

                return [
                    'id' => $n->id,
                    'kind' => $n->data['kind'] ?? '',
                    'actor' => $card($n),
                    // The second name in "Anna and Boris" and how many more reacted.
                    'also' => $people->count() > 1 ? $card($people[1]) : null,
                    'others' => max(0, $people->count() - 2),
                    'reaction' => $n->data['reaction'] ?? null,
                    'excerpt' => $n->data['excerpt'] ?? null,
                    'read' => $group->every(fn (DatabaseNotification $m) => $m->read_at !== null),
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            })->values()->all();

        return $request->routeIs('notifications.latest') ? response()->json(['notifications' => $rows]) : $rows;
    }

    /** Opening a notice marks it read, with the others about the same thing, and goes where it points. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $group = self::group($notification);
        $request->user()->unreadNotifications->filter(fn (DatabaseNotification $n) => self::group($n) === $group)->markAsRead();
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** Notices of the same kind about the same thing, by different people: the key without the person at its end. */
    private static function group(DatabaseNotification $n): string
    {
        $key = $n->data['key'] ?? null;

        return $key && str_contains($key, ':') ? substr($key, 0, strrpos($key, ':')) : 'id:'.$n->id;
    }
}
