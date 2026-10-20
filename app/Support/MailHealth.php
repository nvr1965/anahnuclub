<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Why letters (the "confirm your email" letter after signing up, password resets) may not arrive:
 * a mailer that only writes to the log, a queue nobody works off because cron is not running,
 * or letters the mail server refused. Shown on the admin overview and by php artisan mail:check.
 */
class MailHealth
{
    /** @return list<string> */
    public static function problems(): array
    {
        $problems = [];
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $problems[] = __('Letters are not sent: MAIL_MAILER=:mailer in .env only writes them to the log. Set MAIL_MAILER=smtp and the password of the mail box, then run php artisan optimize.', ['mailer' => $mailer]);
        }

        if (config('queue.default') === 'database') {
            $oldest = DB::table('jobs')->where('queue', 'mail')->min('created_at');
            if ($oldest && $oldest < now()->subMinutes(5)->getTimestamp()) {
                $problems[] = __('Letters have been waiting in the queue for more than 5 minutes: cron does not run php artisan schedule:run every minute.');
            }
        }

        $failed = DB::table('failed_jobs')->where('queue', 'mail')->where('failed_at', '>=', now()->subDays(7))->latest('failed_at')->first();
        if ($failed) {
            $problems[] = __('A letter could not be sent: :error', ['error' => mb_strimwidth(strtok((string) $failed->exception, "\n"), 0, 300, '…')]);
        }

        return $problems;
    }

    /** Sends a short letter right away, without the queue; returns the mail server's error, or null when it was accepted. */
    public static function sendTest(string $email): ?string
    {
        try {
            Mail::raw(__('This is a test letter from :site. If you are reading it, letters from the site arrive.', ['site' => config('app.name')]), function ($message) use ($email) {
                $message->to($email)->subject(__('Test letter from :site', ['site' => config('app.name')]));
            });

            return null;
        } catch (Throwable $e) {
            return mb_strimwidth($e->getMessage(), 0, 300, '…');
        }
    }
}
