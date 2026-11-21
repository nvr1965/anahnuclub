<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Notifications\ReportReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * "Report" on a post or a comment: anyone who can see it, members and guests, tells the team what is
 * wrong. The team gets a letter and a line on the admin page "Complaints", where it removes or keeps it.
 */
class ReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(['post', 'comment'])],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'details' => [$request->input('reason') === 'other' ? 'required' : 'nullable', 'string', 'max:2000'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:190'],
        ]);

        // Only what this visitor can see may be reported (a closed group's post stays unknown to others).
        $target = $data['type'] === 'post' ? Post::findOrFail($data['id']) : Comment::findOrFail($data['id']);
        $postId = $target instanceof Post ? $target->id : $target->post_id;
        abort_unless(self::canSee($request, Post::find($postId)), 404);

        $key = 'report:'.($user?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return back()->withErrors(['reason' => __('Too many messages. Please try again later.')]);
        }
        RateLimiter::hit($key, 3600);

        // One open complaint per person and item: a second click changes nothing.
        $open = Report::where('reportable_type', $data['type'])->where('reportable_id', $target->id)->where('status', 'open')
            ->where(fn ($q) => $user ? $q->where('reporter_id', $user->id) : $q->whereNull('reporter_id')->where('ip', $request->ip()))
            ->exists();
        if (! $open) {
            $report = Report::create([
                'reporter_id' => $user?->id,
                'email' => $user ? null : $data['email'],
                'ip' => $request->ip(),
                'reportable_type' => $data['type'],
                'reportable_id' => $target->id,
                'post_id' => $postId,
                'author_id' => $target->user_id,
                'snapshot' => self::snapshot($target),
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
            ]);
            if ($to = config('services.contact.email')) {
                Notification::route('mail', $to)->notify(new ReportReceived($report));
            }
        }

        return back()->with('reported', true);
    }

    /** The same check as the post's own page (PostController::show): its group, then who may read it. */
    private static function canSee(Request $request, ?Post $post): bool
    {
        if (! $post) {
            return false;
        }
        $user = $request->user();
        if ($user?->is_admin) {
            return true;
        }
        if ($group = $post->group) {
            if (! $group->published || ($group->is_private && ! ($user && $group->members()->whereKey($user->id)->exists()))) {
                return false;
            }
        }

        return FeedController::postQuery($request)->whereKey($post->id)->exists();
    }

    /** The reported text as it was, kept with the complaint even if the post is later edited or removed. */
    public static function snapshot(Post|Comment $target): string
    {
        $title = $target instanceof Post && $target->title ? $target->title."\n" : '';
        $text = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', "\n", (string) $target->body)), ENT_QUOTES | ENT_HTML5);

        return mb_substr(trim($title.$text), 0, 5000);
    }
}
