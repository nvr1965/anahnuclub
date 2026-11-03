<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;

/** Lists, and with --force deletes, groups older than the given age that never got a post, album, video or event. */
class PruneStaleGroups extends Command
{
    protected $signature = 'groups:prune-stale {--days=365 : How old a group must be} {--force : Delete them; without it the groups are only listed}';

    protected $description = 'List or delete old groups without any posts';

    public function handle(): int
    {
        $groups = Group::query()->stale((int) $this->option('days'))->withCount('members')->orderBy('created_at')->get();

        $this->table(['id', 'name', 'created', 'members'], $groups->map(fn (Group $g) => [
            $g->id, $g->name, $g->created_at?->toDateString(), $g->members_count,
        ]));

        if (! $this->option('force')) {
            $this->info('Found '.$groups->count().' groups. Nothing deleted; run with --force to delete them.');

            return self::SUCCESS;
        }

        $groups->each(fn (Group $g) => $g->purge());
        $this->info('Deleted '.$groups->count().' groups.');

        return self::SUCCESS;
    }
}
