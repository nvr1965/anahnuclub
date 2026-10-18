<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** The bell: likes, comments, friend requests and event answers that concern the member. */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/index', [
            'notifications' => $this->latest($request, 100),
        ]);
    }

    /** The newest ones, for the menu under the bell. */
    public function latest(Request $request, int $limit = 20): JsonResponse|array
    {
        $notifications = $request->user()->notifications()->latest()->limit($limit)->get();
        // Names and pictures as they are now, not as they were when the notice was made.
        $actors = User::whereIn('id', $notifications->pluck('data.actor.id')->filter()->unique())->get()->keyBy('id');
        $rows = $notifications->map(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'kind' => $n->data['kind'] ?? '',
            'actor' => ($actor = $actors->get($n->data['actor']['id'] ?? 0)) ? PeopleController::card($actor) : ($n->data['actor'] ?? null),
            'excerpt' => $n->data['excerpt'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ])->all();

        return $request->routeIs('notifications.latest') ? response()->json(['notifications' => $rows]) : $rows;
    }

    /** Opening a notice marks it read and goes where it points. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
