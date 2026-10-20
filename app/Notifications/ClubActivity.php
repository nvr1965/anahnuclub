<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Something a member did that concerns another member: a like, a comment, a friend request, an RSVP.
 * Stored in the database and shown under the bell; the page writes the sentence in the reader's language.
 */
class ClubActivity extends Notification
{
    public const TYPES = [
        'post_liked', 'post_commented', 'comment_replied', 'comment_also', 'photo_liked', 'video_liked',
        'friend_request', 'friend_accepted', 'event_going', 'event_interested',
        'group_offer_admin', 'group_offer_moderator', 'group_offer_owner', 'group_offer_accepted', 'group_invite',
    ];

    public function __construct(
        public string $kind,
        public User $actor,
        public string $url,
        public string $key,
        public ?string $excerpt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'key' => $this->key,
            'url' => $this->url,
            'excerpt' => $this->excerpt,
            'actor' => ['id' => $this->actor->id, 'name' => $this->actor->name, 'avatar' => $this->actor->avatar],
        ];
    }

    /**
     * Tells $to, unless they did it themselves. An earlier unread notice of the same thing by the
     * same person is replaced, so liking, unliking and liking again leaves one.
     */
    public static function send(?User $to, string $kind, User $actor, string $url, string $subject, ?string $excerpt = null): void
    {
        if (! $to || $to->is($actor) || $to->blocked || $to->isBlockedWith($actor)) {
            return;
        }
        $key = "$kind:$subject:{$actor->id}";
        // Kept as a path, so the link works whatever address the site is opened at.
        $parts = parse_url($url);
        $url = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $to->unreadNotifications()->where('data->key', $key)->delete();
        $to->notify(new self($kind, $actor, $url, $key, $excerpt === null ? null : mb_strimwidth(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $excerpt))))), 0, 90, '…')));
    }

    /** Takes back a notice when its reason is undone, e.g. an unlike. */
    public static function retract(?User $to, string $kind, User $actor, string $subject): void
    {
        $to?->unreadNotifications()->where('data->key', "$kind:$subject:{$actor->id}")->delete();
    }
}
