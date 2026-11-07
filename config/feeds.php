<?php

/*
 * The daily filling of the feed by the club's authors. The starting authors and their sources
 * are in database/seeders/feeds.php; everything is changed later on the admin page "Feed filling".
 */
return [
    // Posts go out in the club's daytime only.
    'timezone' => env('FEEDS_TIMEZONE', 'Asia/Jerusalem'),
    'window' => [7, 23],
    // Minutes between any two authors' posts (a different pause each time, between the two numbers),
    // and the least between two posts of one author.
    'site_gap' => [10, 25],
    'author_gap' => 90,
    // When members wrote more posts than this in a day, the authors post half as much.
    'busy_members' => 30,
    // News not posted within this many hours leaves the queue (what a curator added by hand stays).
    'stale_hours' => 12,
    // Where the letters about friend requests and group invitations to club authors go.
    'inbox' => env('FEEDS_INBOX', 'club@anahnu.club'),
    // Sources read per run of feeds:fetch (every 20 minutes), the longest unread first.
    'sources_per_run' => 10,
];
