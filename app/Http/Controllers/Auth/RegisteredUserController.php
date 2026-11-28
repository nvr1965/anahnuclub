<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewMember;
use App\Support\BannedEmails;
use App\Support\SiteMeta;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register')->withViewData(['meta' => SiteMeta::page(__('Register'))]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class, BannedEmails::rule()],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // 16 or older, and the terms of use and the community rules accepted.
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('Please confirm that you are 16 or older and accept the terms of use and the community rules.')]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'locale' => app()->getLocale(),
        ]);

        event(new Registered($user));
        NewMember::send($user, 'email');

        Auth::login($user, true);

        return to_route('verification.notice');
    }
}
