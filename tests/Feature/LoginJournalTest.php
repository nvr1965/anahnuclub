<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class LoginJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sign_in_is_written_with_time_address_and_device()
    {
        $user = User::factory()->create();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/604.1'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $log = LoginLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertTrue($log->success);
        $this->assertSame('password', $log->method);
        $this->assertSame('203.0.113.7', $log->ip);
        $this->assertSame('Mozilla/5.0 (iPhone) Safari/604.1', $log->user_agent);
        $this->assertNotNull($log->created_at);
    }

    public function test_wrong_passwords_are_written_too_also_for_unknown_and_blocked_logins()
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create(['blocked' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => $blocked->email, 'password' => 'password']);

        $this->assertGuest();
        $this->assertSame(3, LoginLog::where('success', false)->count());
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id, 'success' => false]);
        $this->assertDatabaseHas('login_logs', ['user_id' => null, 'login' => 'nobody@example.com']);
        $this->assertDatabaseHas('login_logs', ['user_id' => $blocked->id, 'success' => false]);
    }

    public function test_sign_up_and_google_sign_in_are_marked_as_such()
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $this->assertDatabaseHas('login_logs', ['method' => 'register', 'success' => true]);
        auth()->logout();

        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn((new SocialiteUser)->map(['id' => 'g-1', 'email' => 'anna@example.com', 'name' => 'Anna']));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $this->get('/auth/google/callback');

        $this->assertDatabaseHas('login_logs', ['method' => 'google', 'success' => true, 'user_id' => User::where('email', 'anna@example.com')->value('id')]);
    }

    public function test_coming_back_while_still_signed_in_is_written_as_a_return_visit()
    {
        $user = User::factory()->create();

        $user->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();
        $this->actingAs($user)->get('/settings/logins');
        $this->assertSame(0, LoginLog::count());

        $user->forceFill(['last_seen_at' => now()->subHours(3)])->save();
        $this->actingAs($user)->get('/settings/logins');
        $this->actingAs($user)->get('/settings/logins');

        $log = LoginLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('session', $log->method);
        $this->assertTrue($log->success);
    }

    public function test_the_page_after_signing_in_is_not_also_a_return_visit()
    {
        $user = User::factory()->create(['last_seen_at' => now()->subDays(5)]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->get('/settings/logins');

        $this->assertSame('password', LoginLog::sole()->method);
    }

    public function test_signing_back_in_by_the_remember_me_cookie_is_written()
    {
        $user = User::factory()->create(['last_seen_at' => now()->subDays(5)]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $user->refresh();
        $recaller = $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword();

        // The session is over; only the cookie is left.
        auth()->forgetGuards();
        $this->flushSession();
        $this->withCookie(auth()->guard('web')->getRecallerName(), $recaller)->get('/settings/logins');

        $this->assertSame(['password', 'remember'], LoginLog::orderBy('id')->pluck('method')->all());
    }

    public function test_only_admins_see_the_journal_and_members_see_their_own_history()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        LoginLog::create(['user_id' => $user->id, 'method' => 'password', 'success' => true, 'ip' => '198.51.100.1']);
        LoginLog::create(['user_id' => $other->id, 'method' => 'password', 'success' => true, 'ip' => '198.51.100.2']);

        $this->actingAs($user)->get('/admin/logins')->assertForbidden();
        $this->actingAs($user)->get('/settings/logins')
            ->assertInertia(fn ($page) => $page->component('settings/logins')->has('logs', 1)->where('logs.0.ip', '198.51.100.1'));

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin/logins')->assertInertia(fn ($page) => $page->component('admin/logins')->has('logs.data', 2));
        $this->actingAs($admin)->get('/admin/logins?q=198.51.100.2')->assertInertia(fn ($page) => $page->has('logs.data', 1));
        $this->actingAs($admin)->get('/admin/logins?user='.$user->id)->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('user.id', $user->id));
    }

    public function test_entries_older_than_a_year_are_pruned()
    {
        $old = LoginLog::create(['method' => 'password', 'success' => false]);
        $old->forceFill(['created_at' => now()->subYear()->subDay()])->save();
        LoginLog::create(['method' => 'password', 'success' => false]);

        $this->artisan('model:prune', ['--model' => [LoginLog::class]]);

        $this->assertSame(1, LoginLog::count());
    }
}
