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
        $request->user()?->update(['locale' => $locale, 'ui_locale' => $locale]);

        return back();
    }
}
