<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Jobs\TranslateContent;
use App\Models\Conversation;
use App\Models\FriendList;
use App\Models\Moment;
use App\Models\User;
use App\Support\Background;
use App\Support\ImageStore;
use App\Translation\ContentTranslator;
use App\Translation\LanguageGuesser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Moments: photos, short videos and words that friends see for a day. */
class MomentController extends Controller
{
    /** People shown in the strip above the feed. */
    private const TRAY = 30;

    /**
     * The strip above the feed: the viewer first, then whoever has something not watched yet,
     * newest first, then those already watched. One query over the last day's moments.
     *
     * @return array{people: list<array>, lists: list<array{id: int, name: string}>, mine: int}
     */
    public static function tray(User $viewer): array
    {
        $moments = Moment::query()->active()->visibleTo($viewer)
            ->select('moments.id', 'moments.user_id', 'moments.created_at')
            ->addSelect(['seen' => DB::table('moment_views')->selectRaw('1')
                ->whereColumn('moment_views.moment_id', 'moments.id')->where('moment_views.user_id', $viewer->id)->limit(1)])
            ->latest()->limit(600)->get();

        $people = $moments->groupBy('user_id')->map(fn ($own, $userId) => [
            'user_id' => (int) $userId,
            'count' => $own->count(),
            'unseen' => (int) $userId !== $viewer->id && $own->contains(fn ($m) => ! $m->seen),
            'latest' => $own->max('created_at'),
        ])->sortByDesc(fn ($p) => [$p['user_id'] === $viewer->id, $p['unseen'], $p['latest']->getTimestamp()])->take(self::TRAY)->values();

        $users = User::whereIn('id', $people->pluck('user_id'))->get()->keyBy('id');

        return [
            'people' => $people->filter(fn ($p) => $users->has($p['user_id']))->map(fn ($p) => [
                'user' => PeopleController::card($users[$p['user_id']]),
                'count' => $p['count'],
                'unseen' => $p['unseen'],
            ])->values()->all(),
            'lists' => FriendList::where('user_id', $viewer->id)->orderBy('name')->get(['id', 'name'])->toArray(),
            'mine' => $people->firstWhere('user_id', $viewer->id)['count'] ?? 0,
        ];
    }

