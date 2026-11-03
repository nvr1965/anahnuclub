<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_in_the_letter_works_for_a_week()
    {
        $user = User::factory()->unverified()->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->travel(6)->days();

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_link_leads_to_the_page_that_sends_a_new_one()
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-expired');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_opened_where_another_member_is_signed_in_still_confirms_the_address()
    {
        $newcomer = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $url = (new VerifyEmail)->toMail($newcomer)->actionUrl;

        $this->actingAs($other)->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/email-verified')->where('email', $newcomer->email));
        $this->assertTrue($newcomer->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_opened_signed_out_confirms_the_address_and_asks_to_sign_in()
    {
        $user = User::factory()->unverified()->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_a_link_for_an_address_changed_since_confirms_nothing()
    {
        $user = User::factory()->unverified()->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $user->forceFill(['email' => 'changed@example.com'])->save();

        $this->actingAs($user)->get($url)->assertRedirect(route('verification.notice'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
