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
            throw ValidationException::withMessages(['code' => __('DeepL does not translate into this language. Add GEMINI_API_KEY or ANTHROPIC_API_KEY to .env and Gemini or Claude will translate it.')]);
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

    /** Every interface text of a language next to the English, to read and correct by hand. */
    public function texts(Language $language): Response
    {
        return Inertia::render('admin/language-texts', [
            'language' => $language->only('code', 'name', 'native_name', 'rtl'),
            'rows' => InterfaceStrings::rows($language->code),
        ]);
    }

    /** A hand-made text for one key; empty brings back the machine (or built-in) one. */
    public function correct(Request $request, Language $language): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string'], 'text' => ['nullable', 'string', 'max:2000']]);
        abort_unless(InterfaceStrings::has($data['key']), 404);

        $text = trim($data['text'] ?? '');
        $need = InterfaceStrings::placeholders($data['key']);
        if ($text !== '' && InterfaceStrings::placeholders($text) !== $need) {
            throw ValidationException::withMessages(['text' => __('Keep these placeholders in the text: :list', ['list' => implode(' ', $need)])]);
        }

        InterfaceStrings::correct($language->code, $data['key'], $text);

        return back();
    }

    /** Machine-translates the interface again from scratch; hand-made corrections stay. */
    public function retranslate(Language $language): RedirectResponse
    {
        abort_if(in_array($language->code, self::BUILT_IN, true), 422);

        InterfaceStrings::forgetAuto($language->code);
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
