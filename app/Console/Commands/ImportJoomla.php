<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Group;
use App\Models\Like;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Moves users and content from the old Joomla 3 + JomSocial site.
 * Safe to re-run: rows are matched on their old ids.
 */
class ImportJoomla extends Command
{
    protected $signature = 'import:joomla
        {--files= : Path to the old public_html folder, to copy avatars, covers and photos}';

    protected $description = 'Import users, groups, posts, comments and photos from the old Joomla/JomSocial database';

    private Connection $old;

    /** @var array<int, true> */
    private array $userIds = [];

    /** @var array<int, int> old activity id => new post id */
    private array $activityPosts = [];

    /** @var array<int, int> old discussion id => new post id */
    private array $discussionPosts = [];

    /** @var array<int, int> old bulletin id => new post id */
    private array $bulletinPosts = [];

    public function handle(): int
    {
        $this->old = DB::connection('joomla');

        DB::transaction(function () {
            $this->importUsers();
            $this->importGroups();
            $this->importDiscussions();
            $this->importBulletins();
            $this->importActivities();
            $this->importComments();
            $this->importLikes();
            $this->importPhotos();
            $this->importEvents();
            $this->resetSequences();
        });

        if ($files = $this->option('files')) {
            $this->copyFiles(rtrim($files, '/'));
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function importUsers(): void
    {
        $profiles = $this->old->table('community_users')->get()->keyBy('userid');
        $admins = $this->old->table('user_usergroup_map')->where('group_id', 8)->pluck('user_id')->flip();

        $rows = $this->old->table('users')->orderBy('id')->get();
        foreach ($rows as $row) {
            $profile = $profiles->get($row->id);
            $params = json_decode($row->params ?: '{}', true) ?: [];
            $locale = isset($params['language']) ? substr($params['language'], 0, 2) : 'ru';

            DB::table('users')->updateOrInsert(['id' => $row->id], [
                'name' => $row->name,
                'username' => $row->username,
                'email' => Str::lower(trim($row->email)),
                // Joomla stores bcrypt ($2y$) hashes, which Laravel checks natively.
                'password' => $row->password,
                'locale' => in_array($locale, ['ru', 'en', 'he', 'ar'], true) ? $locale : 'ru',
                'avatar_path' => $this->filePath($profile?->avatar),
                'cover_path' => $this->filePath($profile?->cover),
                'status' => $profile?->status ?: null,
                'blocked' => (bool) $row->block,
                'is_admin' => $admins->has($row->id),
                'email_verified_at' => $row->activation === '' || $row->activation === '0' ? $this->date($row->registerDate) : null,
                'last_seen_at' => $this->date($row->lastvisitDate),
                'created_at' => $this->date($row->registerDate) ?? now(),
                'updated_at' => now(),
            ]);
            $this->userIds[$row->id] = true;
        }

        $this->line('Users: '.count($rows));
    }

    private function importGroups(): void
    {
        $rows = $this->old->table('community_groups')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('groups')->updateOrInsert(['id' => $row->id], [
                'owner_id' => $this->user($row->ownerid),
                'name' => $row->name,
                'slug' => $row->id.'-'.(Str::slug($row->name) ?: 'group'),
                'description' => $row->description ?: null,
                'avatar_path' => $this->filePath($row->avatar),
                'cover_path' => $this->filePath($row->cover),
                'is_private' => (bool) $row->approvals,
                'published' => (bool) $row->published,
                'created_at' => $this->date($row->created) ?? now(),
                'updated_at' => now(),
            ]);
        }

        $members = $this->old->table('community_groups_members')->get();
        $owners = $rows->pluck('ownerid', 'id');
        foreach ($members as $m) {
            if (! $this->user($m->memberid) || ! $owners->has($m->groupid)) {
                continue;
            }
            DB::table('group_user')->updateOrInsert(['group_id' => $m->groupid, 'user_id' => $m->memberid], [
                'role' => $owners[$m->groupid] == $m->memberid ? 'owner' : ($m->permissions == 1 ? 'admin' : 'member'),
                'approved' => (bool) $m->approved,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->line('Groups: '.count($rows).', memberships: '.count($members));
    }

    private function importDiscussions(): void
    {
        $rows = $this->old->table('community_groups_discuss')->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->discussionPosts[$row->id] = $this->post('discussion', $row->id, [
                'user_id' => $this->user($row->creator),
                'group_id' => $row->groupid ?: null,
                'type' => 'discussion',
                'title' => $row->title,
                'body' => $row->message,
                'created_at' => $this->date($row->created),
            ]);
        }
        $this->line('Discussions: '.count($rows));
    }

    private function importBulletins(): void
    {
        $rows = $this->old->table('community_groups_bulletins')->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->bulletinPosts[$row->id] = $this->post('bulletin', $row->id, [
                'user_id' => $this->user($row->created_by),
                'group_id' => $row->groupid ?: null,
                'type' => 'announcement',
                'title' => $row->title,
                'body' => $row->message,
                'created_at' => $this->date($row->date),
            ]);
        }
        $this->line('Announcements: '.count($rows));
    }

    /** Status updates and wall posts. Stream entries that only point to discussions or announcements are skipped. */
    private function importActivities(): void
    {
        $rows = $this->old->table('community_activities')
            ->whereIn('app', ['profile', 'groups.wall', 'pages.wall', 'profile.status.share', 'photos', 'events.wall'])
            ->where('title', '<>', '')
            ->orderBy('id')->get();

        foreach ($rows as $row) {
            $this->activityPosts[$row->id] = $this->post('activity', $row->id, [
                'user_id' => $this->user($row->actor),
                'group_id' => $row->groupid ?: null,
                'type' => $row->app === 'photos' ? 'photo' : 'status',
                'body' => $row->title,
                'created_at' => $this->date($row->created),
            ]);
        }
        $this->line('Wall posts: '.count($rows));
    }

    private function importComments(): void
    {
        $rows = $this->old->table('community_wall')->orderBy('id')->get();
        $count = 0;
        foreach ($rows as $row) {
            $postId = match ($row->type) {
                'discussions' => $this->discussionPosts[$row->contentid] ?? null,
                default => $this->activityPosts[$row->contentid] ?? null,
            };
            if (! $postId) {
                continue;
            }
            Comment::updateOrCreate(['legacy_source' => 'wall', 'legacy_id' => $row->id], [
                'post_id' => $postId,
                'user_id' => $this->user($row->post_by),
                'body' => $row->comment,
                'created_at' => $this->date($row->date),
                'updated_at' => $this->date($row->date),
            ]);
            $count++;
        }
        $this->line("Comments: $count");
    }

    private function importLikes(): void
    {
        $count = 0;
        foreach ($this->old->table('community_likes')->get() as $row) {
            $postId = match ($row->element) {
                'discussion', 'groups.discussion' => $this->discussionPosts[$row->uid] ?? null,
                'groups.bulletin' => $this->bulletinPosts[$row->uid] ?? null,
                default => $this->activityPosts[$row->uid] ?? null,
            };
            if (! $postId) {
                continue;
            }
            foreach (array_filter(explode(',', (string) $row->like)) as $userId) {
                if ($this->user((int) $userId)) {
                    Like::firstOrCreate(['user_id' => (int) $userId, 'likeable_type' => 'post', 'likeable_id' => $postId]);
                    $count++;
                }
            }
        }
        $this->line("Likes: $count");
    }

    private function importPhotos(): void
    {
        $albums = $this->old->table('community_photos_albums')->orderBy('id')->get();
        foreach ($albums as $row) {
            DB::table('albums')->updateOrInsert(['id' => $row->id], [
                'user_id' => $this->user($row->creator),
                'group_id' => $row->groupid ?: null,
                'name' => $row->name ?: 'Album',
                'description' => $row->description ?: null,
                'created_at' => $this->date($row->created) ?? now(),
                'updated_at' => now(),
            ]);
        }

        $photos = $this->old->table('community_photos')->orderBy('id')->get();
        foreach ($photos as $row) {
            DB::table('photos')->updateOrInsert(['id' => $row->id], [
                'album_id' => $albums->contains('id', $row->albumid) ? $row->albumid : null,
                'user_id' => $this->user($row->creator),
                'path' => $this->filePath($row->original ?: $row->image),
                'thumb_path' => $this->filePath($row->thumbnail),
                'caption' => Str::limit((string) $row->caption, 250) ?: null,
                'created_at' => $this->date($row->created) ?? now(),
                'updated_at' => now(),
            ]);
        }
        $this->line('Albums: '.count($albums).', photos: '.count($photos));
    }

    private function importEvents(): void
    {
        $rows = $this->old->table('community_events')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('events')->updateOrInsert(['id' => $row->id], [
                'user_id' => $this->user($row->creator),
                'group_id' => $row->type === 'group' && $row->contentid ? $row->contentid : null,
                'title' => $row->title,
                'description' => $row->description ?: null,
                'location' => $row->location ?: null,
                'starts_at' => $this->date($row->startdate),
                'ends_at' => $this->date($row->enddate),
                'created_at' => $this->date($row->created) ?? now(),
                'updated_at' => now(),
            ]);
        }
        $this->line('Events: '.count($rows));
    }

    private function post(string $source, int $legacyId, array $attributes): int
    {
        $attributes['created_at'] ??= now();
        $attributes['updated_at'] = $attributes['created_at'];

        return Post::updateOrCreate(['legacy_source' => $source, 'legacy_id' => $legacyId], $attributes)->id;
    }

    /** Tables imported with their old ids need their Postgres sequences moved past them. */
    private function resetSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (['users', 'groups', 'albums', 'photos', 'events'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('$table', 'id'), GREATEST((SELECT MAX(id) FROM $table), 1))");
        }
    }

    private function copyFiles(string $root): void
    {
        $source = $root.'/images';
        if (! is_dir($source)) {
            $this->warn("No images folder at $source");

            return;
        }
        File::copyDirectory($source, storage_path('app/public/joomla/images'));
        $this->line('Copied images from '.$source);
    }

    private function user(int|string|null $id): ?int
    {
        return $id && isset($this->userIds[(int) $id]) ? (int) $id : null;
    }

    private function filePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || str_contains($path, '://')) {
            return null;
        }

        return 'joomla/'.ltrim($path, '/');
    }

    private function date(?string $value): ?string
    {
        return $value && ! str_starts_with($value, '0000') ? $value : null;
    }
}
