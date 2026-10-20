<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_members_see_the_welcome_and_accept_the_rules_once()
    {
        $member = User::factory()->create(['rules_accepted_at' => null]);

        $this->actingAs($member)->get('/feed')->assertRedirect('/welcome');
        $this->get('/welcome')->assertInertia(fn ($page) => $page->component('welcome-member')->where('again', false)->where('rules.title', 'Правила сообщества'));
        // Pictures and background requests are not held up.
        $this->getJson('/feed')->assertOk();

        $this->post('/welcome', [])->assertSessionHasErrors('accept');
        $this->post('/welcome', ['accept' => true])->assertRedirect('/feed');
        $this->assertNotNull($member->fresh()->rules_accepted_at);
        $this->get('/feed')->assertOk();
        $this->get('/welcome')->assertRedirect('/feed');
    }

    public function test_members_accept_again_only_when_an_admin_asks()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $member = User::factory()->create(['rules_accepted_at' => now()->subDay()]);
        $rules = Page::where('slug', 'rules')->firstOrFail();

        $this->actingAs($admin)->put("/admin/pages/{$rules->slug}", ['slug' => 'rules', 'content' => $rules->content, 'published' => true, 'in_footer' => true, 'sort' => 0]);
        $this->actingAs($member)->get('/feed')->assertOk();

        $this->actingAs($admin)->put("/admin/pages/{$rules->slug}", ['slug' => 'rules', 'content' => $rules->content, 'published' => true, 'in_footer' => true, 'sort' => 0, 'ask_again' => true])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(Setting::get('rules_changed_at'));
        $this->actingAs($member)->get('/feed')->assertRedirect('/welcome');
        $this->get('/welcome')->assertInertia(fn ($page) => $page->where('again', true));
    }
}
