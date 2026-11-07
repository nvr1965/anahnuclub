<?php

namespace App\Console\Commands;

use App\Models\FeedChannel;
use App\Models\FeedSource;
use App\Models\User;
use App\Support\Badges;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates the club's authors (Фарик, Али, Витрих…) and their sources from database/seeders/feeds.php.
 * Run again at any time: it only adds what is missing, so an admin's changes stay.
 */
class FeedsSetup extends Command
{
    protected $signature = 'feeds:setup';

    protected $description = 'Create the club authors and their news sources for the daily feed';

    /** The line on an author's profile, in the language it posts in. */
    private const ABOUT = [
        'ru' => ':name · автор AnahnuClub. :topic: подборка из проверенных источников со ссылками.',
        'uk' => ':name · автор AnahnuClub. :topic: добірка з перевірених джерел із посиланнями.',
        'he' => ':name · כותב/ת ב-AnahnuClub. :topic: מבחר ממקורות בדוקים, עם קישורים.',
        'ar' => ':name · كاتب في AnahnuClub. :topic: مختارات من مصادر موثوقة مع الروابط.',
        'en' => ':name · AnahnuClub author. :topic: picks from checked sources, with links.',
        'de' => ':name · Autor bei AnahnuClub. :topic: Auswahl aus geprüften Quellen, mit Links.',
        'fr' => ':name · auteur d’AnahnuClub. :topic : une sélection de sources vérifiées, avec les liens.',
    ];

    public function handle(): int
    {
        foreach (require database_path('seeders/feeds.php') as $author) {
            $user = User::firstWhere('email', $author['slug'].'@authors.anahnu.club');
            if (! $user) {
                $user = User::forceCreate([
                    'name' => $author['name'],
                    'username' => $this->freeUsername($author['slug']),
                    'email' => $author['slug'].'@authors.anahnu.club',
                    'password' => Str::random(40),
                    'locale' => $author['locale'],
                    'email_verified_at' => now(),
                    'rules_accepted_at' => now(),
                    // Said openly on the profile, in the author's language: a club account, not a private person.
                    'about' => strtr(self::ABOUT[$author['locale']] ?? self::ABOUT['en'], [':name' => $author['name'], ':topic' => $author['topic']]),
                ]);
                $this->line('Author '.$author['name'].' created.');
            }
            // The "Club author" badge beside the name: a club account, not a private person.
            Badges::set($user, 'club-author', true);

            $channel = FeedChannel::firstOrCreate(['user_id' => $user->id], [
                'topic' => $author['topic'],
                'daily_min' => $author['min'] ?? null,
                'daily_limit' => $author['limit'],
            ]);
            foreach ($author['sources'] as [$name, $url, $locale]) {
                FeedSource::firstOrCreate(['feed_channel_id' => $channel->id, 'url' => $url], ['name' => $name, 'locale' => $locale]);
            }
        }
        $this->info(FeedChannel::count().' authors, '.FeedSource::count().' sources.');

        return self::SUCCESS;
    }

    private function freeUsername(string $slug): string
    {
        $name = $slug;
        for ($i = 2; User::where('username', $name)->exists(); $i++) {
            $name = $slug.$i;
        }

        return $name;
    }
}
