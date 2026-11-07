<?php

/*
 * The daily filling of the feed by the club's authors. The starting authors and their sources
 * are in database/seeders/feeds.php; everything is changed later on the admin page "Feed filling".
 */
return [
    // Posts go out in the club's daytime only.
    'timezone' => env('FEEDS_TIMEZONE', 'Asia/Jerusalem'),
    'window' => [7, 23],
    // Minutes between any two authors' posts, and between two posts of one author.
    'site_gap' => 10,
    'author_gap' => 90,
    // When members wrote more posts than this in a day, the authors post half as much.
    'busy_members' => 30,
    // Approved or waiting news older than this is dropped from the queue (jokes and curators' items stay).
    'stale_hours' => 36,
    // Where the letters about friend requests and group invitations to club authors go.
    'inbox' => env('FEEDS_INBOX', 'club@anahnu.club'),
    // Sources read per run of feeds:fetch (every 20 minutes), the longest unread first.
    'sources_per_run' => 10,
];
