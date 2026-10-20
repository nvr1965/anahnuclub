<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Translation\TranslatorChain;
use App\Translation\TranslatorKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** The translation services: which are set up, the order they are tried in, daily limits, a test. */
class TranslatorController extends AdminController
{
    public function index(TranslatorChain $chain): Response
    {
        return Inertia::render('admin/translators', ['translators' => $chain->describe()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'translators' => ['present', 'array'],
            'translators.*.id' => ['required', 'string', 'in:'.implode(',', array_keys(config('translation.providers')))],
            'translators.*.on' => ['required', 'boolean'],
            'translators.*.daily_chars' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        Setting::put('translators', json_encode(array_map(
            fn ($row) => ['id' => $row['id'], 'on' => (bool) $row['on'], 'daily_chars' => (int) $row['daily_chars']],
            array_values($data['translators']),
        )));

        return back();
    }

    /** Keys and addresses entered here instead of in .env; an empty value goes back to .env. */
    public function keys(Request $request, string $translator): RedirectResponse
    {
        abort_unless(config('translation.providers.'.$translator), 404);
        $paths = TranslatorKeys::paths($translator);
        $values = $request->validate(['values' => ['present', 'array'], 'values.*' => ['nullable', 'string', 'max:500']])['values'];
        $hadEmail = (bool) config('services.mymemory.email');

        TranslatorKeys::put(array_intersect_key($values, array_flip($paths)));

        // With an email address MyMemory allows ten times more; raise a limit still at the old free amount.
        if ($translator === 'mymemory' && ! $hadEmail && config('services.mymemory.email')) {
            $rows = json_decode(Setting::get('translators') ?? 'null', true);
            if (is_array($rows)) {
                foreach ($rows as &$row) {
                    if ($row['id'] === 'mymemory' && (int) $row['daily_chars'] === (int) config('translation.providers.mymemory.daily_chars')) {
                        $row['daily_chars'] = TranslatorChain::defaultLimit('mymemory');
                    }
                }
                Setting::put('translators', json_encode($rows));
            }
        }

        return back();
    }

    /** Translates a sample with one service, to see that its key works and how it sounds. */
    public function check(Request $request, TranslatorChain $chain, string $translator): JsonResponse
    {
        $locale = $request->validate(['locale' => ['required', 'string', 'regex:/^[a-z]{2,3}$/']])['locale'];
        $provider = $chain->only($translator);
        abort_unless($provider, 404);

        try {
            if (! $provider->supports($locale)) {
                return response()->json(['ok' => false, 'text' => __('This service does not translate into this language.')]);
            }

            return response()->json(['ok' => true, 'text' => $provider->translate('Good morning, friends! See you at the club tonight.', $locale, 'en')]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'text' => mb_strimwidth($e->getMessage(), 0, 300, '…')]);
        }
    }
}
