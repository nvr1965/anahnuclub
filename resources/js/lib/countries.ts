/** ISO 3166-1 codes; names come from the browser in the reader's language. */
const CODES =
    'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW'.split(
        ' ',
    );

/** Where most members live, offered first. */
const FIRST = ['IL', 'RU', 'UA', 'BY', 'KZ', 'US', 'DE'];

export function countryName(code: string, locale: string): string {
    try {
        return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code;
    } catch {
        return code;
    }
}

// Flag pictures (emoji flags do not show on Windows). Only the address is in the page; a flag loads when shown.
const FLAGS = import.meta.glob<string>('/node_modules/flag-icons/flags/4x3/*.svg', { query: '?url', import: 'default', eager: true });

export function countryFlagUrl(code: string): string | null {
    return FLAGS[`/node_modules/flag-icons/flags/4x3/${code.toLowerCase()}.svg`] ?? null;
}

/** [code, "Israel"] pairs: the usual countries first, then the rest by name. */
export function countryOptions(locale: string): [string, string][] {
    const label = (code: string) => countryName(code, locale);
    const rest = CODES.filter((c) => !FIRST.includes(c))
        .map((c) => [c, countryName(c, locale)] as const)
        .sort((a, b) => a[1].localeCompare(b[1], locale));
    return [...FIRST.map((c) => [c, label(c)] as [string, string]), ...rest.map(([c]) => [c, label(c)] as [string, string])];
}
