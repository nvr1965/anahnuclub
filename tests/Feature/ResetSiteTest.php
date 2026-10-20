<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_and_content_go_and_the_administrator_settings_stay()
    {
        $user = User::factory()->create();
        Post::create(['user_id' => $user->id, 'type' => 'status', 'body' => 'test post']);
        Setting::updateOrCreate(['key' => 'translator_keys'], ['value' => 'secret']);
        DB::table('languages')->updateOrInsert(['code' => 'xx'], ['name' => 'Test', 'native_name' => 'Test']);
        $pages = DB::table('pages')->count();

        $this->artisan('site:reset', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Post::count());
        $this->assertSame('secret', DB::table('settings')->where('key', 'translator_keys')->value('value'));
        $this->assertTrue(DB::table('languages')->where('code', 'xx')->exists());
        $this->assertSame($pages, DB::table('pages')->count());
        array_map('unlink', glob(storage_path('app/reset-kept-*.json')));
    }
}
