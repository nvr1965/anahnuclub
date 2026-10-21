<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactReceived;
use App\Support\SiteMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/** The feedback form; messages land in the admin inbox and are copied to the team's mailbox. */
class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('contact', ['sent' => session('contact_sent', false), 'email' => config('services.contact.email')])
            ->withViewData(['meta' => SiteMeta::page(__('Contact us'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        // A field people never see: bots fill it in, and their message is quietly dropped.
        if ($request->filled('website')) {
            return back()->with('contact_sent', true);
        }

        $key = 'contact:'.($user?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['body' => __('Too many messages. Please try again later.')]);
        }
        RateLimiter::hit($key, 3600);

        $message = ContactMessage::create([
            'user_id' => $user?->id,
            'name' => ($data['name'] ?? null) ?: $user?->name,
            'email' => ($data['email'] ?? null) ?: $user?->email,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
        ]);
        if ($to = config('services.contact.email')) {
            Notification::route('mail', $to)->notify(new ContactReceived($message));
        }

        return back()->with('contact_sent', true);
    }
}
