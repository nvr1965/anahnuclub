<?php

namespace App\Translation;

use Anthropic\Client;
use App\Models\Setting;
use App\Translation\Providers\Claude;
use App\Translation\Providers\DeepL;
use App\Translation\Providers\Gemini;
use App\Translation\Providers\Google;
use App\Translation\Providers\LibreTranslate;
use App\Translation\Providers\MyMemory;
use App\Translation\Providers\Provider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The translation block: services lined up in order. Each text goes to the first one that is set up,
 * switched on, knows the target language and has room left in its daily limit; when a service fails,
 * the next one is tried. Free services can go first and paid ones after, as a reserve.
 *
 * The order, on/off and limits come from the admin page "Translators" (setting "translators"),
 * otherwise from config/translation.php.
 */
class TranslatorChain implements Translator
{
    /** Which service translated last, recorded with stored translations. */
    private ?string $last = null;

    /** @var array<string, Provider|null> */
    private array $built = [];

    /** @param  list<array{id: string, on: bool, daily_chars: int}>  $entries */
    public function __construct(private array $entries) {}

    public static function fromSettings(): self
    {
        $catalog = config('translation.providers');
        $order = config('translation.order');
        // The older single choice, TRANSLATION_DRIVER, still puts that service first.
        $driver = config('services.translation.driver');
        if ($driver && isset($catalog[$driver]) && in_array($driver, $order, true)) {
            $order = array_values(array_unique([$driver, ...$order]));
        }

        $saved = json_decode(Setting::get('translators') ?? 'null', true);
        if (! is_array($saved)) {
            $saved = array_map(fn ($id) => ['id' => $id, 'on' => true, 'daily_chars' => $catalog[$id]['daily_chars'] ?? 0], $order);
        }

        return new self(self::merge($saved, $catalog));
    }

    /**
     * The saved (or configured) list, plus the other known services, switched off, at the end.
     *
     * @return list<array{id: string, on: bool, daily_chars: int}>
     */
    private static function merge(array $saved, array $catalog): array
    {
        $entries = [];
        foreach ($saved as $row) {
            if (isset($row['id'], $catalog[$row['id']])) {
                $entries[$row['id']] = ['id' => $row['id'], 'on' => (bool) ($row['on'] ?? true), 'daily_chars' => max(0, (int) ($row['daily_chars'] ?? 0))];
            }
        }
        foreach ($catalog as $id => $info) {
            $entries[$id] ??= ['id' => $id, 'on' => false, 'daily_chars' => (int) $info['daily_chars']];
        }

        return array_values($entries);
    }

    /** For tests and other code: use this provider object for an id instead of building one. */
    public function with(string $id, Provider $provider): self
    {
        $this->built[$id] = $provider;

        return $this;
    }

    /**
     * Every service for the admin page: what it is, whether it is set up, and today's use.
     *
     * @return list<array<string, mixed>>
     */
    public function describe(): array
    {
        return array_map(function (array $entry) {
            $info = config('translation.providers.'.$entry['id']);

            return $entry + [
                'label' => $info['label'],
                'price' => $info['price'],
                'note' => $info['note'] ?? '',
                'ready' => $this->ready($entry['id']),
                'needs' => array_values($info['needs']),
                'used_today' => $this->usedToday($entry['id']),
            ];
        }, $this->entries);
    }

    /** Whether any service can translate at all. */
    public function available(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['on'] && $this->ready($entry['id'])) {
                return true;
            }
        }

        return false;
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->translateMany([$text], $targetLocale, $sourceLocale)[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $chars = array_sum(array_map('mb_strlen', $texts));
        $errors = [];

        foreach ($this->entries as $entry) {
            $provider = $entry['on'] ? $this->provider($entry['id']) : null;
            if (! $provider || ($entry['daily_chars'] > 0 && $this->usedToday($entry['id']) + $chars > $entry['daily_chars'])) {
                continue;
            }
            if (! $provider->supports($targetLocale)) {
                continue;
            }
            try {
                $done = $provider->translateMany(array_values($texts), $targetLocale, $sourceLocale);
            } catch (Throwable $e) {
                Log::warning('Translator failed, trying the next one', ['provider' => $entry['id'], 'error' => $e->getMessage()]);
                $errors[] = $entry['id'].': '.$e->getMessage();

                continue;
            }
            $this->record($entry['id'], $chars);
            $this->last = $entry['id'];

            return $done;
        }

        throw new RuntimeException('No translator could translate into '.$targetLocale.($errors ? ' ('.implode('; ', $errors).')' : ''));
    }

    /** Whether some service that is on would take this language. */
    public function supports(string $locale): bool
    {
        foreach ($this->entries as $entry) {
            $provider = $entry['on'] ? $this->provider($entry['id']) : null;
            if ($provider && $provider->supports($locale)) {
                return true;
            }
        }

        return false;
    }

    /** One service on its own, for the "Check" button. */
    public function only(string $id): ?Provider
    {
        return $this->provider($id);
    }

    public function name(): string
    {
        return $this->last ?? 'chain';
    }

    public function usedToday(string $id): int
    {
        return (int) Cache::get($this->usageKey($id), 0);
    }

    private function record(string $id, int $chars): void
    {
        Cache::put($this->usageKey($id), $this->usedToday($id) + $chars, now()->endOfDay());
    }

    private function usageKey(string $id): string
    {
        return 'translators.used.'.$id.'.'.now()->toDateString();
    }

    private function ready(string $id): bool
    {
        if (isset($this->built[$id])) {
            return true;
        }
        foreach (array_keys(config('translation.providers.'.$id.'.needs', [])) as $path) {
            if (! config($path)) {
                return false;
            }
        }

        return true;
    }

    private function provider(string $id): ?Provider
    {
        if (! array_key_exists($id, $this->built)) {
            $this->built[$id] = $this->ready($id) ? self::build($id) : null;
        }

        return $this->built[$id];
    }

    private static function build(string $id): ?Provider
    {
        return match ($id) {
            'deepl' => new DeepL(config('services.deepl.key')),
            'google' => new Google(config('services.google_translate.key')),
            'gemini' => new Gemini(config('services.gemini.key'), config('services.gemini.translation_model')),
            'libretranslate' => new LibreTranslate(config('services.libretranslate.url'), config('services.libretranslate.key')),
            'mymemory' => new MyMemory(config('services.mymemory.email')),
            'claude' => new Claude(new Client(apiKey: config('services.anthropic.key')), config('services.anthropic.translation_model')),
            default => null,
        };
    }
}
