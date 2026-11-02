<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL, unlike MySQL, makes no index for a foreign key: every "posts of this member",
 * "comments of these posts", "photos of this album" read the whole table. These indexes cover the
 * columns the feed, profiles, groups, the messenger and the badges in the top bar look things up by.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> table => indexes, each a list of columns */
    private const INDEXES = [
        // The feed: newest first, a member's posts, posts with a video or an event.
        'posts' => [['created_at'], ['user_id', 'created_at'], ['video_id'], ['event_id']],
        // Comments under each post on the page, oldest first; a member's comments; replies.
        'comments' => [['post_id', 'created_at'], ['user_id'], ['parent_id']],
        'photos' => [['album_id'], ['post_id'], ['created_at']],
        'albums' => [['user_id'], ['group_id']],
        'videos' => [['user_id'], ['group_id']],
        'events' => [['user_id'], ['group_id'], ['starts_at']],
        // Friends are kept one way (user_id, friend_id): the other way needs its own index.
        'friendships' => [['friend_id']],
        'group_user' => [['user_id']],
        'groups' => [['owner_id']],
        'event_user' => [['user_id']],
        'post_audience' => [['user_id']],
        'group_blocks' => [['group_id']],
        'message_blocks' => [['blocked_id']],
        'messages' => [['user_id']],
        'message_attachments' => [['message_id']],
        'calls' => [['conversation_id']],
        'call_signals' => [['call_id']],
        // Members on the site now, and "most recently here first" lists.
        'users' => [['last_seen_at']],
        'group_role_offers' => [['user_id']],
        'group_invitations' => [['user_id']],
        'friend_lists' => [['user_id']],
        'friend_list_user' => [['user_id']],
        'friend_suggestion_dismissals' => [['dismissed_id']],
        'social_accounts' => [['user_id']],
        // The bell's unread count.
        'notifications' => [['notifiable_type', 'notifiable_id', 'read_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $columns) {
                if (! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $this->name($table, $columns))) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->name($table, $columns)));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $this->name($table, $columns))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $columns)));
                }
            }
        }
    }

    /** @param  list<string>  $columns */
    private function name(string $table, array $columns): string
    {
        return 'perf_'.$table.'_'.implode('_', $columns).'_index';
    }
};
