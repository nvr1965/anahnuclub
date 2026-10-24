<?php

namespace App\Listeners;

use App\Http\Controllers\Auth\SocialLoginController;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Writes the login journal: every sign-in (password, Google, Facebook, right after signing up,
 * by the "remember me" cookie once the session is over) and every wrong password.
 * Coming back while still signed in is written by TouchLastSeen.
 * A failure to write never stops the member from signing in.
 */
class LogLogins
{
    public function login(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->write($event->user, $this->method(), true);
            // So that the next page is not also written as a return visit.
            $event->user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    public function failed(Failed $event): void
    {
        $credentials = $event->credentials;
        $login = $credentials['email'] ?? $credentials['username'] ?? null;
        $user = $event->user instanceof User ? $event->user : null;
        // Blocked members are not found by the attempt (it asks for blocked = false); still put it under their name.
        if (! $user && is_string($login) && $login !== '') {
            $user = User::where(isset($credentials['email']) ? 'email' : 'username', $login)->first();
        }

        $this->write($user, 'password', false, is_string($login) ? $login : null);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [Login::class => 'login', Failed::class => 'failed'];
    }

    private function method(): string
    {
        $request = request();
        $provider = $request->route('provider');

        return match (true) {
            $request->routeIs('social.callback') && isset(SocialLoginController::PROVIDERS[$provider]) => $provider,
            $request->is('register') => 'register',
            $request->is('login') => 'password',
            // Any other page: the session was over and the "remember me" cookie signed the member back in.
            default => 'remember',
        };
    }

    private function write(?User $user, string $method, bool $success, ?string $login = null): void
    {
        try {
            LoginLog::record(request(), $user, $method, $success, $login);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
