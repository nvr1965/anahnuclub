<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\TranslateInterface;
use App\Models\Language;
use App\Translation\InterfaceStrings;
use App\Translation\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends AdminController
{
    /** The languages the site was built with; their interface text lives in the code. */
    public const BUILT_IN = ['ru', 'he', 'en', 'ar'];

    public function index(Translator $translator): Response
    {
        $total = InterfaceStrings::total();

        return Inertia::render('admin/languages', [
            'languages' => Language::orderBy('sort')->get(['code', 'name', 'native_name', 'rtl', 'enabled'])
                ->map(fn (Language $l) => $l->toArray() + [
                    'missing' => count(InterfaceStrings::missing($l->code)),
                    'built_in' => in_array($l->code, self::BUILT_IN, true),
                ]),
            'total' => $total,
            'can_translate' => $translator->name() !== 'null',
        ]);
    }

    /** Adds a language and has its interface and pages machine-translated; it switches on when ready. */
    public function store(Request $request, Translator $translator): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z]{2,3}$/', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'rtl' => ['boolean'],
        ]);

        if (method_exists($translator, 'supports') && ! $translator->supports($data['code'])) {
            throw ValidationException::withMessages(['code' => __('DeepL does not translate into this language. Add ANTHROPIC_API_KEY to .env and Claude will translate it.')]);
        }

        Language::create($data + ['rtl' => false, 'enabled' => false, 'sort' => (Language::max('sort') ?? 0) + 1]);
        TranslateInterface::dispatch($data['code'], true);

        return back();
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        abort_if(! $enabled && (Language::enabled()->count() <= 1 || $language->code === config('app.fallback_locale')), 422, 'This language must stay on.');

        $language->update(['enabled' => $enabled]);

        return back();
    }

    /** Translates interface strings added since, e.g. after an update of the site. */
    public function refill(Language $language): RedirectResponse
    {
        TranslateInterface::dispatch($language->code);

        return back();
    }

    public function destroy(Language $language): RedirectResponse
    {
        abort_if(in_array($language->code, self::BUILT_IN, true) || $language->code === config('app.fallback_locale'), 422);

        InterfaceStrings::forget($language->code);
        $language->delete();

        return back();
    }
}
