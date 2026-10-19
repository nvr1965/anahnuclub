<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_opens_in_the_browser_language()
    {
        $this->withHeader('Accept-Language', 'he-IL,he;q=0.9,en;q=0.5')->get('/')
            ->assertInertia(fn ($page) => $page->where('locale', 'he'));
    }

    public function test_members_from_the_old_site_get_the_browser_language_until_they_pick_one()
    {
        $member = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($member)->withHeader('Accept-Language', 'ar')->get('/feed')
            ->assertInertia(fn ($page) => $page->where('locale', 'ar'));

        $this->post('/locale', ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', $member->fresh()->ui_locale);

        // The choice is remembered on the next visit too, whatever the browser says.
        $this->flushSession();
        $this->actingAs($member->fresh())->withHeader('Accept-Language', 'ar')->get('/feed')
            ->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }
}
