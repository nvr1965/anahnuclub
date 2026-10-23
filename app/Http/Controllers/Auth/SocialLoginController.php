<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\NewMember;
use App\Support\BannedEmails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Signing in (and up) with Google or Facebook. The first visit makes an account, or joins one that
 * already has the same email; the email counts as confirmed, since the service has checked it.
 */
class SocialLoginController extends Controller
{
    public const PROVIDERS = ['google' => 'Google', 'facebook' => 'Facebook'];

    /** The services whose keys are set in .env, so their buttons can show. @return list<string> */
    public static function enabled(): array
    {
        return array_values(array_filter(
            array_keys(self::PROVIDERS),
            fn (string $p) => config("services.$p.client_id") && config("services.$p.client_secret"),
        ));
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless(in_array($provider, self::enabled(), true), 404);

        $driver = Socialite::driver($provider);
        if ($provider === 'facebook') {
            $driver->scopes(['email']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::enabled(), true), 404);
        $name = self::PROVIDERS[$provider];

        try {
            $remote = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            // Cancelled on the service's page, an expired attempt or a wrong key.
            report($e);

            return $this->fail($request, $provider, __('Could not sign in with :provider. Please try again.', ['provider' => $name]));
        }

        $account = SocialAccount::where('provider', $provider)->where('provider_id', (string) $remote->getId())->first();
        $user = $account?->user;

        if (! $user) {
            $email = Str::lower(trim((string) $remote->getEmail()));
            if ($email === '') {
                return $this->fail($request, $provider, __(':provider did not share your email address. Please sign up with your email instead.', ['provider' => $name]));
            }
            if (BannedEmails::has($email)) {
                return $this->fail($request, $provider, __('auth.failed'), login: $email);
            }

            $user = DB::transaction(function () use ($email, $remote, $provider) {
                $user = User::where('email', $email)->first() ?? User::create([
                    'name' => $remote->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    // Never used to sign in; "Forgot password" sets a real one if wanted.
                    'password' => Hash::make(Str::random(40)),
                    'locale' => app()->getLocale(),
                ]);
                $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => (string) $remote->getId()]);
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                return $user;
            });
            if ($user->wasRecentlyCreated) {
                NewMember::send($user, $name);
            }
        }

        if ($user->blocked) {
            return $this->fail($request, $provider, __('auth.failed'), $user);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Back to the login page with the reason; the attempt goes to the login journal. */
    private function fail(Request $request, string $provider, string $message, ?User $user = null, ?string $login = null): RedirectResponse
    {
        LoginLog::record($request, $user, $provider, false, $login ?? $user?->email);

        return to_route('login')->withErrors(['email' => $message]);
    }
}
