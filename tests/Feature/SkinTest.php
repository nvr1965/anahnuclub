<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Skins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkinTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_starts_with_the_club_style_and_every_style_is_offered()
    {
        $this->get('/')
            ->assertSee('data-skin="club"', false)
            ->assertInertia(fn ($page) => $page->where('skin', 'club')->where('skins', Skins::ALL));
    }

    public function test_a_member_keeps_the_chosen_style_on_every_device()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post('/skin', ['skin' => 'stream'])->assertRedirect()->assertCookie(Skins::COOKIE, 'stream');
        $this->assertSame('stream', $member->fresh()->ui_skin);

        // Another browser, no cookie: the style comes from the account.
        $this->actingAs($member->fresh())->get('/feed')
            ->assertSee('data-skin="stream"', false)
            ->assertInertia(fn ($page) => $page->where('skin', 'stream'));
    }

    public function test_a_guest_keeps_the_style_in_the_browser()
    {
        $this->post('/skin', ['skin' => 'large'])->assertCookie(Skins::COOKIE, 'large');

        $this->withCookie(Skins::COOKIE, 'large')->get('/')->assertSee('data-skin="large"', false);
    }

    public function test_unknown_or_switched_off_styles_are_refused_and_fall_back_to_the_default()
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post('/skin', ['skin' => 'facebook'])->assertSessionHasErrors('skin');

        $member->forceFill(['ui_skin' => 'showcase'])->save();
        Setting::put('skins_enabled', json_encode(['club', 'community']));
        Setting::put('skin_default', 'community');

        $this->actingAs($member->fresh())->post('/skin', ['skin' => 'showcase'])->assertSessionHasErrors('skin');
        $this->actingAs($member->fresh())->get('/feed')->assertInertia(fn ($page) => $page->where('skin', 'community')->where('skins', ['club', 'community']));
    }

    public function test_an_administrator_sets_the_default_and_the_styles_on_offer()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->patch('/admin/skins', ['default' => 'large', 'enabled' => ['stream']])->assertRedirect();

        $this->assertSame('large', Skins::default());
        // The default is always on offer.
        $this->assertSame(['large', 'stream'], Skins::enabled());

        $this->actingAs(User::factory()->create())->patch('/admin/skins', ['default' => 'club', 'enabled' => []])->assertForbidden();
    }

    public function test_the_sign_in_pages_always_show_the_club_style()
    {
        Setting::put('skin_default', 'forum');

        // Everywhere else the site's style stays.
        $this->get('/')->assertSee('data-skin="forum"', false);

        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $this->withCookie(Skins::COOKIE, 'showcase')->get($url)
                ->assertSee('data-skin="club"', false)
                ->assertInertia(fn ($page) => $page->where('skin', 'club'));
        }
    }
}
