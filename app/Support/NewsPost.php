<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Support;

use App\Jobs\FetchLinkPreview;
use App\Jobs\SubmitToIndexNow;
use App\Jobs\TranslateContent;
use App\Models\LinkPreview;
use App\Models\Post;
use App\Models\User;
use App\Translation\LanguageGuesser;

/**
 * A post written by a server rather than a person: news from our other sites (the autopost API)
 * or from the sources of the club's authors. The link becomes a card, from the card data given
 * (so it never depends on reading the page) or else from the page's Open Graph tags.
 */
class NewsPost
{
    /**
     * @param  array{title?: ?string, description?: ?string, image?: ?string}  $card
     * @param  ?string  $source  the name of the paper or channel the text comes from: written under it as
     *                           "— Ynet" and never translated, so the reader always sees whose text it is
     */
    public static function publish(User $user, string $text, ?string $url, array $card = [], int|string|null $groupId = null, ?string $locale = null, ?string $source = null): Post
    {
        if ($url && (! empty($card['title']) || ! empty($card['image']))) {
            LinkPreview::updateOrCreate(['url' => $url], [
                'title' => $card['title'] ?? null,
                'description' => $card['description'] ?? null,
                'image' => $card['image'] ?? null,
                'site_name' => parse_url($url, PHP_URL_HOST),
            ]);
        }

        $body = Html::fromPlainText(trim($text));
        if ($source !== null && trim($source) !== '') {
            $body .= '<br><br>— <span translate="no" class="notranslate">'.e(trim($source)).'</span>';
        }
        if ($url) {
            $body .= '<br><br>'.Html::fromPlainText($url);
        }
        $text = trim($text).($url ? "\n\n".$url : '');
        $post = Post::create([
            'user_id' => $user->id,
            'group_id' => $groupId,
            'type' => 'status',
            'body' => $body,
            'locale' => $locale ?? LanguageGuesser::guess($text) ?? $user->locale,
        ]);
        if (! $groupId) {
            $post->update(['audience' => 'public']);
        }
        // The card is always for the link given, even when the text itself carries another one
        // (news descriptions often end with the source's address).
        if ($url && $post->link_url !== $url) {
            $post->forceFill(['link_url' => $url])->saveQuietly();
            if (! LinkPreview::where('url', $url)->exists()) {
                Background::run(new FetchLinkPreview($url));
            }
        }

        Background::run(new TranslateContent($post));
        Background::run(new SubmitToIndexNow($post));

        return $post;
    }
}
