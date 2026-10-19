<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The "confirm your email" letter, sent from the queue: a slow or failing mail server
 * must not break signing up. It goes out in the member's language (User::preferredLocale).
 */
class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;
}
