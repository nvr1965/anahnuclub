<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESSES = ['/friends/list', '/messages/latest', '/notifications/latest', '/push/key', '/share/groups', '/profile/photos', '/calls/ringing', '/clips/more?before=1000', '/hashtags/suggest?q=a'];

    public function test_data_addresses_are_not_found_when_opened_in_the_browser()
    {
        $member = User::factory()->create();

        foreach (self::ADDRESSES as $url) {
            $this->actingAs($member)->get($url, ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertNotFound();
            // An old browser without Sec-Fetch-* headers, asking for a page.
            $this->actingAs($member)->get($url, ['Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])->assertNotFound();
        }
    }

    public function test_the_sites_own_pages_still_get_the_data()
    {
        $member = User::factory()->create();

        foreach (self::ADDRESSES as $url) {
            $this->actingAs($member)->get($url, ['Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'empty', 'Accept' => 'application/json'])->assertOk();
            $this->actingAs($member)->get($url, ['Accept' => '*/*'])->assertOk();
        }
    }

    public function test_admins_can_open_them_directly_to_look_into_a_problem()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (self::ADDRESSES as $url) {
            $this->actingAs($admin)->get($url, ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertOk();
        }
    }

    public function test_ordinary_pages_still_open_by_a_direct_link()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/friends', ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertOk();
        $this->actingAs($member)->get('/messages', ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertOk();
    }
}
