<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns translations of posts and comments the page could not show translated yet.
 * Normally it only reads translations made after publishing (the page asks again until
 * they are there); with on-demand translation on it also asks the provider.
 */
class TranslationController extends Controller
{
    public function __invoke(Request $request, ContentTranslator $translator): JsonResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array', 'max:5'],
            'items.*.type' => ['required', 'in:post,comment,group,event,video'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        $locale = app()->getLocale();
        $results = [];

        foreach ($items as $item) {
            $model = Relation::getMorphedModel($item['type'])::find($item['id']);
            $hidden = match ($item['type']) {
                'event' => ! EventController::visible($request)->whereKey($item['id'])->exists(),
                'video' => ! VideoController::visible($request)->whereKey($item['id'])->exists(),
                default => false,
            };
            if (! $model || $hidden) {
                continue;
            }
            $onDemand = config('services.translation.on_demand');
            if (! $onDemand && ! TranslationPayload::isFresh($model)) {
                continue;
            }
            $fields = [];
            foreach ($model->translatableFields() as $field) {
                $text = $onDemand
                    ? $translator->translate($model, $field, $locale)
                    : ($translator->needsTranslation($model, $field, $locale) ? $model->cachedTranslation($field, $locale) : null);
                if ($text !== null) {
                    $fields[$field] = $field === 'title' || $field === 'name' ? $text : Html::clean($text);
                }
            }
            $results[] = ['type' => $item['type'], 'id' => $item['id'], 'fields' => $fields];
        }

        return response()->json(['locale' => $locale, 'results' => $results]);
    }
}
