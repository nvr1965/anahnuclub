<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Loose text matching for site search: big and small letters are the same, "е" and "ё" are the same, every word of
 * the query is found anywhere in the text, and Russian words lose their ending ("Новости" finds "новостей", "новость").
 *
 * PostgreSQL's ILIKE folds case only as far as the database locale knows the letters; a database made with the C
 * locale treats "Н" and "н" as different. So on PostgreSQL each letter becomes a class such as [Нн] in a regular
 * expression, which works under any locale. Other databases get the words in their usual spellings (as typed, small,
 * capital, first letter capital), matched with LIKE.
 */
final class TextSearch
{
    /** Words shorter than this are kept whole. */
    private const STEM_MIN = 5;

    /** @return list<string> the words to look for, each at least two letters, at most six of them. */
    public static function terms(string $q): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];
        foreach ($words as $word) {
            $word = self::stem($word);
            if (mb_strlen($word) >= 2 && ! in_array($word, $terms, true)) {
                $terms[] = $word;
            }
        }
        // A query of only one-letter words or symbols is searched as typed.
        if (! $terms && mb_strlen(trim($q)) >= 2) {
            $terms[] = mb_strtolower(trim($q));
        }

        return array_slice($terms, 0, 6);
    }

    /** Drops a Russian ending of up to two letters, keeping at least four: "новости" → "новост", "израиля" → "израил". */
    public static function stem(string $word): string
    {
        if (! preg_match('/^\p{Cyrillic}+$/u', $word) || mb_strlen($word) < self::STEM_MIN) {
            return $word;
        }
        $stem = preg_replace('/[аеёиийоуыьэюя]{1,2}$/u', '', $word);

        return mb_strlen($stem) >= 4 ? $stem : mb_substr($word, 0, 4);
    }

    /** Adds "column contains term" to the query, joined with OR. */
    public static function orColumn(Builder $query, string $column, string $term): Builder
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->orWhere($column, '~', self::regex($term));
        }

        return $query->orWhere(function (Builder $any) use ($column, $term) {
            foreach (self::spellings($term) as $spelling) {
                $any->orWhere($column, 'like', '%'.addcslashes($spelling, '%_\\').'%');
            }
        });
    }

    /**
     * Puts first the rows where every term is in one of these columns (a group's name, a post's text), so a row
     * found only through a comment or a translation comes after them.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $terms
     */
    public static function orderByColumns(Builder $query, array $columns, array $terms): Builder
    {
        $grammar = $query->getQuery()->getGrammar();
        $pgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $all = [];
        $bindings = [];
        foreach ($terms as $term) {
            $any = [];
            foreach ($columns as $column) {
                $wrapped = $grammar->wrap($query->qualifyColumn($column));
                if ($pgsql) {
                    $any[] = "$wrapped ~ ?";
                    $bindings[] = self::regex($term);
                } else {
                    foreach (self::spellings($term) as $spelling) {
                        $any[] = "$wrapped like ?";
                        $bindings[] = '%'.addcslashes($spelling, '%_\\').'%';
                    }
                }
            }
            $all[] = '('.implode(' or ', $any).')';
        }

        return $all ? $query->orderByRaw('case when '.implode(' and ', $all).' then 0 else 1 end', $bindings) : $query;
    }

    /** Whether the text holds every term, as the database search would decide it. */
    public static function matches(string $text, array $terms): bool
    {
        $text = str_replace('ё', 'е', mb_strtolower($text));
        foreach ($terms as $term) {
            if (! str_contains($text, str_replace('ё', 'е', $term))) {
                return false;
            }
        }

        return true;
    }

    /** "нов" → "[Нн][Оо][Вв]"; "е" and "ё" stand for each other; anything that is not a letter or digit is escaped. */
    public static function regex(string $term): string
    {
        $out = '';
        foreach (mb_str_split($term) as $char) {
            $lower = mb_strtolower($char);
            if ($lower === 'е' || $lower === 'ё') {
                $out .= '[еёЕЁ]';
            } elseif ($lower !== mb_strtoupper($char)) {
                $out .= '['.$lower.mb_strtoupper($char).']';
            } elseif (preg_match('/^[\p{L}\p{N}\s]$/u', $char)) {
                $out .= $char;
            } else {
                $out .= '\\'.$char;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function spellings(string $term): array
    {
        $lower = mb_strtolower($term);
        $variants = [$term, $lower, mb_strtoupper($term), mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1)];
        if (str_contains($lower, 'ё')) {
            $variants[] = str_replace('ё', 'е', $lower);
        }

        return array_values(array_unique($variants));
    }
}
