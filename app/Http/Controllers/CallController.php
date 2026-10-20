<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Video and audio calls in a chat. The browsers connect to each other directly (WebRTC); the site
 * only rings the other member and passes their connection details back and forth, which each page
 * picks up by asking every second or so, since shared hosting has no live connection.
 */
class CallController extends Controller
{
    public function start(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();
        $members = $conversation->users()->get();
        abort_unless($members->contains($me), 404);
        $other = $members->firstWhere('id', '!=', $me->id);
        abort_if(! $other, 422);
        // Only into a chat the other member has let in, and only while they still take messages.
        abort_if(! $me->canMessage($other) || $other->pivot->accepted_at === null, 403);
        $video = $request->boolean('video', true);

        $live = Call::live()->where(fn ($q) => $q->whereIn('caller_id', [$me->id, $other->id])->orWhereIn('callee_id', [$me->id, $other->id]))->get()
            ->each->settle()->filter->isLive();
        if ($live->contains(fn (Call $c) => $c->involves($other) && $c->conversation_id !== $conversation->id)) {
            return response()->json(['busy' => true], 409);
        }
        // Calling again replaces this chat's earlier call, and whatever this member left hanging elsewhere ends.
        $live->each->update(['status' => 'ended', 'ended_at' => now()]);

        $call = Call::create([
            'conversation_id' => $conversation->id,
            'caller_id' => $me->id,
            'callee_id' => $other->id,
            'video' => $video,
            'status' => 'ringing',
            'caller_seen_at' => now(),
        ]);

        return response()->json(['call' => $this->payload($call, $me)]);
    }

    /** Asked every few seconds by each open page: is someone calling me? */
    public function ringing(Request $request): JsonResponse
    {
        $me = $request->user();
        $call = Call::where('callee_id', $me->id)->where('status', 'ringing')->latest('id')->first()?->settle();

        return response()->json(['call' => $call?->isLive() ? $this->payload($call, $me) : null]);
    }

    /** The call's state and what the other side sent since signal $after. */
    public function show(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->involves($me), 404);
        if ($call->isLive()) {
            $call->touchBy($me);
        }
        $call->settle();

        $signals = $call->signals()
            ->where('user_id', '!=', $me->id)
            ->where('id', '>', (int) $request->query('after', 0))
            ->orderBy('id')
            ->get(['id', 'type', 'payload']);

        return response()->json(['call' => $this->payload($call, $me), 'signals' => $signals]);
    }

    public function signal(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->involves($me), 404);
        $data = $request->validate([
            'type' => ['required', 'in:offer,answer,candidate'],
            'payload' => ['required', 'string', 'max:20000'],
        ]);
        abort_unless($call->settle()->isLive(), 410);

        $call->signals()->create(['user_id' => $me->id] + $data);

        return response()->json(['ok' => true]);
    }

    public function accept(Request $request, Call $call): JsonResponse
    {
        $me = $request->user();
        abort_unless($call->callee_id === $me->id, 404);
        abort_unless($call->settle()->status === 'ringing', 410);
        $call->update(['status' => 'active', 'answered_at' => now(), 'callee_seen_at' => now(), 'caller_seen_at' => now()]);

        return response()->json(['call' => $this->payload($call, $me)]);
    }

    public function decline(Request $request, Call $call): JsonResponse
    {
        abort_unless($call->callee_id === $request->user()->id, 404);
        if ($call->status === 'ringing') {
            $call->update(['status' => 'declined', 'ended_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /** Hang up: before an answer it is a missed call for the other member, after it the call ends. */
    public function end(Request $request, Call $call): JsonResponse
    {
        abort_unless($call->involves($request->user()), 404);
        if ($call->isLive()) {
            $call->update(['status' => $call->status === 'ringing' ? 'missed' : 'ended', 'ended_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    private function payload(Call $call, User $me): array
    {
        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'status' => $call->status,
            'video' => $call->video,
            'outgoing' => $call->caller_id === $me->id,
            'with' => PeopleController::card($call->otherThan($me)),
            'answered_at' => $call->answered_at?->toIso8601String(),
            'ice' => self::iceServers(),
        ];
    }

    /** STUN finds each side's address; a TURN relay (optional, in .env) carries calls that cannot connect directly. */
    public static function iceServers(): array
    {
        $servers = [['urls' => array_values(array_filter(explode(',', (string) config('services.webrtc.stun'))))]];
        if ($turn = array_values(array_filter(explode(',', (string) config('services.webrtc.turn_urls'))))) {
            $servers[] = ['urls' => $turn, 'username' => config('services.webrtc.turn_username'), 'credential' => config('services.webrtc.turn_credential')];
        }

        return $servers;
    }
}
