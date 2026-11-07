<?php

namespace App\Http\Controllers\Admin;

use App\Feeds\FeedCollector;
use App\Feeds\FeedPublisher;
use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClubActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Feed filling": the club's authors, their sources, and the queue of news a curator approves
 * before the scheduler posts it over the day.
 */
class FeedController extends AdminController
{
    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ['queue', 'authors', 'sources'], true) ? $request->query('tab') : 'queue';
        $status = in_array($request->query('status'), FeedItem::STATUSES, true) ? $request->query('status') : 'approved';
        $channelId = $request->integer('author') ?: null;
        $today = now()->tz(config('feeds.timezone'))->setTime(config('feeds.window')[0], 0)->utc();

        $channels = FeedChannel::with(['user:id,name,username', 'group:id,name,slug'])->orderBy('id')->get();
        $counts = FeedItem::selectRaw('feed_channel_id, status, count(*) as n')->groupBy('feed_channel_id', 'status')->get()
            ->groupBy('feed_channel_id')->map(fn ($rows) => $rows->pluck('n', 'status'));
        $postedToday = FeedItem::where('posted_at', '>=', $today)->selectRaw('feed_channel_id, count(*) as n')->groupBy('feed_channel_id')->pluck('n', 'feed_channel_id');
        // How the week's posts were met: likes and comments per post.
        $week = Post::where('created_at', '>=', now()->subWeek())->whereIn('user_id', $channels->pluck('user_id'))
            ->withCount(['likes', 'comments'])->get(['id', 'user_id'])->groupBy('user_id');

        return Inertia::render('admin/feeds', [
            'tab' => $tab,
            'status' => $status,
            'author' => $channelId,
            'paused' => FeedPublisher::paused(),
            'members_busy' => FeedPublisher::membersAreBusy(),
            'window' => config('feeds.window'),
            'channels' => $channels->map(fn (FeedChannel $c) => [
                'id' => $c->id,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'username' => $c->user->username] : null,
                'topic' => $c->topic,
                'group' => $c->group ? ['name' => $c->group->name, 'slug' => $c->group->slug] : null,
                'daily_min' => $c->daily_min,
                'daily_limit' => $c->daily_limit,
                'stop_words' => $c->stop_words,
                'auto_approve' => $c->auto_approve,
                'paused' => $c->paused,
                'waiting' => (int) ($counts[$c->id]['new'] ?? 0),
                'approved' => (int) ($counts[$c->id]['approved'] ?? 0),
                'posted_today' => (int) ($postedToday[$c->id] ?? 0),
                // When the author posts today: its own times, different each day.
                'plan' => $c->paused ? [] : FeedPublisher::plan($c),
                'posts_week' => $week->get($c->user_id)?->count() ?? 0,
                'likes_week' => $week->get($c->user_id)?->sum('likes_count') ?? 0,
                'comments_week' => $week->get($c->user_id)?->sum('comments_count') ?? 0,
                // What members asked of the author; its curator answers here (a letter tells the club's inbox).
                'requests' => $c->user ? $c->user->friendRequesters()->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]) : [],
                'invitations' => GroupInvitation::with(['group:id,name,slug', 'from:id,name'])->where('user_id', $c->user_id)->get()
                    ->filter(fn (GroupInvitation $i) => $i->group)
                    ->map(fn (GroupInvitation $i) => ['id' => $i->id, 'group' => $i->group->name, 'slug' => $i->group->slug, 'from' => $i->from?->name])->values(),
            ]),
            'sources' => $tab !== 'sources' ? [] : FeedSource::withCount(['items' => fn ($q) => $q->where('status', 'posted')])
                ->orderBy('feed_channel_id')->orderBy('name')->get()->map(fn (FeedSource $s) => [
                    'id' => $s->id,
                    'channel_id' => $s->feed_channel_id,
                    'name' => $s->name,
                    'url' => $s->url,
                    'locale' => $s->locale,
                    'trusted' => $s->trusted,
                    'active' => $s->active,
                    'fetched_at' => $s->fetched_at?->toIso8601String(),
                    'error' => $s->error,
                    'posted' => $s->items_count,
                ]),
            'items' => $tab !== 'queue' ? null : FeedItem::with(['source:id,name', 'channel.user:id,name'])
                ->where('status', $status)
                // News that simply was not posted in time is gone from the page.
                ->when($status === 'skipped', fn ($q) => $q->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'stale')))
                ->when($channelId, fn ($q) => $q->where('feed_channel_id', $channelId))
                ->when($status === 'posted', fn ($q) => $q->latest('posted_at'), fn ($q) => $q->latest('id'))
                ->paginate(50)->withQueryString()
                ->through(fn (FeedItem $i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'summary' => $i->summary,
                    'url' => $i->url,
                    'image' => $i->image,
                    'source' => $i->source?->name,
                    'author' => $i->channel?->user?->name,
                    'status' => $i->status,
                    'reason' => $i->reason,
                    'published_at' => ($i->published_at ?? $i->created_at)?->toIso8601String(),
                    'posted_at' => $i->posted_at?->toIso8601String(),
                    'post_id' => $i->post_id,
                ]),
        ]);
    }

    public function pause(Request $request): RedirectResponse
    {
        $paused = $request->validate(['paused' => ['required', 'boolean']])['paused'];
        Setting::updateOrCreate(['key' => 'feeds_paused'], ['value' => $paused ? '1' : '0']);
        Cache::memo()->forget('settings');

        return back();
    }

    public function updateChannel(Request $request, FeedChannel $channel): RedirectResponse
    {
        $channel->update($request->validate([
            'topic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'daily_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'daily_limit' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'stop_words' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'auto_approve' => ['sometimes', 'boolean'],
            'paused' => ['sometimes', 'boolean'],
        ]));

        return back();
    }

    public function storeSource(Request $request): RedirectResponse
    {
        FeedSource::create($request->validate([
            'feed_channel_id' => ['required', 'exists:feed_channels,id'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:1000'],
            'locale' => ['nullable', 'string', 'max:8'],
            'trusted' => ['boolean'],
        ]));

        return back();
    }

    public function updateSource(Request $request, FeedSource $source): RedirectResponse
    {
        $source->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url:http,https', 'max:1000'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'trusted' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]));

        return back();
    }

    public function destroySource(FeedSource $source): RedirectResponse
    {
        $source->delete();

        return back();
    }

    /** Reads one source now, to check a new or fixed address. */
    public function fetchSource(FeedSource $source, FeedCollector $collector): RedirectResponse
    {
        $collector->collect($source);

        return back();
    }

    /** Approve or skip items of the queue (skipped ones can be approved later). */
    public function status(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['approved', 'skipped', 'new'])],
        ]);
        FeedItem::whereIn('id', $data['ids'])->where('status', '!=', 'posted')
            ->update(['status' => $data['status'], 'reason' => $data['status'] === 'skipped' ? 'by curator' : null]);

        return back();
    }

    /** A curator's own items: recipes, folk jokes, a question to readers. Several at once, separated by a line "---". */
    public function storeItems(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feed_channel_id' => ['required', 'exists:feed_channels,id'],
            'text' => ['required', 'string', 'max:100000'],
            'url' => ['nullable', 'url:http,https', 'max:1000'],
        ]);
        $texts = array_values(array_filter(array_map('trim', preg_split('/^\s*-{3,}\s*$/m', $data['text']))));
        foreach ($texts as $text) {
            $url = count($texts) === 1 ? ($data['url'] ?? null) : null;
            FeedItem::create([
                'feed_channel_id' => $data['feed_channel_id'],
                'url' => $url,
                'url_hash' => $url ? sha1(FeedItem::normalizeUrl($url)) : null,
                'summary' => mb_substr($text, 0, 5000),
                'status' => 'approved',
            ]);
        }

        return back();
    }

    public function publishNow(FeedItem $item, FeedPublisher $publisher): RedirectResponse
    {
        abort_if($item->status === 'posted', 422);
        $publisher->publish($item);

        return back();
    }

    /** The curator accepts (or with $accept false declines) a member's friend request to a club author. */
    public function answerFriend(Request $request, FeedChannel $channel, User $user): RedirectResponse
    {
        $author = $channel->user;
        $pending = DB::table('friendships')->where('user_id', $user->id)->where('friend_id', $author->id)->where('accepted', false);
        abort_unless($pending->exists(), 404);
        if ($request->boolean('accept')) {
            $pending->update(['accepted' => true, 'updated_at' => now()]);
            ClubActivity::send($user, 'friend_accepted', $author, route('people.show', $author), 'user:'.$user->id);
        } else {
            $pending->delete();
        }
        ClubActivity::retract($author, 'friend_request', $user, 'user:'.$author->id);

        return back();
    }

    /** The curator takes a club author into a group it was invited to, or declines. */
    public function answerInvitation(Request $request, FeedChannel $channel, GroupInvitation $invitation): RedirectResponse
    {
        $author = $channel->user;
        abort_unless($invitation->user_id === $author->id && $invitation->group, 404);
        $group = $invitation->group;
        if ($request->boolean('accept')) {
            $approved = ! $group->is_private || ($invitation->from && $group->canModerate($invitation->from));
            $group->members()->syncWithoutDetaching([$author->id => ['approved' => $approved]]);
            Group::forgetRoles();
        }
        if ($invitation->from) {
            ClubActivity::retract($author, 'group_invite', $invitation->from, 'group:'.$group->id);
        }
        $invitation->delete();

        return back();
    }
}
