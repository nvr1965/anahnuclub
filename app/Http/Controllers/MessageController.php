<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Jobs\TranslateContent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Support\Background;
use App\Translation\ContentTranslator;
use App\Translation\LanguageGuesser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Private messages between members, translated into each reader's language. */
class MessageController extends Controller
{
    /** How many of the latest messages in an open chat are translated for the reader on the spot. */
    public const TRANSLATE_ON_OPEN = 50;

    /** Files a browser or a computer could run; they are not passed on. */
    private const BLOCKED = ['php', 'phtml', 'phar', 'html', 'htm', 'xhtml', 'svg', 'js', 'mjs', 'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'ps1', 'vbs', 'jar', 'scr', 'apk'];

    public function index(Request $request): Response
    {
        return Inertia::render('messages/index', [
            ...$this->lists($request->user()),
            'conversation' => null,
            'messages' => [],
        ]);
    }

    /** The chats for the panel under the Messages tile in the top bar, as Messenger's dropdown on Facebook. */
    public function latest(Request $request): JsonResponse
    {
        $rows = collect($this->conversations($request->user()));

        return response()->json([
            'conversations' => $rows->where('request', false)->take(30)->values()->all(),
            'requests' => $rows->where('request', true)->take(20)->values()->all(),
        ]);
    }

    /** "Mark all as read" in the chats panel. */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->conversations()->newPivotQuery()->update(['last_read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, Conversation $conversation, ContentTranslator $translator): Response
    {
        return Inertia::render('messages/index', [
            ...$this->lists($request->user()),
            ...$this->chat($request, $conversation, $translator),
        ]);
    }

    /** One chat for the small window docked at the bottom of any page, as on Facebook. */
    public function popup(Request $request, Conversation $conversation, ContentTranslator $translator): JsonResponse
    {
        return response()->json($this->chat($request, $conversation, $translator, 60));
    }

    /** @return array{conversation: array, messages: Collection} */
    private function chat(Request $request, Conversation $conversation, ContentTranslator $translator, int $limit = 200): array
    {
        $user = $request->user();
        abort_unless($conversation->users()->whereKey($user->id)->exists(), 404);

        // Reading the chat marks everything in it as read.
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        $mine = $conversation->users()->whereKey($user->id)->first()->pivot;

        $locale = app()->getLocale();
        $other = $conversation->users()->whereKeyNot($user->id)->first() ?? $user;
        $messages = $conversation->messages()
            ->with(['attachments', 'contentTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
        // The latest ones are translated while the reader waits, e.g. after switching language.
        $recent = $messages->slice(-self::TRANSLATE_ON_OPEN)->pluck('id')->all();
        $messages = $messages
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'files' => $m->attachments->map->card()->all(),
                'mine' => $m->user_id === $user->id,
                'created_at' => $m->created_at?->toIso8601String(),
                'translation' => $m->user_id === $user->id ? null : TranslationPayload::for($m, $locale, $translator, in_array($m->id, $recent, true)),
            ]);

        return [
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
        ];
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->users()->whereKey($user->id)->exists(), 404);
        $data = $request->validate([
            'body' => ['nullable', 'required_without:files', 'string', 'max:5000'],
            'files' => ['array', 'max:10'],
            'files.*' => ['file', 'max:20480', function (string $attribute, mixed $file, \Closure $fail) {
                if (in_array(strtolower($file->getClientOriginalExtension()), self::BLOCKED, true)) {
                    $fail(__('This kind of file cannot be sent.'));
                }
            }],
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        $others = $conversation->users()->whereKeyNot($user->id)->get();
        abort_if($others->contains(fn (User $other) => ! $user->canMessage($other)), 403);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'locale' => LanguageGuesser::guess($body) ?? $user->locale,
        ]);
        foreach ($request->file('files', []) as $file) {
            $message->attachments()->create([
                'path' => $file->store('messages/'.$conversation->id, 'local'),
                'name' => mb_substr($file->getClientOriginalName(), -200),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
        $conversation->update(['last_message_at' => $message->created_at]);
        // Answering a request lets it into the inbox.
        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now(), 'accepted_at' => $conversation->users()->whereKey($user->id)->first()->pivot->accepted_at ?? now(), 'declined_at' => null]);

        // Only into the languages the other people in the chat read.
        $readers = $conversation->users()->whereKeyNot($user->id)->get(['users.id', 'locale', 'ui_locale']);
        $locales = $readers->flatMap(fn (User $u) => [$u->locale, $u->ui_locale])->filter()->unique()->values()->all();
        if ($body !== '') {
            Background::run(new TranslateContent($message, $locales));
        }

        return $request->wantsJson() ? response()->json(['id' => $message->id], 201) : back();
    }

    /** A file from a chat, only for the people in it. Pictures open in place, other files download. */
    public function file(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->message->conversation->users()->whereKey($request->user()->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $headers = ['Content-Type' => $attachment->isImage() ? $attachment->mime : 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400'];

        return $attachment->isImage()
            ? Storage::disk('local')->response($attachment->path, $attachment->name, $headers)
            : Storage::disk('local')->download($attachment->path, $attachment->name, $headers);
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

    /** @return array{conversations: array, requests: array, friends: array, contacts: array} */
    private function lists(User $user): array
    {
        $rows = collect($this->conversations($user));
        // The row of friends over the chats, as in Messenger: those on the site now come first.
        $contacts = $user->friends()->where('blocked', false)
            ->whereNotIn('id', $user->hiddenUserIds())
            ->onlineFirst()->orderBy('name')->limit(30)->get();
        // Friends there is no chat with yet, so one can be started from here.
        $friends = $user->friends()->where('blocked', false)
            ->whereNotIn('id', $rows->pluck('with.id')->merge($user->hiddenUserIds()))
            ->onlineFirst()->orderBy('name')->limit(200)->get();

        return [
            'conversations' => $rows->where('request', false)->values()->all(),
            'requests' => $rows->where('request', true)->values()->all(),
            'friends' => $friends->map(fn (User $u) => PeopleController::card($u))->all(),
            'contacts' => $contacts->map(fn (User $u) => PeopleController::card($u))->all(),
        ];
    }

    private function conversations(User $user): array
    {
        return $user->conversations()
            ->with(['users', 'latestMessage.attachments'])
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
                    'last' => $last ? ['body' => mb_strimwidth($last->body !== '' ? $last->body : '📎 '.$last->attachments->pluck('name')->implode(', '), 0, 80, '…'), 'mine' => $last->user_id === $user->id, 'created_at' => $last->created_at?->toIso8601String()] : null,
                    'unread' => $last && $last->user_id !== $user->id && ($readAt === null || $last->created_at->gt($readAt)),
                    'request' => $pivot?->accepted_at === null,
                ];
            })
            ->all();
    }
}
