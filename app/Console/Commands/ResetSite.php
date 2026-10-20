<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Empties the site before the real start: members, posts, messages and uploaded files go,
 * while what the administrator set up stays (languages, site pages, settings with the translator
 * keys, and the interface texts, which are files). The old site is then imported anew.
 */
class ResetSite extends Command
{
    protected $signature = 'site:reset {--force : do not ask}';

    protected $description = 'Remove all members and content, keeping the administrator settings';

    private const KEEP = ['settings', 'languages', 'pages'];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remove every member, post, message and uploaded file?')) {
            return self::FAILURE;
        }

        $backup = storage_path('app/reset-kept-'.now()->format('Ymd-His').'.json');
        File::put($backup, collect(self::KEEP)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()])
            ->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("Kept settings also saved to $backup");

        $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), [...self::KEEP, 'migrations']));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $tables)).' RESTART IDENTITY CASCADE');
        } else {
            Schema::withoutForeignKeyConstraints(fn () => array_map(fn ($t) => DB::table($t)->delete(), $tables));
        }
        $this->line('Emptied '.count($tables).' tables');

        foreach (['app/public', 'app/private'] as $dir) {
            foreach (File::directories(storage_path($dir)) as $folder) {
                File::deleteDirectory($folder);
            }
            File::delete(array_filter(File::files(storage_path($dir), true), fn ($file) => $file->getFilename() !== '.gitignore'));
        }
        Cache::flush();

        $this->info('The site is empty; settings, languages and pages kept.');

        return self::SUCCESS;
    }
}
