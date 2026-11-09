<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notify;

/**
 * The letter to the club's inbox (config feeds.inbox, club@anahnu.club) when a member asks a club author
 * to be friends or invites it into a group: nobody signs in as the author, so its curator answers
 * on the admin page "Feed filling". Sent from the "mail" queue.
 */
class ClubAuthorInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $author, public User $from, public ?Group $group = null)
    {
        $this->onQueue('mail');
    }

    public static function send(User $author, User $from, ?Group $group = null): void
    {
        $inbox = (string) config('feeds.inbox');
        if ($inbox !== '') {
            Notify::route('mail', $inbox)->notify((new self($author, $from, $group))->locale(config('app.locale')));
        }
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $names = ['author' => $this->author->name, 'name' => $this->from->name];

        return (new MailMessage)
            ->subject($this->group
                ? __(':author: :name invites to the group “:group”', $names + ['group' => $this->group->localized('name')])
                : __(':author: friend request from :name', $names))
            ->line($this->group
                ? __(':name invited the club author :author into the group “:group”.', $names + ['group' => $this->group->localized('name')])
                : __(':name asked the club author :author to be friends.', $names))
            ->line(__('Accept or decline it on the admin page "Feed filling", the Authors tab.'))
            ->action(__('Open the requests'), route('admin.feeds.index', ['tab' => 'authors']))
            ->line(__('Who sent it: :url', ['url' => route('people.show', $this->from)]));
    }
}
