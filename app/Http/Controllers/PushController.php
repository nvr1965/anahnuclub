<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Turning notifications on and off for one device. */
class PushController extends Controller
{
    public function key(): JsonResponse
    {
        return response()->json(['key' => WebPush::publicKey()]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:40'],
        ]);
        // A device signed in as someone else now belongs to whoever is signed in.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']],
        );

        return response()->noContent();
    }

    /**
     * The browser renewed a subscription by itself (from the service worker, which has no session
     * token): the old address, known only to that device, stands in for signing in.
     */
    public function renew(Request $request): Response
    {
        $data = $request->validate([
            'old' => ['required', 'string', 'max:2000'],
            'subscription.endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:120'],
            'subscription.keys.auth' => ['required', 'string', 'max:40'],
        ]);
        $old = PushSubscription::where('endpoint_hash', PushSubscription::hash($data['old']))->first();
        abort_unless($old, 404);
        $new = $data['subscription'];
        PushSubscription::where('endpoint_hash', PushSubscription::hash($new['endpoint']))->whereKeyNot($old->id)->delete();
        $old->update(['endpoint' => $new['endpoint'], 'endpoint_hash' => PushSubscription::hash($new['endpoint']), 'p256dh' => $new['keys']['p256dh'], 'auth' => $new['keys']['auth']]);

        return response()->noContent();
    }

    /**
     * "Check notifications": a test notification to every device of the member, with what each
     * push service answered, so a device that does not ring can be told apart from one never signed up.
     */
    public function test(Request $request): JsonResponse
    {
        $payload = json_encode([
            'kind' => 'test',
            'title' => 'Anahnu Club',
            'body' => __('Notifications work on this device.'),
            'url' => route('messages.index', [], false),
            'tag' => 'test',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // What went out before this test (a call, a message), read first so the test does not crowd it out.
        $recent = WebPush::recent($request->user()->id);
        $devices = PushSubscription::where('user_id', $request->user()->id)->get()->map(fn (PushSubscription $s) => [
            'service' => parse_url($s->endpoint, PHP_URL_HOST),
            'status' => WebPush::send($s, $payload, 300),
        ]);

        return response()->json(['devices' => $devices->values(), 'recent' => $recent]);
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::where('endpoint_hash', PushSubscription::hash($request->input('endpoint')))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->noContent();
    }
}
