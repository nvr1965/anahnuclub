<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    /** A browser's side of a subscription: its key pair and auth secret. */
    private function device(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        return ['key' => $key, 'p256dh' => WebPush::b64encode(WebPush::rawPublic($key)), 'auth' => WebPush::b64encode(random_bytes(16))];
    }

    /** Decrypts the way the browser does (RFC 8291), to prove the message can be read on the phone. */
    private function decrypt(string $body, array $device): string
    {
        $salt = substr($body, 0, 16);
        $idLength = ord($body[20]);
        $asPublic = substr($body, 21, $idLength);
        $data = substr($body, 21 + $idLength);
        $uaPublic = WebPush::b64decode($device['p256dh']);
        $shared = openssl_pkey_derive(WebPush::publicKeyFromRaw($asPublic), $device['key'], 32);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, WebPush::b64decode($device['auth']));
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($data, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($data, -16));
        $this->assertNotFalse($plain);

        return rtrim($plain, "\x02");
    }

    public function test_the_message_is_encrypted_so_only_the_device_can_read_it()
    {
        $device = $this->device();
        $body = WebPush::encrypt('{"title":"Анна","body":"Привет"}', $device['p256dh'], $device['auth']);

        $this->assertSame('{"title":"Анна","body":"Привет"}', $this->decrypt($body, $device));
    }

    /** The worked example of RFC 8291, appendix A, byte for byte. */
    public function test_encryption_matches_the_standards_example()
    {
        $d = WebPush::b64decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
        $public = WebPush::b64decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$public;
        $key = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");

        $body = WebPush::encrypt(
            'When I grow up, I want to be a watermelon',
            'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
            'BTBZMqHH6r4Tts7J_aSIgg',
            WebPush::b64decode('DGv6ra1nlYgDCS1FRnbzlw'),
            $key,
        );

        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPush::b64encode($body),
        );
    }

    public function test_the_vapid_token_is_signed_with_the_sites_key()
    {
        $header = WebPush::authorization('https://fcm.googleapis.com/fcm/send/abc');
        $this->assertMatchesRegularExpression('/^vapid t=[\w-]+\.[\w-]+\.[\w-]+, k=[\w-]+$/', $header);
        preg_match('/t=([\w-]+)\.([\w-]+)\.([\w-]+), k=([\w-]+)/', $header, $m);
        $claims = json_decode(WebPush::b64decode($m[2]), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame(WebPush::publicKey(), $m[4]);

        // The signature checks out against the published key.
        $raw = WebPush::b64decode($m[3]);
        $int = fn (string $b) => "\x02".chr(strlen($b = (ord($b[0]) & 0x80 ? "\0" : '').ltrim($b, "\0") ?: "\0")).$b;
        $r = $int(substr($raw, 0, 32));
        $s = $int(substr($raw, 32));
        $der = "\x30".chr(strlen($r.$s)).$r.$s;
        $this->assertSame(1, openssl_verify($m[1].'.'.$m[2], $der, WebPush::publicKeyFromRaw(WebPush::b64decode($m[4])), OPENSSL_ALGO_SHA256));
    }

    public function test_a_new_message_reaches_the_other_members_phone()
    {
        Http::fake(['push.example/*' => Http::response('', 201)]);
        $anna = User::factory()->create(['name' => 'Анна']);
        $dana = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);
        $device = $this->device();

        $this->actingAs($dana)->getJson('/push/key')->assertOk()->assertJsonPath('key', WebPush::publicKey());
        $this->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/dana', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]])->assertNoContent();
        // Anna's own device gets nothing for her own message.
        $this->actingAs($anna)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/anna', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]])->assertNoContent();

        $this->postJson("/messages/{$conversation->id}", ['body' => 'Привет, как дела?'])->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($device, $conversation) {
            $payload = json_decode($this->decrypt($request->body(), $device), true);

            return $request->url() === 'https://push.example/dana'
                && $request->hasHeader('Content-Encoding', 'aes128gcm')
                && str_starts_with($request->header('Authorization')[0], 'vapid t=')
                && $payload['title'] === 'Анна'
                && $payload['body'] === 'Привет, как дела?'
                && $payload['url'] === "/messages/{$conversation->id}";
        });
    }

    public function test_a_device_the_push_service_forgot_is_dropped_and_one_can_switch_off()
    {
        Http::fake(['push.example/*' => Http::response('', 410)]);
        $anna = User::factory()->create();
        $dana = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);
        $device = $this->device();
        $this->actingAs($dana)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/old', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);
        $this->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/new', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);
        $this->deleteJson('/push/subscriptions', ['endpoint' => 'https://push.example/new'])->assertNoContent();
        $this->assertSame(['https://push.example/old'], PushSubscription::pluck('endpoint')->all());

        $this->actingAs($anna)->postJson("/messages/{$conversation->id}", ['body' => 'Привет'])->assertCreated();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_an_incoming_call_rings_the_phone()
    {
        Http::fake(['push.example/*' => Http::response('', 201)]);
        $anna = User::factory()->create(['name' => 'Анна']);
        $dana = User::factory()->create(['locale' => 'ru', 'ui_locale' => 'ru']);
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);
        $conversation->users()->updateExistingPivot($dana->id, ['accepted_at' => now()]);
        $device = $this->device();
        $this->actingAs($dana)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/dana', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);

        $this->actingAs($anna)->postJson("/messages/{$conversation->id}/call", ['video' => true])->assertOk();

        Http::assertSent(function (Request $request) use ($device) {
            $payload = json_decode($this->decrypt($request->body(), $device), true);

            return $payload['kind'] === 'call' && $payload['body'] === 'Входящий видеозвонок' && $request->header('TTL')[0] === '45';
        });
    }

    public function test_the_browser_can_move_a_subscription_to_a_new_address()
    {
        $dana = User::factory()->create();
        $device = $this->device();
        $this->actingAs($dana)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/old', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);
        auth()->logout();

        $this->postJson('/push/subscriptions/renew', ['old' => 'https://push.example/unknown', 'subscription' => ['endpoint' => 'https://push.example/x', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]])->assertNotFound();
        $this->postJson('/push/subscriptions/renew', ['old' => 'https://push.example/old', 'subscription' => ['endpoint' => 'https://push.example/new', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]])->assertNoContent();

        $this->assertSame([[$dana->id, 'https://push.example/new']], PushSubscription::get()->map(fn ($s) => [$s->user_id, $s->endpoint])->all());
    }

    public function test_a_ringing_call_rings_again_and_turns_into_a_missed_call()
    {
        Http::fake(['push.example/*' => Http::response('', 201)]);
        $anna = User::factory()->create(['name' => 'Анна']);
        $dana = User::factory()->create(['locale' => 'ru', 'ui_locale' => 'ru']);
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);
        $conversation->users()->updateExistingPivot($dana->id, ['accepted_at' => now()]);
        $device = $this->device();
        $this->actingAs($dana)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/dana', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);
        $kinds = function () use ($device) {
            return Http::recorded()->map(fn ($pair) => json_decode($this->decrypt($pair[0]->body(), $device), true)['kind'])->all();
        };

        $id = $this->actingAs($anna)->postJson("/messages/{$conversation->id}/call", ['video' => false])->json('call.id');
        // The caller's page asks right away: too soon to ring again.
        $this->getJson("/calls/{$id}")->assertOk();
        $this->assertSame(['call'], $kinds());

        $this->travel(10)->seconds();
        $this->getJson("/calls/{$id}")->assertOk();
        $this->getJson("/calls/{$id}")->assertOk();
        $this->assertSame(['call', 'call'], $kinds());

        $this->postJson("/calls/{$id}/end")->assertOk();
        $this->assertSame(['call', 'call', 'missed-call'], $kinds());
    }

    public function test_check_notifications_sends_a_test_and_reports_each_device()
    {
        Http::fake(['push.example/ok' => Http::response('', 201), 'push.example/bad' => Http::response('', 403)]);
        $dana = User::factory()->create();
        $device = $this->device();
        $this->actingAs($dana);
        foreach (['ok', 'bad'] as $name) {
            $this->postJson('/push/subscriptions', ['endpoint' => "https://push.example/{$name}", 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);
        }

        $this->postJson('/push/test')->assertOk()
            ->assertJsonPath('devices.0.status', 201)
            ->assertJsonPath('devices.1.status', 403)
            ->assertJsonPath('devices.0.service', 'push.example');
        Http::assertSent(fn (Request $request) => json_decode($this->decrypt($request->body(), $device), true)['kind'] === 'test');
    }

    public function test_on_a_host_with_a_cron_queue_the_push_still_goes_out_at_once()
    {
        // As on shared hosting without FPM: background work waits for cron, a phone call cannot.
        config(['queue.default' => 'database']);
        Http::fake(['push.example/*' => Http::response('', 201)]);
        $anna = User::factory()->create();
        $dana = User::factory()->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);
        $device = $this->device();
        $this->actingAs($dana)->postJson('/push/subscriptions', ['endpoint' => 'https://push.example/dana', 'keys' => ['p256dh' => $device['p256dh'], 'auth' => $device['auth']]]);

        $this->actingAs($anna)->postJson("/messages/{$conversation->id}", ['body' => 'Привет'])->assertCreated();

        Http::assertSentCount(1);
        $this->assertFalse(DB::table('jobs')->where('payload', 'like', '%SendWebPush%')->exists());
    }
}
