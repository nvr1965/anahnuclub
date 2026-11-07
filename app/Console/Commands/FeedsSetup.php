<?php

namespace App\Console\Commands;

use App\Models\FeedChannel;
use App\Models\FeedSource;
use App\Models\Group;
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
        $config = require database_path('seeders/feeds.php');
        $retired = $config['retired'] ?? [];
        $groups = $config['groups'] ?? [];
        unset($config['retired'], $config['groups']);
        foreach ($config as $author) {
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
            if (! $channel->group_id && isset($author['group'], $groups[$author['group']])) {
                $group = $this->topicGroup($author['group'], $groups[$author['group']], $user);
                $channel->update(['group_id' => $group?->id]);
            }
            $group = $channel->group_id ? Group::find($channel->group_id) : null;
            if ($group && ! $group->members()->whereKey($user->id)->exists()) {
                $group->members()->attach($user->id, ['role' => 'admin', 'approved' => true]);
            }
            foreach ($author['sources'] as [$name, $url, $locale]) {
                FeedSource::firstOrCreate(['feed_channel_id' => $channel->id, 'url' => $url], ['name' => $name, 'locale' => $locale]);
            }
        }
        // Addresses given earlier that never worked: off, so they do not fill the admin page with errors.
        FeedSource::whereIn('url', $retired)->update(['active' => false]);
        $this->info(FeedChannel::count().' authors, '.FeedSource::count().' sources.');

        return self::SUCCESS;
    }

    /**
     * The topic group "Новости России", "Интересно"…: made once, by its first author, open to everyone.
     * A group of a member that happens to have the same address is left alone; the author then posts in the feed.
     */
    private function topicGroup(string $slug, array $group, User $author): ?Group
    {
        $existing = Group::firstWhere('slug', $slug);
        if ($existing) {
            return FeedChannel::where('group_id', $existing->id)->exists() ? $existing : null;
        }
        [$name, $description] = $group;
        $created = Group::create([
            'owner_id' => $author->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'locale' => 'ru',
        ]);
        $created->members()->attach($author->id, ['role' => 'owner', 'approved' => true]);
        $this->line('Group '.$name.' created.');

        return $created;
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
