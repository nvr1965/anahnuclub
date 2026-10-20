<?php

namespace App\Translation;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Keys and addresses of the translation services entered on the admin page "Translators".
 * They are stored encrypted with APP_KEY and take the place of the same values from .env;
 * a value removed here falls back to .env again.
 */
class TranslatorKeys
{
    private const SETTING = 'translator_keys';

    /** @return array<string, string> config path => value */
    public static function saved(): array
    {
        $raw = Setting::get(self::SETTING);
        if (! $raw) {
            return [];
        }
        try {
            return json_decode(Crypt::decryptString($raw), true) ?: [];
        } catch (Throwable) {
            // APP_KEY changed: the old values cannot be read and have to be entered again.
            return [];
        }
    }

    /** Puts the saved values into the config, so the services are built with them. */
    public static function apply(): void
    {
        // The values from .env, kept aside the first time, before any saved value is laid over them.
        if (! is_array($fromEnv = config('translation.from_env'))) {
            $fromEnv = array_combine(self::paths(), array_map(fn ($path) => config($path), self::paths()));
            config(['translation.from_env' => $fromEnv]);
        }
        $saved = self::saved();
        foreach ($fromEnv as $path => $value) {
            config([$path => $saved[$path] ?? $value]);
        }
    }

    /**
     * Saves the given values; an empty value removes the saved one.
     *
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        $saved = self::saved();
        foreach ($values as $path => $value) {
            if (in_array($path, self::paths(), true)) {
                $value = trim((string) $value);
                if ($value === '') {
                    unset($saved[$path]);
                } else {
                    $saved[$path] = $value;
                }
            }
        }
        Setting::put(self::SETTING, $saved ? Crypt::encryptString(json_encode($saved)) : null);
        self::apply();
    }

    /**
     * One service's fields for the admin page. Secret values are never sent whole, only their last characters.
     *
     * @return list<array{path: string, env: string, secret: bool, value: string, source: string|null}>
     */
    public static function fields(string $id): array
    {
        self::apply();
        $saved = self::saved();

        return array_map(function (string $path, string $env) use ($saved) {
            $secret = str_ends_with($env, '_KEY');
            $value = (string) config($path);

            return [
                'path' => $path,
                'env' => $env,
                'secret' => $secret,
                'value' => $secret && $value !== '' ? '••••'.mb_substr($value, -4) : $value,
                'source' => isset($saved[$path]) ? 'admin' : ($value !== '' ? 'env' : null),
            ];
        }, array_keys(config('translation.providers.'.$id.'.fields', [])), config('translation.providers.'.$id.'.fields', []));
    }

    /** @return list<string> every config path a service can take from this page */
    public static function paths(?string $id = null): array
    {
        $providers = $id ? [config('translation.providers.'.$id, [])] : config('translation.providers');

        return array_values(array_unique(array_merge(...array_map(fn ($p) => array_keys($p['fields'] ?? []), array_values($providers)))));
    }
}
