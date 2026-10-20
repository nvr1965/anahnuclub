<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', 'string']])['locale'];
        abort_unless(Language::isEnabled($locale), 422);

        $request->session()->put('locale', $locale);
        // Only the interface (and so the translations shown); the default language stays as set in the profile.
        $request->user()?->forceFill(['ui_locale' => $locale])->save();

        return back();
    }
}
