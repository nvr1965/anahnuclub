<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The single donation link becomes the first of a list. */
return new class extends Migration
{
    public function up(): void
    {
        $url = DB::table('settings')->where('key', 'donate_url')->value('value');
        if ($url === null) {
            return;
        }
        $label = str_contains($url, 'ko-fi.com') ? 'Ko-fi' : (str_contains($url, 'paypal') ? 'PayPal' : 'Donate');
        DB::table('settings')->updateOrInsert(
            ['key' => 'donate_links'],
            ['value' => json_encode($url === '' ? [] : [['label' => $label, 'url' => $url]], JSON_UNESCAPED_SLASHES), 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('settings')->where('key', 'donate_url')->delete();
    }

    public function down(): void {}
};
