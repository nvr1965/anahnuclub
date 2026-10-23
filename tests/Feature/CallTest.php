<?php

namespace Tests\Feature;

use App\Http\Controllers\CallController;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private function friends(): array
    {
        $anna = User::factory()->create();
        $dana = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);

        return [$anna, $dana, Conversation::between($anna, $dana)];
    }

    public function test_a_call_rings_connects_passes_signals_and_ends()
    {
        [$anna, $dana, $chat] = $this->friends();

        $id = $this->actingAs($anna)->postJson("/messages/{$chat->id}/call", ['video' => true])
            ->assertOk()->assertJsonPath('call.status', 'ringing')->assertJsonPath('call.outgoing', true)->json('call.id');
        $this->postJson("/calls/$id/signal", ['type' => 'offer', 'payload' => '{"type":"offer","sdp":"x"}'])->assertOk();

        $this->actingAs($dana)->getJson('/calls/ringing')->assertJsonPath('call.id', $id)->assertJsonPath('call.with.id', $anna->id);
        $this->postJson("/calls/$id/accept")->assertJsonPath('call.status', 'active');
        // Dana gets Anna's offer, not her own signals.
        $this->postJson("/calls/$id/signal", ['type' => 'answer', 'payload' => '{"type":"answer","sdp":"y"}'])->assertOk();
        $this->getJson("/calls/$id")->assertJsonCount(1, 'signals')->assertJsonPath('signals.0.type', 'offer');

        $offerId = Call::find($id)->signals()->where('type', 'offer')->value('id');
        $this->actingAs($anna)->getJson("/calls/$id?after=$offerId")->assertJsonPath('signals.0.type', 'answer');

        $this->postJson("/calls/$id/end")->assertOk();
        $this->assertSame('ended', Call::find($id)->status);
        $this->actingAs($dana)->postJson("/calls/$id/signal", ['type' => 'candidate', 'payload' => '{}'])->assertStatus(410);
    }

    public function test_strangers_and_outsiders_cannot_call_or_listen_in()
    {
        [$anna, $dana, $chat] = $this->friends();
        $eve = User::factory()->create();

        $this->actingAs($eve)->postJson("/messages/{$chat->id}/call")->assertNotFound();
        $id = $this->actingAs($anna)->postJson("/messages/{$chat->id}/call")->json('call.id');
        $this->actingAs($eve)->getJson("/calls/$id")->assertNotFound();
        $this->postJson("/calls/$id/accept")->assertNotFound();

        // A chat request the other member has not accepted cannot ring them.
        $request = Conversation::between($eve, $anna);
        $this->actingAs($eve)->postJson("/messages/{$request->id}/call")->assertForbidden();

        // Neither can someone who blocked messages.
        DB::table('message_blocks')->insert(['user_id' => $dana->id, 'blocked_id' => $anna->id]);
        Call::query()->update(['status' => 'ended']);
        $this->actingAs($anna)->postJson("/messages/{$chat->id}/call")->assertForbidden();
    }

    public function test_becoming_friends_opens_calls_in_a_waiting_chat_request()
    {
        $eve = User::factory()->create();
        $anna = User::factory()->create();
        // Eve wrote before they were friends, so the chat waits for Anna.
        $chat = Conversation::between($eve, $anna);
        $this->actingAs($eve)->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.can_call', false));

        $this->actingAs($eve)->post("/people/{$anna->id}/friend");
        $this->actingAs($anna)->post("/people/{$eve->id}/friend");

        $this->actingAs($eve)->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.can_call', true));
        $this->actingAs($anna)->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.can_call', true)->where('conversation.request', false));
        $this->actingAs($eve)->postJson("/messages/{$chat->id}/call")->assertOk();
    }

    public function test_unanswered_calls_turn_missed_and_busy_members_are_not_rung()
    {
        [$anna, $dana, $chat] = $this->friends();
        $ben = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $ben->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $other = Conversation::between($ben, $dana);

        $id = $this->actingAs($anna)->postJson("/messages/{$chat->id}/call")->json('call.id');
        $this->actingAs($ben)->postJson("/messages/{$other->id}/call")->assertStatus(409)->assertJsonPath('busy', true);

        $this->travel(Call::RING_SECONDS + 5)->seconds();
        $this->actingAs($dana)->getJson('/calls/ringing')->assertJsonPath('call', null);
        $this->assertSame('missed', Call::find($id)->status);
        $this->actingAs($ben)->postJson("/messages/{$other->id}/call")->assertOk();
    }

    public function test_a_call_ends_when_one_side_goes_quiet()
    {
        [$anna, $dana, $chat] = $this->friends();
        $id = $this->actingAs($anna)->postJson("/messages/{$chat->id}/call")->json('call.id');
        $this->actingAs($dana)->postJson("/calls/$id/accept");

        $this->travel(Call::GONE_SECONDS - 5)->seconds();
        $this->getJson("/calls/$id")->assertJsonPath('call.status', 'active');
        $this->travel(10)->seconds();
        $this->getJson("/calls/$id")->assertJsonPath('call.status', 'ended');
    }

    public function test_the_chat_says_whether_a_call_is_possible()
    {
        [$anna, $dana, $chat] = $this->friends();
        $this->actingAs($anna)->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.can_call', true));

        $eve = User::factory()->create();
        $request = Conversation::between($eve, $anna);
        $this->actingAs($eve)->get("/messages/{$request->id}")->assertInertia(fn ($page) => $page->where('conversation.can_call', false));
    }

    public function test_cloudflare_turn_logins_are_fetched_once_and_reused()
    {
        config(['services.webrtc.cloudflare_key_id' => 'key', 'services.webrtc.cloudflare_api_token' => 'token']);
        Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
            ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp'], 'username' => 'u', 'credential' => 'c'],
        ]])]);

        $servers = CallController::iceServers();
        CallController::iceServers();

        $this->assertSame('u', $servers[1]['username']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer token') && str_contains($r->url(), '/keys/key/') && $r['ttl'] === 86400);
    }

    public function test_calls_still_work_when_cloudflare_does_not_answer()
    {
        config(['services.webrtc.cloudflare_key_id' => 'key', 'services.webrtc.cloudflare_api_token' => 'token', 'services.webrtc.turn_urls' => null]);
        Http::fake(['rtc.live.cloudflare.com/*' => Http::response('down', 500)]);

        $servers = CallController::iceServers();
        CallController::iceServers();

        $this->assertCount(1, $servers);
        Http::assertSentCount(1);
        $this->assertStringStartsWith('stun:', $servers[0]['urls'][0]);
    }
}
