<?php

namespace App\Http\Controllers;

use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Translates posts and comments the page could not show translated yet.
 * The page sends a few at a time, so one slow provider call never blocks the whole feed.
 */
class TranslationController extends Controller
{
    public function __invoke(Request $request, ContentTranslator $translator): JsonResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array', 'max:5'],
            'items.*.type' => ['required', 'in:post,comment,group'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        $locale = app()->getLocale();
        $results = [];

        foreach ($items as $item) {
            $model = Relation::getMorphedModel($item['type'])::find($item['id']);
            if (! $model) {
                continue;
            }
            $fields = [];
            foreach ($model->translatableFields() as $field) {
                $text = $translator->translate($model, $field, $locale);
                if ($text !== null) {
                    $fields[$field] = $field === 'title' || $field === 'name' ? $text : Html::clean($text);
                }
            }
            $results[] = ['type' => $item['type'], 'id' => $item['id'], 'fields' => $fields];
        }

        return response()->json(['locale' => $locale, 'results' => $results]);
    }
}
