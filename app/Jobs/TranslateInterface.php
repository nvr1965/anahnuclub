<?php

namespace App\Jobs;

use App\Models\Language;
use App\Models\Page;
use App\Translation\InterfaceStrings;
use App\Translation\Translator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Machine-translates the interface and the site pages into a newly added language, a slice per run
 * (cron gives the queue under a minute), and switches the language on once the interface is done.
 */
class TranslateInterface implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $code, public bool $enableWhenDone = false) {}

    public function handle(Translator $translator): void
    {
        $language = Language::find($this->code);
        if (! $language) {
            return;
        }

        if (InterfaceStrings::fill($this->code, $translator, 35) > 0) {
            self::dispatch($this->code, $this->enableWhenDone);

            return;
        }

        Page::all()->each(fn (Page $page) => $page->translateMissing($translator, [$this->code]));
        if ($this->enableWhenDone) {
            $language->update(['enabled' => true]);
        }
    }
}