    /** One person's moments of the last day, oldest first, for the viewer. */
    public function show(Request $request, User $user, ContentTranslator $translator): JsonResponse
    {
        $viewer = $request->user();
        $locale = app()->getLocale();
        $moments = Moment::query()->where('user_id', $user->id)->active()->visibleTo($viewer)
            ->with(['contentTranslations' => fn ($q) => $q->where('locale', $locale)])
            ->withCount('viewers')
            ->oldest()->get();
        $seen = DB::table('moment_views')->where('user_id', $viewer->id)->whereIn('moment_id', $moments->pluck('id'))->pluck('moment_id')->flip();
        $mine = $viewer->id === $user->id;

        return response()->json([
            'user' => PeopleController::card($user),
            'mine' => $mine,
            'can_reply' => ! $mine && $viewer->canMessage($user),
            'moments' => $moments->map(fn (Moment $m) => $m->card() + [
                'seen' => $mine || $seen->has($m->id),
                'views' => $mine ? $m->viewers_count : null,
                'can_delete' => $mine || $viewer->is_admin,
                'translation' => $m->text ? TranslationPayload::for($m, $locale, $translator, onDemand: true) : null,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'kind' => ['required', Rule::in(['photo', 'video', 'text'])],
            'photo' => ['required_if:kind,photo', 'nullable', 'image', 'max:15360'],
            'video' => ['required_if:kind,video', 'nullable', 'file', 'mimetypes:'.VideoController::FILE_TYPES, 'max:20480'],
            'thumb' => ['nullable', 'image', 'max:5120'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:'.(Moment::MAX_SECONDS + 1)],
            'text' => ['required_if:kind,text', 'nullable', 'string', 'max:500'],
            'text_position' => ['nullable', Rule::in(Moment::POSITIONS)],
            'background' => ['nullable', Rule::in(Moment::BACKGROUNDS)],
            'audience' => ['nullable', Rule::in(Moment::AUDIENCES)],
            'friend_list_id' => ['nullable', 'required_if:audience,list', 'integer', Rule::exists('friend_lists', 'id')->where('user_id', $user->id)],
        ]);
        $text = trim((string) ($data['text'] ?? '')) ?: null;
        $audience = $data['audience'] ?? 'friends';

        $attributes = [
            'user_id' => $user->id,
            'kind' => $data['kind'],
            'text' => $text,
            'text_position' => $data['text_position'] ?? ($data['kind'] === 'text' ? 'center' : 'bottom'),
            'background' => $data['background'] ?? ($data['kind'] === 'text' ? 'brand' : null),
            'locale' => $text ? (LanguageGuesser::guess($text) ?? $user->locale) : null,
            'audience' => $audience,
            'friend_list_id' => $audience === 'list' ? $data['friend_list_id'] : null,
            'expires_at' => now()->addHours(Moment::HOURS),
        ];
        if ($data['kind'] === 'photo') {
            $attributes['path'] = ImageStore::save($request->file('photo'), 'moments', 1080);
        } elseif ($data['kind'] === 'video') {
            $file = $request->file('video');
            $attributes['path'] = $file->storeAs('moments', Str::random(24).'.'.($file->extension() ?: 'mp4'), 'public');
            $attributes['thumb_path'] = $request->hasFile('thumb') ? ImageStore::save($request->file('thumb'), 'moments', 720) : null;
            $attributes['duration'] = isset($data['duration']) ? (int) ceil(min($data['duration'], Moment::MAX_SECONDS)) : null;
        }

        Moment::create($attributes);

        return back();
    }

    /** Marks a moment watched, for the coloured ring and the author's list of viewers. */
    public function view(Request $request, Moment $moment): HttpResponse
    {
        abort_unless($moment->isVisibleTo($request->user()), 404);
        $moment->markSeenBy($request->user());

        return response()->noContent();
    }

    /** Who watched: only the author sees it. */
    public function viewers(Request $request, Moment $moment): JsonResponse
    {
        abort_unless($moment->user_id === $request->user()->id, 403);
        $hidden = $request->user()->hiddenUserIds();

        return response()->json([
            'viewers' => $moment->viewers()->whereNotIn('users.id', $hidden)->orderByPivot('viewed_at', 'desc')->limit(500)->get()
                ->map(fn (User $u) => PeopleController::card($u) + ['viewed_at' => $u->pivot->viewed_at ? Carbon::parse($u->pivot->viewed_at)->toIso8601String() : null])->all(),
        ]);
    }

    /**
     * An answer to a moment goes to the author's messages, with the moment's picture,
     * so the chat still shows what it was about after the moment is gone.
     */
    public function reply(Request $request, Moment $moment): JsonResponse
    {
        $viewer = $request->user();
        $body = trim($request->validate(['body' => ['required', 'string', 'max:1000']])['body']);
        abort_unless($moment->isVisibleTo($viewer) && $moment->user_id !== $viewer->id, 404);
        $author = $moment->user;
        abort_unless($viewer->canMessage($author), 403);

        $conversation = Conversation::between($viewer, $author);
        $quote = $moment->text ? '«'.Str::limit($moment->text, 120, '…').'»' : null;
        $message = $conversation->messages()->create([
            'user_id' => $viewer->id,
            'body' => __('Reply to a moment').($quote ? ' '.$quote : '').":\n".$body,
            'locale' => LanguageGuesser::guess($body) ?? $viewer->locale,
        ]);
        $picture = $moment->kind === 'photo' ? $moment->path : $moment->thumb_path;
        if ($picture && Storage::disk('public')->exists($picture)) {
            $copy = 'messages/'.$conversation->id.'/'.Str::random(24).'.jpg';
            Storage::disk('local')->put($copy, Storage::disk('public')->get($picture));
            $message->attachments()->create(['path' => $copy, 'name' => 'moment.jpg', 'mime' => 'image/jpeg', 'size' => Storage::disk('local')->size($copy)]);
        }
        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->users()->updateExistingPivot($viewer->id, ['last_read_at' => now()]);
        Background::run(new TranslateContent($message, array_values(array_filter(array_unique([$author->locale, $author->ui_locale])))));

        return response()->json(['ok' => true, 'conversation' => $conversation->id]);
    }

    public function destroy(Request $request, Moment $moment): RedirectResponse
    {
        abort_unless($moment->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $moment->delete();

        return back();
    }

    /** The author's moments of the last few days, active and gone, to look back or share again. */
    public function archive(Request $request): Response
    {
        $moments = Moment::query()->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now()->subDays(Moment::ARCHIVE_DAYS))
            ->withCount('viewers')->latest()->get();

        return Inertia::render('moments/archive', [
            'moments' => $moments->map(fn (Moment $m) => $m->card() + [
                'active' => $m->expires_at->isFuture(),
                'views' => $m->viewers_count,
                'deleted_at' => $m->expires_at->copy()->addDays(Moment::ARCHIVE_DAYS)->toIso8601String(),
            ])->all(),
            'archive_days' => Moment::ARCHIVE_DAYS,
        ]);
    }

    /** Shares a moment again for another day, with copies of its files (the old one is deleted later). */
    public function repost(Request $request, Moment $moment): RedirectResponse
    {
        abort_unless($moment->user_id === $request->user()->id, 403);
        $copy = function (?string $path) {
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return null;
            }
            $new = dirname($path).'/'.Str::random(24).'.'.pathinfo($path, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($path, $new);

            return $new;
        };
        $fresh = $moment->replicate(['created_at', 'updated_at']);
        $fresh->fill(['path' => $copy($moment->path), 'thumb_path' => $copy($moment->thumb_path), 'expires_at' => now()->addHours(Moment::HOURS)]);
        abort_if($moment->path && ! $fresh->path, 410);
        $fresh->save();

        return to_route('dashboard');
    }
}
