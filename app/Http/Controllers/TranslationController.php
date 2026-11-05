<?php

namespace App\Http\Controllers;

use App\Http\Resources\TranslationPayload;
use App\Support\Html;
use App\Translation\ContentTranslator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            'items.*.type' => ['required', 'in:post,comment,group,event,video,message,moment'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        $locale = app()->getLocale();
        $results = [];

        foreach ($items as $item) {
            $model = Relation::getMorphedModel($item['type'])::find($item['id']);
            $hidden = match ($item['type']) {
                'event' => ! EventController::visible($request)->whereKey($item['id'])->exists(),
                'video' => ! VideoController::visible($request)->whereKey($item['id'])->exists(),
                // Only the people in the chat, and not their own messages.
                'message' => ! $model || $model->user_id === $request->user()?->id
                    || ! $model->conversation->users()->whereKey($request->user()?->id)->exists(),
                'moment' => ! $model || ! $request->user() || ! $model->isVisibleTo($request->user()),
                default => false,
            };
            if (! $model || $hidden) {
                continue;
            }
            $onDemand = config('services.translation.on_demand') || in_array($item['type'], ['message', 'moment'], true)
                || TranslationPayload::translatedOnOpen($model, $locale);
            if (! $onDemand && ! TranslationPayload::isFresh($model)) {
                continue;
            }
            // The page asks again every few seconds; after a failure (services down or out of today's limit)
            // only stored translations are read for a while, so the asking does not hammer the services.
            $failedKey = 'translate-on-open-failed.'.$item['type'].'.'.$item['id'].'.'.$locale;
            $onDemand = $onDemand && ! Cache::has($failedKey);
            $fields = [];
            foreach ($model->translatableFields() as $field) {
                $text = $onDemand
                    ? $translator->translate($model, $field, $locale)
                    : ($translator->needsTranslation($model, $field, $locale) ? $model->cachedTranslation($field, $locale) : null);
                if ($onDemand && $translator->lastFailed) {
                    Cache::put($failedKey, true, now()->addMinutes(15));
                }
                if ($text !== null && ! TranslationPayload::same($text, (string) $model->getAttribute($field))) {
                    $fields[$field] = $field === 'title' || $field === 'name' ? $text : Html::clean($text);
                }
            }
            $results[] = ['type' => $item['type'], 'id' => $item['id'], 'fields' => $fields];
        }

        return response()->json(['locale' => $locale, 'results' => $results]);
    }
}
