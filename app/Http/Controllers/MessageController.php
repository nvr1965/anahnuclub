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
            ...$this->lists($request->user()),
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
        $mine = $conversation->users()->whereKey($user->id)->first()->pivot;

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
            ...$this->lists($user),
            'conversation' => [
                'id' => $conversation->id,
                'with' => PeopleController::card($other) + ['last_seen_at' => $other->last_seen_at?->toIso8601String()],
                // A block either way closes the chat; only the one who blocked is told why.
                'blocked' => match (true) {
                    $user->hasBlocked($other) => 'by_me',
                    $user->hasMessengerBlocked($other) => 'messages_by_me',
                    $user->messengerBlockedWith($other) => 'by_them',
                    ! $user->canMessage($other) => 'friends_only',
                    default => null,
                },
                // Someone who is not a friend wrote first and this member has not let them in yet.
                'request' => $mine->accepted_at === null,
                'friends' => $user->friendshipWith($other) === 'friends',
                'can_call' => ! $other->is($user) && $user->canMessage($other)
                    && $conversation->users()->whereKey($other->id)->first()?->pivot->accepted_at !== null,
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->users()->whereKey($user->id)->exists(), 404);
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        $others = $conversation->users()->whereKeyNot($user->id)->get();
        abort_if($others->contains(fn (User $other) => ! $user->canMessage($other)), 403);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'locale' => LanguageGuesser::guess($body) ?? $user->locale,
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);
        // Answering a request lets it into the inbox.
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now(), 'accepted_at' => $conversation->users()->whereKey($user->id)->first()->pivot->accepted_at ?? now(), 'declined_at' => null]);

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
        $existing = Conversation::query()->whereHas('users', fn ($q) => $q->whereKey($me->id))->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
        // A new chat needs leave to write; an old one opens anyway, to read it.
        abort_if(! $existing && ! $me->canMessage($user), 403);

        return to_route('messages.show', Conversation::between($me, $user));
    }

    /** Lets a request into the inbox. */
    public function accept(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->users()->whereKey($request->user()->id)->exists(), 404);
        $conversation->users()->updateExistingPivot($request->user()->id, ['accepted_at' => now(), 'declined_at' => null]);

        return back();
    }

    /** Turns a request down: it leaves the list until the person writes again. */
    public function decline(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->users()->whereKey($request->user()->id)->exists(), 404);
        $conversation->users()->updateExistingPivot($request->user()->id, ['declined_at' => now()]);

        return to_route('messages.index');
    }

    /** @return array{conversations: array, requests: array} */
    private function lists(User $user): array
    {
        $rows = collect($this->conversations($user));

        return [
            'conversations' => $rows->where('request', false)->values()->all(),
            'requests' => $rows->where('request', true)->values()->all(),
        ];
    }

    private function conversations(User $user): array
    {
        return $user->conversations()
            ->with(['users', 'latestMessage'])
            ->whereNotNull('last_message_at')
            ->whereDoesntHave('users', fn ($q) => $q->whereIn('users.id', $user->hiddenUserIds()))
            // A request that was turned down, with nothing newer since.
            ->where(fn ($q) => $q->whereNull('conversation_user.declined_at')->orWhereColumn('conversations.last_message_at', '>', 'conversation_user.declined_at'))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $other = $c->users->firstWhere('id', '!=', $user->id) ?? $user;
                $pivot = $c->users->firstWhere('id', $user->id)?->pivot;
                $readAt = $pivot?->last_read_at;
                $last = $c->latestMessage;

                return [
                    'id' => $c->id,
                    'with' => PeopleController::card($other),
                    'last' => $last ? ['body' => mb_strimwidth($last->body, 0, 80, '…'), 'mine' => $last->user_id === $user->id, 'created_at' => $last->created_at?->toIso8601String()] : null,
                    'unread' => $last && $last->user_id !== $user->id && ($readAt === null || $last->created_at->gt($readAt)),
                    'request' => $pivot?->accepted_at === null,
                ];
            })
            ->all();
    }
}
