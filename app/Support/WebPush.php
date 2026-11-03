<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Web Push without an extra library: the message is encrypted for the device (RFC 8291, aes128gcm)
 * and signed with the site's key (VAPID, RFC 8292), then handed to the browser's push service
 * (Google, Apple, Mozilla), which wakes the phone even when the site is closed.
 * The site's key pair is made once and kept in the settings table.
 */
class WebPush
{
    /** The P-256 public key header of a DER SubjectPublicKeyInfo; the 65-byte point follows. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** The site's public key, as the browser needs it to subscribe (base64url). */
    public static function publicKey(): string
    {
        return self::keys()['public'];
    }

    /**
     * Sends $payload to every device of the members; devices the push service no longer knows are forgotten.
     *
     * @param  iterable<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public static function toUsers(iterable $userIds, array $payload, int $ttl = 86400, string $urgency = 'high'): void
    {
        $ids = collect($userIds)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach (PushSubscription::whereIn('user_id', $ids)->get() as $subscription) {
            self::send($subscription, $json, $ttl, $urgency);
        }
    }

    /** The push service's answer: 201 delivered to it, 0 no answer, anything else refused. */
    public static function send(PushSubscription $subscription, string $payload, int $ttl = 86400, string $urgency = 'high'): int
    {
        try {
            $body = self::encrypt($payload, $subscription->p256dh, $subscription->auth);
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => self::authorization($subscription->endpoint),
                    'Content-Encoding' => 'aes128gcm',
                    'TTL' => (string) $ttl,
                    'Urgency' => $urgency,
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($subscription->endpoint);
        } catch (\Throwable $e) {
            Log::warning('Web push failed: '.$e->getMessage());

            return 0;
        }
        // Unsubscribed, expired or a broken record: no use trying again.
        if (in_array($response->status(), [400, 403, 404, 410], true)) {
            $subscription->delete();
        } elseif (! $response->successful()) {
            Log::warning('Web push refused: '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        return $response->status();
    }

    /** The message encrypted for one device (RFC 8291), with the aes128gcm header in front. */
    public static function encrypt(string $payload, string $p256dh, string $authSecret, ?string $salt = null, ?\OpenSSLAsymmetricKey $local = null): string
    {
        $uaPublic = self::b64decode($p256dh);
        $auth = self::b64decode($authSecret);
        if (strlen($uaPublic) !== 65 || strlen($auth) < 16) {
            throw new RuntimeException('Bad subscription keys.');
        }
        $local ??= self::newKey();
        $asPublic = self::rawPublic($local);
        $shared = openssl_pkey_derive(self::publicKeyFromRaw($uaPublic), $local, 32);
        if ($shared === false) {
            throw new RuntimeException('Key agreement failed.');
        }
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $auth);
        $salt ??= random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        // One record: the text, then the "last record" mark.
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** "vapid t=<signed token>, k=<public key>" for the push service at $endpoint. */
    public static function authorization(string $endpoint): string
    {
        $keys = self::keys();
        $parts = parse_url($endpoint);
        $claims = [
            'aud' => $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:'.(config('mail.from.address') ?: 'admin@anahnu.club'),
        ];
        $unsigned = self::b64encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])).'.'.self::b64encode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        openssl_sign($unsigned, $der, $keys['private'], OPENSSL_ALGO_SHA256);

        return 'vapid t='.$unsigned.'.'.self::b64encode(self::derToRaw($der)).', k='.$keys['public'];
    }

    /** @return array{public: string, private: string} */
    private static function keys(): array
    {
        $private = Setting::get('vapid_private_key');
        $public = Setting::get('vapid_public_key');
        if (! $private || ! $public) {
            $key = self::newKey();
            openssl_pkey_export($key, $private);
            $public = self::b64encode(self::rawPublic($key));
            Setting::put('vapid_private_key', $private);
            Setting::put('vapid_public_key', $public);
        }

        return ['public' => $public, 'private' => $private];
    }

    private static function newKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new RuntimeException('Cannot make an EC key.');
        }

        return $key;
    }

    /** The uncompressed point 0x04 || X || Y of a P-256 key. */
    public static function rawPublic(\OpenSSLAsymmetricKey $key): string
    {
        $ec = openssl_pkey_get_details($key)['ec'];

        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw): \OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::SPKI_PREFIX).$raw), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Bad public key.');
        }

        return $key;
    }

    /** OpenSSL signs in DER (SEQUENCE of two INTEGERs); JWT wants r and s as 32 bytes each. */
    private static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $int = ltrim(substr($der, $offset + 2, $length), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $out;
    }

    public static function b64encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
