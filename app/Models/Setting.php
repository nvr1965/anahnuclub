<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Small site-wide values an administrator can change, such as the donation link. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        // The PayPal button of the old site.
        'donate_links' => '[{"label":"PayPal","url":"https://www.paypal.com/donate/?hosted_button_id=9K3YLUVSY8K72"}]',
    ];

    public static function get(string $key): ?string
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Ways to donate shown under the footer button; with $all also the ones switched off in the admin.
     *
     * @return list<array{label: string, url: string, on?: bool}>
     */
    public static function donateLinks(bool $all = false): array
    {
        // Saved before the switch existed: on.
        $links = array_map(fn ($l) => $l + ['on' => true], json_decode(static::get('donate_links') ?? '[]', true) ?: []);

        return $all
            ? array_values($links)
            : array_values(array_map(fn ($l) => ['label' => $l['label'], 'url' => $l['url']], array_filter($links, fn ($l) => $l['on'])));
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
