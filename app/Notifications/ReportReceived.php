<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A complaint about a post or a comment, for the team's mailbox, so it is looked at quickly. */
class ReportReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report)
    {
        $this->onQueue('mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->report;
        $mail = (new MailMessage)
            ->subject('Anahnu.Club: complaint about a '.$r->reportable_type.' ('.$r->reason.')')
            ->line(($r->reporter_id ? 'Member #'.$r->reporter_id : 'Guest '.$r->email).' · author #'.($r->author_id ?? '?'))
            ->line('Reason: '.$r->reason.($r->details ? ' · '.$r->details : ''))
            ->line(mb_substr((string) $r->snapshot, 0, 500))
            ->action('Open in the admin', route('admin.reports.index'));

        return $r->email ? $mail->replyTo($r->email) : $mail;
    }
}
