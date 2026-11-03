<?php

namespace App\Console\Commands;

use App\Support\MailHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Shows the mail settings and what stops letters, and with an address sends a test letter right away. */
class MailCheck extends Command
{
    protected $signature = 'mail:check {email? : send a test letter to this address now}';

    protected $description = 'Check that the site can send letters';

    public function handle(): int
    {
        $this->line('MAIL_MAILER: '.config('mail.default'));
        $this->line('MAIL_HOST: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->line('MAIL_USERNAME: '.config('mail.mailers.smtp.username'));
        $this->line('MAIL_FROM_ADDRESS: '.config('mail.from.address'));
        $this->line('MAIL_PASSWORD: '.(config('mail.mailers.smtp.password') ? 'set' : 'EMPTY'));
        $cron = MailHealth::lastCronRun();
        $this->line('Cron last ran: '.($cron ? now()->setTimestamp($cron)->diffForHumans() : 'never'));
        $this->line('Letters waiting in the queue: '.MailHealth::waiting());
        $this->queueSummary();
        foreach (MailHealth::problems() as $problem) {
            $this->warn($problem);
        }

        if ($email = $this->argument('email')) {
            $error = MailHealth::sendTest($email);
            if ($error !== null) {
                $this->error($error);

                return self::FAILURE;
            }
            $this->info(in_array(config('mail.default'), ['log', 'array'], true) ? 'Written to storage/logs/laravel.log, not sent.' : "Sent to $email.");
        }

        return self::SUCCESS;
    }

    /** What else waits in the queue (translations, link cards) and the last failures, for the deploy log. */
    private function queueSummary(): void
    {
        try {
            if (Schema::hasTable('jobs')) {
                $waiting = DB::table('jobs')->selectRaw('queue, count(*) as n, min(created_at) as oldest')->groupBy('queue')->get();
                foreach ($waiting as $row) {
                    $this->line("Queue {$row->queue}: {$row->n} waiting, oldest ".now()->setTimestamp((int) $row->oldest)->diffForHumans());
                }
                $this->line('Jobs by kind: '.DB::table('jobs')->get(['payload'])
                    ->countBy(fn ($job) => class_basename((string) (json_decode($job->payload, true)['displayName'] ?? '?')))
                    ->map(fn ($n, $kind) => "$kind $n")->implode(', '));
            }
            if (Schema::hasTable('failed_jobs')) {
                $this->line('Failed jobs: '.DB::table('failed_jobs')->count());
                foreach (DB::table('failed_jobs')->latest('failed_at')->limit(3)->get() as $failed) {
                    $this->line('  '.$failed->failed_at.' '.class_basename((string) (json_decode($failed->payload, true)['displayName'] ?? '?')).': '.mb_substr(strtok((string) $failed->exception, "\n"), 0, 200));
                }
            }
        } catch (Throwable $e) {
            $this->warn('Queue not readable: '.$e->getMessage());
        }
    }
}
