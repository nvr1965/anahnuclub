<?php

namespace App\Console\Commands;

use App\Models\FeedChannel;
use App\Models\FeedSource;
use App\Models\User;
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
                    // Said openly on the profile: a club account, not a private person.
                    'about' => $author['name'].' · автор AnahnuClub. '.$author['topic'].': подборка из проверенных источников со ссылками.',
                ]);
                $this->line('Author '.$author['name'].' created.');
            }
            // The "Club author" badge, once the badges are on the site.
            if (class_exists(\App\Support\Badges::class)) {
                \App\Support\Badges::set($user, 'club-author', true);
            }

            $channel = FeedChannel::firstOrCreate(['user_id' => $user->id], [
                'topic' => $author['topic'],
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
