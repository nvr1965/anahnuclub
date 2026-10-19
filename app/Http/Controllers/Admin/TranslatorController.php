<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Translation\TranslatorChain;
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
