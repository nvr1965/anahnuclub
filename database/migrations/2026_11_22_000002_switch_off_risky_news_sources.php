<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * News sources off for legal reasons: Al Jazeera (under a closure order in Israel), the Iranian state agencies IRNA,
 * Mehr and Fars (republishing praise of terror is on the publisher under Israeli counter-terrorism law), and AP as
 * re-published by feedx.net without a licence. Their waiting news is skipped, and the author of the Iranian agencies
 * (کاوه) is paused. Posts already published stay as they are.
 */
return new class extends Migration
{
    private const URLS = [
        'https://www.aljazeera.net/aljazeerarss/a7c186be-1baa-4bd4-9d80-a84db769f779/73d0e1b4-532f-45ef-b135-bfdff8b8cab9',
        'https://www.aljazeera.com/xml/rss/all.xml',
        'https://www.irna.ir/rss',
        'https://www.mehrnews.com/rss',
        'https://www.farsnews.ir/rss',
        'https://feedx.net/rss/ap.xml',
    ];

    public function up(): void
    {
        $sources = DB::table('feed_sources')->whereIn('url', self::URLS)->pluck('id');
        DB::table('feed_items')->whereIn('feed_source_id', $sources)->whereIn('status', ['new', 'approved'])
            ->update(['status' => 'skipped', 'reason' => 'source off']);
        DB::table('feed_sources')->whereIn('id', $sources)->update(['active' => false]);
        $kaveh = DB::table('users')->where('email', 'kaveh@authors.anahnu.club')->value('id');
        if ($kaveh) {
            DB::table('feed_channels')->where('user_id', $kaveh)->update(['paused' => true]);
        }
    }

    public function down(): void
    {
        // The sources stay off; an admin can switch one back on.
    }
};
