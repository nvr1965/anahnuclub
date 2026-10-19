# Translation block

Everything about machine translation lives here, apart from the app code that calls it.

## Services (`Providers/`)

| id | service | price | needs in `.env` |
|---|---|---|---|
| `deepl` | DeepL | free up to 500,000 chars/month | `DEEPL_API_KEY` |
| `google` | Google Cloud Translation | free up to 500,000 chars/month | `GOOGLE_TRANSLATE_KEY` |
| `gemini` | Google Gemini | free tier with request limits | `GEMINI_API_KEY` (`GEMINI_TRANSLATION_MODEL`) |
| `libretranslate` | LibreTranslate | free, own server | `LIBRETRANSLATE_URL` (`LIBRETRANSLATE_KEY`) |
| `mymemory` | MyMemory | free, 5,000 chars/day (50,000 with `MYMEMORY_EMAIL`) | nothing |
| `claude` | Anthropic Claude | paid | `ANTHROPIC_API_KEY` (`ANTHROPIC_TRANSLATION_MODEL`) |

Each one extends `Providers\Provider`: `translateMany()` (or `translate()`), and `supports($locale)`
when it does not know every language (DeepL and LibreTranslate ask the service once a day).

## The chain (`TranslatorChain`)

Bound as `Translator`. A text goes to the first service that is set up, switched on, knows the
target language and has room in its daily limit; if it throws, the next one is tried. The service
used is recorded with each stored translation (`name()`).

Order, on/off and daily limits: admin page **Translators** (setting `translators`). Before that,
`config/translation.php` (`TRANSLATION_PROVIDERS=deepl,gemini,mymemory` to change it; empty for none).
`TRANSLATION_DRIVER` from older setups still puts that service first.

## Adding a service

1. A class in `Providers/` extending `Provider`.
2. An entry in `config/translation.php` (label, price, the config value it needs, daily limit).
3. A line in `TranslatorChain::build()`.

## Interface texts (`InterfaceStrings`)

`lang/{code}.json` from the code, `storage/app/lang/{code}.json` machine-translated for added
languages, `storage/app/lang/{code}.edits.json` corrected by hand on the admin page (never overwritten).
A translation that loses a placeholder such as `:name` keeps the English.
