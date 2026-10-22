<?php

namespace App\Jobs;

use App\Models\Language;
use App\Models\Page;
use App\Translation\InterfaceStrings;
use App\Translation\NullTranslator;
use App\Translation\Translator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Machine-translates the interface and the site pages into a newly added language, a slice per run
 * (cron gives the queue under a minute), and switches the language on once the interface is done.
 * When the services refuse (a daily limit reached, a service down) it stops; the hourly schedule
 * in routes/console.php starts it again, so a large interface gets done over a few days if need be.
 */
class TranslateInterface implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $code, public bool $enableWhenDone = false) {}

    /** Marked while a run is queued or working, so the schedule does not start a second one. */
    public static function busyKey(string $code): string
    {
        return 'translate-interface.'.$code;
    }

    /** Why the last run stopped, shown on the admin page until a run gets further. */
    public static function errorKey(string $code): string
    {
        return 'translate-interface.error.'.$code;
    }

    /** Starts again every language whose interface is not fully translated and has no run going. */
    public static function resumeAll(): void
    {
        foreach (Language::all() as $language) {
            if (InterfaceStrings::missing($language->code) !== [] && ! Cache::has(self::busyKey($language->code))) {
                Cache::put(self::busyKey($language->code), true, now()->addMinutes(10));
                // A language still switched off is one waiting for its translation; it switches on when done.
                self::dispatch($language->code, ! $language->enabled);
            }
        }
    }

    public function handle(Translator $translator): void
    {
        $language = Language::find($this->code);
        if (! $language || $translator instanceof NullTranslator || (method_exists($translator, 'available') && ! $translator->available())) {
            Cache::forget(self::busyKey($this->code));

            return;
        }
        Cache::put(self::busyKey($this->code), true, now()->addMinutes(10));

        try {
            $left = InterfaceStrings::fill($this->code, $translator, 35);
        } catch (Throwable $e) {
            Log::warning('Interface translation paused', ['language' => $this->code, 'error' => $e->getMessage()]);
            Cache::put(self::errorKey($this->code), mb_substr($e->getMessage(), 0, 300), now()->addDay());
            Cache::forget(self::busyKey($this->code));

            return;
        }
        Cache::forget(self::errorKey($this->code));
        if ($left > 0) {
            self::dispatch($this->code, $this->enableWhenDone);

            return;
        }

        Page::all()->each(fn (Page $page) => $page->translateMissing($translator, [$this->code]));
        if ($this->enableWhenDone) {
            $language->update(['enabled' => true]);
        }
        Cache::forget(self::busyKey($this->code));
    }
}
