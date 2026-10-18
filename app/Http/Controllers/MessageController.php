<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Jobs\TranslateContent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Translation\ContentTranslator;
use App\Translation\LanguageGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Private messages between members, translated into each reader's language. */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('messages/index', [
            'conversations' => $this->conversations($request->user()),
            'conversation' => null,
            'messages' => [],
        ]);
    }

    public function show(Request $request, Conversation $conversation, ContentTranslator $translator): Response
    {
        $user = $request->user();
        abort_unless($conversation->users()->whereKey($user->id)->exists(), 404);

        // Reading the chat marks everything in it as read.
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $locale = app()->getLocale();
        $other = $conversation->users()->whereKeyNot($user->id)->first() ?? $user;
        $messages = $conversation->messages()
            ->with(['contentTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->user_id === $user->id,
                'created_at' => $m->created_at?->toIso8601String(),
                'translation' => $m->user_id === $user->id ? null : TranslationPayload::for($m, $locale, $translator),
            ]);

        return Inertia::render('messages/index', [
            'conversations' => $this->conversations($user),
            'conversation' => [
                'id' => $conversation->id,
                'with' => PeopleController::card($other) + ['last_seen_at' => $other->last_seen_at?->toIso8601String()],
                // A block either way closes the chat; only the one who blocked is told why.
                'blocked' => $user->isBlockedWith($other) ? ($user->hasBlocked($other) ? 'by_me' : 'by_them') : null,
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->users()->whereKey($user->id)->exists(), 404);
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        abort_if($conversation->users()->whereKeyNot($user->id)->whereIn('users.id', $user->hiddenUserIds())->exists(), 403);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'locale' => LanguageGuesser::guess($body) ?? $user->locale,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        // Only into the languages the other people in the chat read.
        $locales = $conversation->users()->whereKeyNot($user->id)->pluck('locale')->unique()->values()->all();
        TranslateContent::dispatchAfterResponse($message, $locales);

        return back();
    }

    /** Opens the chat with a member, starting one if needed. */
    public function start(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($user->blocked || ($me->isBlockedWith($user) && ! $me->hasBlocked($user)), 404);

        return to_route('messages.show', Conversation::between($me, $user));
    }

    private function conversations(User $user): array
    {
        return $user->conversations()
            ->with(['users', 'latestMessage'])
            ->whereNotNull('last_message_at')
            ->whereDoesntHave('users', fn ($q) => $q->whereIn('users.id', $user->hiddenUserIds()))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $other = $c->users->firstWhere('id', '!=', $user->id) ?? $user;
                $readAt = $c->users->firstWhere('id', $user->id)?->pivot->last_read_at;
                $last = $c->latestMessage;

                return [
                    'id' => $c->id,
                    'with' => PeopleController::card($other),
                    'last' => $last ? ['body' => mb_strimwidth($last->body, 0, 80, '…'), 'mine' => $last->user_id === $user->id, 'created_at' => $last->created_at?->toIso8601String()] : null,
                    'unread' => $last && $last->user_id !== $user->id && ($readAt === null || $last->created_at->gt($readAt)),
                ];
            })
            ->all();
    }
}
