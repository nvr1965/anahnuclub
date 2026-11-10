<?php

namespace App\Console\Commands;

use App\Models\FeedChannel;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Support\Badges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates the club's authors (Farik, علي, Dietrich…), their sources and their groups (one language each) from database/seeders/feeds.php.
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
        'uz' => ':name · AnahnuClub muallifi. :topic: ishonchli manbalardan saralangan xabarlar, havolalar bilan.',
        'kk' => ':name · AnahnuClub авторы. :topic: тексерілген дереккөздерден іріктеме, сілтемелерімен.',
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
            // Renamed in the list since: the name in the author's own language, and its language and topic with it.
            if (isset($author['was']) && in_array($old = $user->name, (array) $author['was'], true)) {
                $user->forceFill([
                    'name' => $author['name'],
                    'locale' => $author['locale'],
                    'about' => strtr(self::ABOUT[$author['locale']] ?? self::ABOUT['en'], [':name' => $author['name'], ':topic' => $author['topic']]),
                ])->save();
                $user->feedChannel()->update(['topic' => $author['topic']]);
                $this->line('Author '.$old.' is now '.$author['name'].'.');
            }
            // The "Club author" badge beside the name: a club account, not a private person.
            Badges::set($user, 'club-author', true);
            $this->pictures($user, $author['slug']);

            $channel = FeedChannel::firstOrCreate(['user_id' => $user->id], [
                'topic' => $author['topic'],
                'daily_min' => $author['min'] ?? null,
                'daily_limit' => $author['limit'],
            ]);
            // Moved to another topic group since, unless the admin has put it elsewhere.
            if (isset($author['was_group']) && $channel->group_id && Group::whereKey($channel->group_id)->value('slug') === $author['was_group']) {
                $channel->group_id = null;
            }
            if (! $channel->group_id && isset($author['group'], $groups[$author['group']])) {
                $group = $this->topicGroup($author['group'], $groups[$author['group']], $user);
                $channel->update(['group_id' => $group?->id]);
            }
            $group = $channel->group_id ? Group::find($channel->group_id) : null;
            if ($group && ! $group->members()->whereKey($user->id)->exists()) {
                $group->members()->attach($user->id, ['role' => 'admin', 'approved' => true]);
            }
            foreach ($author['sources'] as $source) {
                [$name, $url, $locale] = $source;
                FeedSource::firstOrCreate(['feed_channel_id' => $channel->id, 'url' => $url], ['name' => $name, 'locale' => $locale] + ($source[3] ?? []));
            }
            // Sources in another language than the author's (or given to another author): off, with their waiting news.
            if (! empty($author['dropped'])) {
                $dropped = FeedSource::where('feed_channel_id', $channel->id)->whereIn('url', $author['dropped']);
                FeedItem::whereIn('feed_source_id', (clone $dropped)->select('id'))->whereIn('status', ['new', 'approved'])
                    ->update(['status' => 'skipped', 'reason' => 'source off']);
                $dropped->update(['active' => false]);
            }
        }
        $this->tidyGroups($groups);
        // Addresses given earlier that never worked: off, so they do not fill the admin page with errors.
        FeedSource::whereIn('url', $retired)->update(['active' => false]);
        $this->info(FeedChannel::count().' authors, '.FeedSource::count().' sources.');

        return self::SUCCESS;
    }

    /**
     * The topic group "Новости России", "חדשות ישראל"…: made once, by its first author, open to everyone.
     * A group of a member that happens to have the same address is left alone; the author then posts in the feed.
     * A club group that still has its earlier name ('was') gets the new name, description and language.
     */
    private function topicGroup(string $slug, array $group, User $author): ?Group
    {
        $existing = Group::firstWhere('slug', $slug);
        if ($existing) {
            return FeedChannel::where('group_id', $existing->id)->exists() || isset($group['was']) && $existing->name === $group['was'] ? $existing : null;
        }
        $created = Group::create([
            'owner_id' => $author->id,
            'name' => $group['name'],
            'slug' => $slug,
            'description' => $group['description'],
            'locale' => $group['locale'],
        ]);
        $created->members()->attach($author->id, ['role' => 'owner', 'approved' => true]);
        $this->line('Group '.$group['name'].' created.');

        return $created;
    }

    /**
     * The club's groups after the authors are in place: one language each (renamed from an earlier name,
     * the language set), owned by an author that writes there, with no club author that no longer writes
     * there, with a picture; and the authors' earlier posts in a family moved to the group of their language.
     *
     * @param  array<string, array<string, string>>  $config
     */
    private function tidyGroups(array $config): void
    {
        $authors = FeedChannel::query()->whereNotNull('group_id')->get(['user_id', 'group_id']);
        $clubAuthorIds = FeedChannel::query()->pluck('user_id');
        $families = [];
        foreach ($config as $slug => $settings) {
            $group = Group::firstWhere('slug', $slug);
            $writers = $group ? $authors->where('group_id', $group->id)->pluck('user_id') : collect();
            if (! $group || $writers->isEmpty()) {
                continue;
            }
            if (isset($settings['was']) && $group->name === $settings['was']) {
                $group->fill(['name' => $settings['name'], 'description' => $settings['description']]);
                $this->line('Group '.$settings['was'].' is now '.$settings['name'].'.');
            }
            $group->locale = $settings['locale'];
            // Club authors that write elsewhere now leave; the group's owner is one that writes here.
            $leaving = $group->members()->whereIn('users.id', $clubAuthorIds)->whereNotIn('users.id', $writers)->pluck('users.id');
            $group->members()->detach($leaving);
            if ($clubAuthorIds->contains($group->owner_id) && ! $writers->contains($group->owner_id)) {
                $group->owner_id = $writers->first();
            }
            $group->members()->syncWithoutDetaching([$group->owner_id => ['role' => 'owner', 'approved' => true]]);
            if (! $group->avatar_path && isset($settings['avatar']) && is_file($picture = resource_path('club-groups/'.$settings['avatar'].'.png'))) {
                $path = 'group-avatars/club-'.$slug.'.png';
                Storage::disk('public')->put($path, (string) file_get_contents($picture));
                $group->avatar_path = $path;
            }
            $group->save();
            if (isset($settings['family'])) {
                $families[$settings['family']][$settings['locale']] = $group->id;
            }
        }
        Group::forgetRoles();

        foreach ($families as $byLocale) {
            foreach ($byLocale as $locale => $groupId) {
                Post::whereIn('group_id', array_values($byLocale))->where('group_id', '!=', $groupId)
                    ->whereIn('user_id', $clubAuthorIds)->where('locale', $locale)
                    ->update(['group_id' => $groupId]);
            }
        }
    }

    /**
     * The author's drawn picture and profile cover (resources/club-authors/<slug>.png, covers/<slug>.jpg),
     * in the topic and language of its group; only where there is none yet, so a picture an admin set stays.
     */
    private function pictures(User $user, string $slug): void
    {
        foreach (['avatar_path' => ['avatars', $slug.'.png'], 'cover_path' => ['covers', 'covers/'.$slug.'.jpg']] as $column => [$folder, $file]) {
            if ($user->{$column} || ! is_file($picture = resource_path('club-authors/'.$file))) {
                continue;
            }
            $path = $folder.'/club-'.$slug.'.'.pathinfo($file, PATHINFO_EXTENSION);
            Storage::disk('public')->put($path, (string) file_get_contents($picture));
            $user->forceFill([$column => $path])->save();
        }
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
