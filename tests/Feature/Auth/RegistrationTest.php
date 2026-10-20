<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\NewMember;
use App\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        Notification::fake();
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        // Until the email is confirmed, the club stays closed; the letter goes out in the visitor's language.
        $user = User::where('email', 'test@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class, fn ($n) => $n->queue === 'mail');
        $this->get('/feed')->assertRedirect(route('verification.notice', absolute: false));
        $this->get('/settings/profile')->assertOk();

        // The club team hears about every new member.
        Notification::assertSentOnDemand(NewMember::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'team@anahnu.club' && $n->user->is($user) && $n->queue === 'mail');
    }

    public function test_the_team_letter_can_be_turned_off()
    {
        Notification::fake();
        config(['mail.team_address' => '']);
        $this->post('/register', ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password']);

        Notification::assertNothingSentTo(new AnonymousNotifiable);
        Notification::assertCount(1);
    }

    public function test_admin_sees_why_letters_do_not_arrive_and_can_send_a_test()
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page->where('mail.mailer', 'log')->has('mail.problems', 1));
        $this->actingAs($admin)->postJson('/admin/mail/test')->assertJson(['ok' => false]);
    }

    public function test_the_letter_is_written_in_the_members_language()
    {
        $user = User::factory()->unverified()->create(['locale' => 'ru']);
        $mail = (new VerifyEmail)->toMail($user);

        app()->setLocale($user->preferredLocale());
        $this->assertSame('Подтвердить почту', __($mail->subject));
        $this->assertInstanceOf(ShouldQueue::class, new VerifyEmail);
    }

    public function test_changing_the_email_asks_to_confirm_the_new_one()
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', ['name' => $user->name, 'email' => 'new@example.com']);

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
