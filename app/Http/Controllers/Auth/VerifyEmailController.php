<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController extends Controller
{
    /**
     * The link from the "confirm your email" letter. Its signature already proves that whoever opens
     * it read that mailbox, so it confirms the address of the account it was sent to whatever this
     * browser is signed in as: the letter often opens in another browser than the one used to sign up
     * (or in the installed app, signed in as somebody else), which used to end in a bare 403.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse|Response
    {
        $owner = User::find($id);
        $me = $request->user();

        // The address was changed after the letter went out, or the account is gone.
        if (! $owner || ! hash_equals(sha1($owner->getEmailForVerification()), $hash)) {
            if (! $me) {
                return redirect()->route('login');
            }

            return $me->hasVerifiedEmail()
                ? redirect()->route('dashboard')
                : redirect()->route('verification.notice')->with('status', 'verification-link-expired');
        }

        if (! $owner->hasVerifiedEmail() && $owner->markEmailAsVerified()) {
            event(new Verified($owner));
        }

        if ($me?->is($owner)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if (! $me) {
            return redirect()->route('login')->with('status', __('Your email is confirmed. Sign in to continue.'));
        }

        return Inertia::render('auth/email-verified', ['email' => $owner->email]);
    }
}
