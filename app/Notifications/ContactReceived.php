<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A copy of a contact form message for the team's mailbox; "Reply" goes straight to the sender. */
class ContactReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $message)
    {
        $this->onQueue('mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->message;

        return (new MailMessage)
            ->subject('Anahnu.Club: '.($m->subject ?: 'message from '.$m->name))
            ->replyTo($m->email, $m->name)
            ->greeting($m->name.' <'.$m->email.'>')
            ->line(($m->user_id ? 'Member #'.$m->user_id.' · ' : 'Guest · ').'language: '.($m->locale ?? '?'))
            ->line($m->body)
            ->action('Open in the admin', route('admin.contact.index'));
    }
}
