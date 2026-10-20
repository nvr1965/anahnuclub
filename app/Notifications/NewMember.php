<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notify;

/** The letter to the club team (MAIL_TEAM_ADDRESS) about every new member, sent from the "mail" queue. */
class NewMember extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public string $via)
    {
        $this->onQueue('mail');
    }

    /** Tells the team about a member who has just signed up; $via is "email" or the social network's name. */
    public static function send(User $user, string $via): void
    {
        if ($address = config('mail.team_address')) {
            Notify::route('mail', $address)->notify((new self($user, $via))->locale(config('app.locale')));
        }
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New member: :name', ['name' => $this->user->name]))
            ->line(__('A new member has signed up on :site.', ['site' => config('app.name')]))
            ->line(__('Name: :name', ['name' => $this->user->name]))
            ->line(__('Email: :email', ['email' => $this->user->email]))
            ->line(__('Signed up with: :via', ['via' => $this->via === 'email' ? __('email and password') : $this->via]))
            ->line(__('Language: :locale', ['locale' => $this->user->locale]))
            ->action(__('Open the profile'), route('people.show', $this->user));
    }
}
