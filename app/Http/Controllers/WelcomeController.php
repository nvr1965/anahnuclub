<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** The first page after joining: a greeting, where to start, and the community rules to accept. */
class WelcomeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! self::mustAccept($user)) {
            return to_route('dashboard');
        }
        $rules = Page::where('slug', 'rules')->first();
        $text = $rules?->in(app()->getLocale());

        return Inertia::render('welcome-member', [
            // Someone who accepted older rules is only asked about the new ones.
            'again' => $user->rules_accepted_at !== null,
            'rules' => $text ? ['title' => $text['title'], 'body' => Html::clean($text['body']), 'locale' => $text['locale']] : null,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']]);
        $request->user()->forceFill(['rules_accepted_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * No acceptance yet, or one older than the last change an admin asked members to accept again
     * (setting "rules_changed_at"; fixing a typo need not bother everyone).
     */
    public static function mustAccept(User $user): bool
    {
        if (! Cache::remember('rules.published', 300, fn () => Page::where('slug', 'rules')->where('published', true)->exists())) {
            return false;
        }
        $changed = Setting::get('rules_changed_at');

        return $user->rules_accepted_at === null || ($changed && $user->rules_accepted_at->lt(Carbon::parse($changed)));
    }
}
