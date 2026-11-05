<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Translation\TranslationTargets;
use App\Translation\TranslatorChain;
use Illuminate\Console\Command;
use Throwable;

/** For the deploy log: which languages new posts go into, and how much of each service's daily limit is used. */
class TranslationStatus extends Command
{
    protected $signature = 'translations:status';

    protected $description = 'Show the languages translated ahead of time and the translators\' use today';

    public function handle(): int
    {
        try {
            $enabled = Language::enabled()->pluck('code');
            $eager = TranslationTargets::eager();
            $this->line('Languages on: '.$enabled->count().' ('.$enabled->implode(', ').')');
            $this->line('New posts translated at once into: '.$eager->implode(', '));
            $this->line('Translated when opened: '.($enabled->diff($eager)->implode(', ') ?: 'none'));
            foreach (TranslatorChain::fromSettings()->describe() as $t) {
                $state = ! $t['on'] ? 'off' : (! $t['ready'] ? 'no key' : 'on');
                $limit = $t['daily_chars'] > 0 ? $t['daily_chars'] : 'no limit';
                $this->line("  {$t['id']}: {$state}, today {$t['used_today']} of {$limit}");
            }
        } catch (Throwable $e) {
            $this->warn('Translation status not readable: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
