<?php

namespace App\Console\Commands;

use App\Support\MailHealth;
use Illuminate\Console\Command;

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
}
