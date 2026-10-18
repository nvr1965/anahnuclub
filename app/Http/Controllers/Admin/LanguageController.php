<?php

namespace App\Http\Controllers\Admin;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends AdminController
{
    public function index(): Response
    {
        return Inertia::render('admin/languages', [
            'languages' => Language::orderBy('sort')->get(['code', 'name', 'native_name', 'rtl', 'enabled']),
        ]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        abort_if(! $enabled && (Language::enabled()->count() <= 1 || $language->code === config('app.fallback_locale')), 422, 'This language must stay on.');

        $language->update(['enabled' => $enabled]);

        return back();
    }
}
