<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #Hashtags and @friends in the text of a post, in any alphabet: #шаббат, #שבת, #سبت, #Haifa2026.
 * Both become links: a hashtag to the page of every post that carries it, a friend to their profile.
 */
class PostTags
{
    /** Hashtags a post keeps, at most; a list of a hundred is spam, not topics. */
    public const MAX_HASHTAGS = 30;

    /** Friends tagged in one post, at most. */
    public const MAX_PEOPLE = 50;

    /**
     * A # and letters, digits or _ of any alphabet (with Hebrew and Arabic vowel marks), at least one a letter:
     * "#1" is a number, not a topic. Not in the middle of a word, an address (/page#part) or an entity (&#039;).
     */
    private const HASHTAG = '/(?<![\p{L}\p{M}\p{N}_&#\/])#([\p{L}\p{M}\p{N}_]*\p{L}[\p{L}\p{M}\p{N}_]*)/u';

    /** The text of a post as words, without its markup. */
    public static function plain(?string $html): string
    {
        return html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', "\n", (string) $html) ?? ''), ENT_QUOTES | ENT_HTML5);
    }

    /** One spelling for a hashtag: lower case, without the #. */
    public static function normalize(string $tag): string
    {
        return mb_substr(mb_strtolower(ltrim(trim($tag), '#')), 0, 100);
    }

    /** Whether the words are one hashtag, with or without its #. */
    public static function isHashtag(string $text): bool
    {
        return preg_match('/^#?[\p{L}\p{M}\p{N}_]*\p{L}[\p{L}\p{M}\p{N}_]*$/u', trim($text)) === 1;
    }

    /**
     * The hashtags of a text, each once, in the order they come.
     *
     * @return list<string>
     */
    public static function hashtags(string $text): array
    {
        preg_match_all(self::HASHTAG, $text, $m);

        return array_slice(array_values(array_unique(array_map(self::normalize(...), $m[1]))), 0, self::MAX_HASHTAGS);
    }

    public static function url(string $tag): string
    {
        return '/hashtag/'.rawurlencode(self::normalize($tag));
    }

    /**
     * The people of $candidates named in the text as "@Full Name", the way the composer writes them.
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>
     */
    public static function mentioned(string $text, Collection $candidates): Collection
    {
        if (! str_contains($text, '@')) {
            return collect();
        }

        return $candidates->filter(fn ($user) => trim((string) $user->name) !== ''
            && preg_match('/(?<![\p{L}\p{N}_])@'.preg_quote($user->name, '/').'(?![\p{L}\p{M}\p{N}_])/u', $text) === 1)->values();
    }

    /**
     * Makes the hashtags, and the @names of $people, links in HTML that Html::fromPlainText or Html::clean
     * made. Words already inside a link stay as they are, so it can run over its own result again.
     * The links say translate="no": the translators keep a name and a hashtag as written.
     *
     * @param  iterable<User>  $people
     */
    public static function link(string $html, iterable $people = []): string
    {
        if (! str_contains($html, '#') && ! str_contains($html, '@')) {
            return $html;
        }
        // The longest names first, so "@Anna Bell" is not taken for "@Anna".
        $people = collect($people)->sortByDesc(fn ($user) => mb_strlen((string) $user->name))->values();
        $parts = preg_split('~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $inLink = 0;
        foreach ($parts as $i => $part) {
            if (str_starts_with($part, '<')) {
                if (preg_match('~^<a[\s>]~i', $part)) {
                    $inLink++;
                } elseif (preg_match('~^</a\s*>~i', $part)) {
                    $inLink = max(0, $inLink - 1);
                }

                continue;
            }
            if ($inLink > 0 || $part === '') {
                continue;
            }
            // Hashtags first: the names put in next are links, which no hashtag goes into.
            $part = preg_replace_callback(self::HASHTAG, fn ($m) => '<a href="'.e(self::url($m[1])).'" translate="no">#'.$m[1].'</a>', $part) ?? $part;
            foreach ($people as $user) {
                $name = e($user->name);
                if ($name === '' || ! str_contains($part, '@'.$name)) {
                    continue;
                }
                // Only in the words, not inside a hashtag link just made.
                $pieces = preg_split('~(<a [^>]*>.*?</a>)~u', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part];
                foreach ($pieces as $j => $piece) {
                    if (! str_starts_with($piece, '<a ')) {
                        $pieces[$j] = preg_replace(
                            '/(?<![\p{L}\p{N}_])@'.preg_quote($name, '/').'(?![\p{L}\p{M}\p{N}_])/u',
                            '<a href="/people/'.$user->id.'" translate="no">'.str_replace(['\\', '$'], ['\\\\', '\\$'], $name).'</a>',
                            $piece,
                        ) ?? $piece;
                    }
                }
                $part = implode('', $pieces);
            }
            $parts[$i] = $part;
        }

        return implode('', $parts);
    }

    /** The text a member edits: a friend's link back to "@Name", a hashtag link back to its words. */
    public static function editable(string $html): string
    {
        return preg_replace('~<a\s[^>]*href="/people/\d+"[^>]*>(.*?)</a>~u', '@$1', $html) ?? $html;
    }

    /** Keeps the list of a post's hashtags in step with its title and text. */
    public static function syncHashtags(Post $post): void
    {
        $tags = self::hashtags(self::plain($post->title.' '.$post->body));
        DB::table('post_hashtags')->where('post_id', $post->id)->whereNotIn('tag', $tags)->delete();
        DB::table('post_hashtags')->insertOrIgnore(array_map(
            fn ($tag) => ['post_id' => $post->id, 'tag' => $tag, 'created_at' => $post->created_at ?? now()],
            $tags,
        ));
    }
}
