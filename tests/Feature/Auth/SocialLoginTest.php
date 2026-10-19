<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret', 'services.facebook.client_id' => null]);
    }

    private function remote(string $id, ?string $email, string $name = 'Анна Коган'): void
    {
        $user = (new SocialiteUser)->map(['id' => $id, 'email' => $email, 'name' => $name]);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_only_services_with_keys_show_and_work()
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('social', ['google']));
        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/auth/github/redirect')->assertNotFound();
    }

    public function test_a_new_visitor_gets_a_confirmed_account()
    {
        $this->remote('g-1', 'Anna@Example.com');

        $this->get('/auth/google/callback')->assertRedirect('/feed');

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Анна Коган', $user->name);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        // The next time, the same Google account opens the same member.
        auth()->logout();
        $this->remote('g-1', 'changed@example.com');
        $this->get('/auth/google/callback');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_an_existing_member_with_the_same_email_is_joined_not_duplicated()
    {
        $member = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $this->remote('g-2', 'old@example.com');

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($member);
        $this->assertTrue($member->fresh()->hasVerifiedEmail());
        $this->assertSame(1, User::count());
    }

    public function test_no_email_or_a_blocked_member_is_turned_away()
    {
        $this->remote('g-3', null);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        User::factory()->create(['email' => 'bad@example.com', 'blocked' => true]);
        $this->remote('g-4', 'bad@example.com');
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }
}
