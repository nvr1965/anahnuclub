<?php

namespace App\Moderation;

use App\Models\Setting;

/**
 * Looks for words that often mark unwanted posts: drugs and pills, porn, prostitution, gambling.
 * The lists cover the club's languages (and translations of posts are scanned too), so a post
 * written in one language is caught by words in any of them. A hit is only a hint for an admin.
 */
class ContentScanner
{
    public const CATEGORIES = ['drugs', 'porn', 'prostitution', 'casino'];

    /**
     * Word beginnings; a leading "^" means the word must start there (so "sex" does not match "Essex"),
     * otherwise it may sit inside a word, which suits Hebrew and Arabic with their attached prefixes.
     */
    public const WORDS = [
        'drugs' => [
            // ru / uk
            '^наркот', '^героин', '^кокаин', '^амфетамин', '^мефедрон', '^метадон', '^марихуан', '^гашиш', '^спайс', '^закладк', '^экстази', '^трамадол', '^оксикодон', '^фентанил', '^ксанакс', '^виагр', '^сиалис', '^кодеин', 'без рецепта', '^мдма', '^лсд\b',
            // en / de
            '^cocaine', '^heroin', '^methamphetamine', '^meth\b', '^mdma\b', '^ecstasy', '^cannabis', '^marijuana', '^fentanyl', '^oxycodone', '^tramadol', '^xanax', '^adderall', '^viagra', '^cialis', '^kratom', '^lsd\b', 'without (a )?prescription', 'no prescription', 'online pharmacy', 'buy pills', '^kokain', '^drogen', 'ohne rezept',
            // he
            'סמים', 'קוקאין', 'הרואין', 'מריחואנה', 'קנאביס', 'ויאגרה', 'ללא מרשם',
            // ar
            'مخدرات', 'كوكايين', 'هيروين', 'حشيش', 'ماريجوانا', 'ترامادول', 'فياجرا', 'بدون وصفة',
        ],
        'porn' => [
            '^порн', '^порев', '^секс', '^эротик', '^вебкам', '^минет', '^голые девушк', '^xxx',
            '^porn', '^sex\b', '^sexy\b', '^nudes?\b', '^naked\b', '^nsfw', '^onlyfans', '^camgirl', '^hentai', '^erotic', '^nackt', '^erotik',
            'פורנו', 'סקס', 'עירום', 'ארוטי',
            'إباحي', 'اباحي', 'سكس', 'جنسي',
        ],
        'prostitution' => [
            '^проститу', '^шлюх', '^путан', '^интим.?услуг', '^эскорт', 'досуг для мужчин', '^индивидуалк',
            '^escort', '^prostitu', 'call girls?', '^hooker', 'happy ending', 'sugar daddy', '^hookup', '^bordell', '^nutte', '^hure\b',
            'זונה', 'זנות', 'נערות ליווי', 'שירותי ליווי',
            'دعارة', 'عاهرة', 'مرافقات',
        ],
        'casino' => [
            '^казино', '^букмекер', '^ставки на спорт', '^рулетк', '^игровые автомат', '^джекпот', '^мостбет', '^1хбет', 'бонус за регистрацию', '^фриспин',
            '^casino', '^gambling', '^betting\b', '^bookmaker', '^roulette', '^jackpot', '^slots?\b', '^mostbet', '^1xbet', '^melbet', '^betwinner', '^pin-?up casino', 'free spins', 'no deposit bonus', 'toto ?site', '^kasino', '^glücksspiel', '^spielautomat', '^sportwetten',
            'קזינו', 'הימורים', 'רולטה', 'מכונות מזל',
            'كازينو', 'قمار', 'مراهنات', 'روليت',
            '토토', '카지노', '바카라',
        ],
    ];

    /** @var array<string, string> */
    private array $patterns = [];

    public function __construct(?array $extra = null)
    {
        $extra ??= self::extraWords();
        foreach (self::CATEGORIES as $category) {
            $words = array_merge(self::WORDS[$category], $extra[$category] ?? []);
            $parts = array_map(fn (string $w) => str_replace('\b', '(?![\p{L}\p{N}])', str_starts_with($w, '^') ? '(?<![\p{L}\p{N}])'.substr($w, 1) : $w), array_filter($words));
            $this->patterns[$category] = '/(?:'.implode('|', $parts).')[\p{L}\p{N}]*/iu';
        }
    }

    /** All the words as one PostgreSQL pattern, so the database can pick out candidates before the exact check. */
    public static function databasePattern(): string
    {
        $words = collect(self::WORDS)->flatten()->merge(collect(self::extraWords())->flatten())
            ->map(fn (string $w) => str_replace(['^', '\b'], '', $w));

        return implode('|', $words->unique()->all());
    }

    /** Words admins added on the moderation page, one list per category. @return array<string, list<string>> */
    public static function extraWords(): array
    {
        $saved = json_decode(Setting::get('moderation_words') ?? '{}', true) ?: [];

        return collect(self::CATEGORIES)->mapWithKeys(fn ($c) => [$c => collect(preg_split('/[,\n]+/u', (string) ($saved[$c] ?? '')))
            ->map(fn ($w) => trim(mb_strtolower($w)))
            ->filter()
            // Typed by hand: taken literally, at the start of a word.
            ->map(fn ($w) => '^'.preg_quote($w, '/'))
            ->values()->all()])->all();
    }

    /**
     * Categories found in the texts, each with the words that matched.
     *
     * @param  list<?string>  $texts
     * @return array<string, list<string>>
     */
    public function scan(array $texts): array
    {
        $text = self::plain(implode("\n", array_filter($texts)));
        $hits = [];
        foreach ($this->patterns as $category => $pattern) {
            if (preg_match_all($pattern, $text, $m)) {
                $hits[$category] = array_values(array_slice(array_unique(array_map(fn ($w) => mb_strtolower($w), $m[0])), 0, 6));
            }
        }

        return $hits;
    }

    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', $html)), ENT_QUOTES | ENT_HTML5);

        return str_replace(['ё', 'Ё'], ['е', 'Е'], trim(preg_replace('/\s+/u', ' ', $text)));
    }
}
