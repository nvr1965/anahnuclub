<?php

/*
 * The club's authors at the start of the feed filling, and their sources. Each author's name and topic
 * are in the language it posts in; 'min' and 'limit' are its posts a day (a number between them each day).
 * Read by `php artisan feeds:setup`, which adds what is missing and never changes what an admin edited.
 * A source is an RSS/Atom address, a public Telegram channel (https://t.me/s/<channel>), or the front page of
 * Panet or Bokra (read without RSS, see app/Feeds/PageParsers.php). The Israeli, Arab and Telegram ones are
 * those that work on nofinfo; the others were written from memory: the first run of feeds:fetch shows on
 * the admin page which of them answer; fix or switch off the others there.
 *
 * 'was' is the author's earlier name: feeds:setup renames an author that still has it (name, language, topic, profile line).
 * 'group' is the topic group the author posts in (the list 'groups' below: slug, name, description).
 * 'retired' are addresses given earlier that do not work: feeds:setup switches them off.
 */
return [
    ['slug' => 'david', 'group' => 'israel', 'name' => 'דוד', 'locale' => 'he', 'limit' => 3, 'topic' => 'ישראל: החדשות העיקריות', 'sources' => [
        ['Ynet', 'https://www.ynet.co.il/Integration/StoryRss2.xml', 'he'],
        ['Kan News', 'https://www.kan.org.il/rss/', 'he'],
        ['Times of Israel', 'https://www.timesofisrael.com/feed/', 'en'],
        ['Jerusalem Post', 'https://www.jpost.com/rss/rssfeedsheadlines.aspx', 'en'],
        ['Israel Hayom', 'https://www.israelhayom.co.il/rss.xml', 'he'],
        ['Haaretz · Telegram', 'https://t.me/s/haaretz_news', 'he'],
    ]],
    ['slug' => 'marina', 'group' => 'israel', 'name' => 'Марина', 'locale' => 'ru', 'min' => 1, 'limit' => 2, 'topic' => 'Израиль по-русски, репатриантам: деньги, права, иврит', 'sources' => [
        ['NEWSru.co.il', 'https://www.newsru.co.il/il/www/news/rss.xml', 'ru'],
        ['Вести', 'https://www.vesty.co.il/rss', 'ru'],
        ['nofinfo.com', 'https://nofinfo.com/rss', 'ru'],
        ['9 Канал', 'https://www.9tv.co.il/rss/mainfeed', 'ru'],
        ['Новости Израиля · Telegram', 'https://t.me/s/israelinfo_co_il', 'ru'],
        ['Вести Израиль · Telegram', 'https://t.me/s/vestyisrael', 'ru'],
        ['Курсор · Telegram', 'https://t.me/s/cursorinfo', 'ru'],
    ]],
    ['slug' => 'ali', 'group' => 'arab-world', 'name' => 'علي', 'locale' => 'ar', 'min' => 1, 'limit' => 2, 'topic' => 'العرب في إسرائيل والعالم العربي', 'sources' => [
        ['Panet', 'https://panet.com/', 'ar'],
        ['Bokra', 'https://bokra.net/', 'ar'],
        ['Bokra · Telegram', 'https://t.me/s/bokranews', 'ar'],
        ['BBC Arabic', 'https://feeds.bbci.co.uk/arabic/rss.xml', 'ar'],
        ['الجزيرة', 'https://www.aljazeera.net/aljazeerarss/a7c186be-1baa-4bd4-9d80-a84db769f779/73d0e1b4-532f-45ef-b135-bfdff8b8cab9', 'ar'],
        ['عرب 48', 'https://www.arab48.com/rss', 'ar'],
        ['Arab News', 'https://www.arabnews.com/rss.xml', 'en'],
    ]],
    ['slug' => 'farik', 'group' => 'central-asia', 'name' => 'Farik', 'was' => 'Фарик', 'locale' => 'uz', 'min' => 1, 'limit' => 2, 'topic' => 'O‘zbekiston: yangiliklar va qiziqarli voqealar', 'sources' => [
        ['Kun.uz', 'https://kun.uz/news/rss', 'uz'],
        ['Daryo', 'https://daryo.uz/feed/', 'uz'],
        ['Gazeta.uz', 'https://www.gazeta.uz/oz/rss/', 'uz'],
        ['BBC O‘zbek', 'https://feeds.bbci.co.uk/uzbek/rss.xml', 'uz'],
        ['Kun.uz · Telegram', 'https://t.me/s/kunuzofficial', 'uz'],
        ['Podrobno.uz', 'https://podrobno.uz/rss/', 'ru'],
    ]],
    ['slug' => 'aigul', 'group' => 'central-asia', 'name' => 'Айгүл', 'was' => 'Айгуль', 'locale' => 'kk', 'limit' => 1, 'topic' => 'Қазақстан, Қырғызстан, Тәжікстан', 'sources' => [
        ['Tengrinews · қазақша', 'https://kaz.tengrinews.kz/news.rss', 'kk'],
        ['BBC Кыргыз', 'https://feeds.bbci.co.uk/kyrgyz/rss.xml', 'ky'],
        ['Tengrinews', 'https://tengrinews.kz/news.rss', 'ru'],
        ['24.kg', 'https://24.kg/rss/', 'ru'],
        ['Asia-Plus', 'https://asiaplustj.info/ru/rss', 'ru'],
    ]],
    ['slug' => 'sergey', 'group' => 'russia', 'name' => 'Сергей', 'locale' => 'ru', 'limit' => 3, 'topic' => 'Россия', 'sources' => [
        ['BBC Русская служба', 'https://feeds.bbci.co.uk/russian/rss.xml', 'ru'],
        ['DW по-русски', 'https://rss.dw.com/xml/rss-ru-all', 'ru'],
        ['Медуза', 'https://meduza.io/rss/all', 'ru'],
        ['РБК', 'https://rssexport.rbc.ru/rbcnews/news/30/full.rss', 'ru'],
    ]],
    ['slug' => 'oksana', 'group' => 'ukraine', 'name' => 'Оксана', 'locale' => 'uk', 'limit' => 3, 'topic' => 'Україна', 'sources' => [
        ['Українська правда', 'https://www.pravda.com.ua/rss/', 'uk'],
        ['BBC News Україна', 'https://feeds.bbci.co.uk/ukrainian/rss.xml', 'uk'],
        ['Hromadske', 'https://hromadske.ua/rss', 'uk'],
    ]],
    ['slug' => 'dietrich', 'group' => 'europe', 'name' => 'Dietrich', 'locale' => 'de', 'min' => 1, 'limit' => 2, 'topic' => 'Deutschland', 'sources' => [
        ['Tagesschau', 'https://www.tagesschau.de/xml/rss2/', 'de'],
        ['DW', 'https://rss.dw.com/xml/rss-de-all', 'de'],
        ['Spiegel', 'https://www.spiegel.de/schlagzeilen/index.rss', 'de'],
    ]],
    ['slug' => 'jake', 'group' => 'america', 'name' => 'Jake', 'locale' => 'en', 'limit' => 3, 'topic' => 'USA and Canada', 'sources' => [
        ['NPR', 'https://feeds.npr.org/1001/rss.xml', 'en'],
        ['CBC', 'https://www.cbc.ca/webfeed/rss/rss-topstories', 'en'],
        ['AP News', 'https://feedx.net/rss/ap.xml', 'en'],
    ]],
    ['slug' => 'camille', 'group' => 'europe', 'name' => 'Camille', 'locale' => 'fr', 'limit' => 2, 'topic' => 'France et Europe', 'sources' => [
        ['France 24', 'https://www.france24.com/fr/rss', 'fr'],
        ['Le Monde', 'https://www.lemonde.fr/rss/une.xml', 'fr'],
        ['Euronews', 'https://fr.euronews.com/rss', 'fr'],
    ]],
    ['slug' => 'lev', 'group' => 'world', 'name' => 'Leo', 'was' => 'Лев', 'locale' => 'en', 'limit' => 2, 'topic' => 'The world today', 'sources' => [
        ['BBC World', 'https://feeds.bbci.co.uk/news/world/rss.xml', 'en'],
        ['Al Jazeera', 'https://www.aljazeera.com/xml/rss/all.xml', 'en'],
        ['DW', 'https://rss.dw.com/xml/rss-en-all', 'en'],
        ['Euronews', 'https://www.euronews.com/rss', 'en'],
    ]],
    ['slug' => 'einat', 'group' => 'science', 'name' => 'עינת', 'locale' => 'he', 'limit' => 2, 'topic' => 'מדע', 'sources' => [
        ['הידען', 'https://www.hayadan.org.il/feed', 'he'],
        ['זווית', 'https://www.zavit.org.il/feed/', 'he'],
        ['מכון דוידסון', 'https://davidson.weizmann.ac.il/rss.xml', 'he'],
        ['Geektime', 'https://www.geektime.co.il/feed/', 'he'],
    ]],
    ['slug' => 'ron', 'group' => 'science', 'name' => 'Ron', 'locale' => 'en', 'limit' => 2, 'topic' => 'Tech and startups', 'sources' => [
        ['Geektime', 'https://www.geektime.co.il/feed/', 'he'],
        ['Calcalist Tech', 'https://www.calcalistech.com/ctechnews/rss', 'en'],
        ['The Verge', 'https://www.theverge.com/rss/index.xml', 'en'],
    ]],
    ['slug' => 'tali', 'group' => 'interesting', 'name' => 'טלי', 'was' => 'Тали', 'locale' => 'he', 'limit' => 1, 'topic' => 'בריאות: רק ממקורות רשמיים', 'sources' => [
        ['WHO', 'https://www.who.int/rss-feeds/news-english.xml', 'en'],
        ['ScienceDaily', 'https://www.sciencedaily.com/rss/health_medicine.xml', 'en'],
        ['מכון דוידסון', 'https://davidson.weizmann.ac.il/rss.xml', 'he'],
    ]],
    ['slug' => 'vera', 'group' => 'interesting', 'name' => 'Vera', 'was' => 'Вера', 'locale' => 'en', 'limit' => 1, 'topic' => 'Culture and cinema', 'sources' => [
        ['The Guardian · Culture', 'https://www.theguardian.com/culture/rss', 'en'],
        ['Variety', 'https://variety.com/feed/', 'en'],
    ]],
    ['slug' => 'moshe', 'group' => 'sport', 'name' => 'משה', 'locale' => 'he', 'limit' => 1, 'topic' => 'ספורט', 'sources' => [
        ['Sport5', 'https://www.sport5.co.il/RSS.aspx', 'he'],
        ['BBC Sport', 'https://feeds.bbci.co.uk/sport/rss.xml', 'en'],
    ]],
    ['slug' => 'asya', 'group' => 'interesting', 'name' => 'Asya', 'was' => 'Ася', 'locale' => 'en', 'limit' => 1, 'topic' => 'Travel', 'sources' => [
        ['Lonely Planet', 'https://www.lonelyplanet.com/news/feed/atom/', 'en'],
        ['The Guardian · Travel', 'https://www.theguardian.com/travel/rss', 'en'],
    ]],
    // Recipes come from the curator: the "Add by hand" form on the admin page.
    ['slug' => 'faina', 'group' => 'interesting', 'name' => 'Фаина', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Кухня и рецепты', 'sources' => []],
    // Folk jokes, added by the curator by hand (a few hundred at once); jokes of other sites only as links.
    ['slug' => 'izya', 'group' => 'humor', 'name' => 'Изя', 'locale' => 'ru', 'limit' => 2, 'topic' => 'Анекдоты и юмор', 'sources' => []],
    'groups' => [
        'israel' => ['Новости Израиля', 'Главное в Израиле за день: новости из проверенных источников, со ссылками.'],
        'arab-world' => ['Новости арабского мира', 'Арабы Израиля и арабский мир: Panet, Bokra, BBC Arabic и другие, со ссылками.'],
        'central-asia' => ['Средняя Азия', 'Узбекистан, Казахстан, Кыргызстан, Таджикистан: новости и интересное.'],
        'russia' => ['Новости России', 'Россия: новости из проверенных источников, со ссылками.'],
        'ukraine' => ['Новости Украины', 'Україна: новини з перевірених джерел, з посиланнями.'],
        'europe' => ['Европа', 'Германия, Франция и вся Европа: новости на языке оригинала, с переводом.'],
        'america' => ['США и Канада', 'Новости Северной Америки, со ссылками на источники.'],
        'world' => ['В мире', 'Главное в мире за день.'],
        'science' => ['Наука и технологии', 'Открытия, космос, техника и стартапы.'],
        'interesting' => ['Интересно', 'Здоровье, культура и кино, путешествия, кухня: всё, что интересно почитать.'],
        'sport' => ['Спорт', 'Спортивные новости Израиля и мира.'],
        'humor' => ['Юмор и анекдоты', 'Анекдоты и весёлое: для настроения.'],
    ],
    'retired' => [
        // Fewer Russian sources: the authors write in the languages of their countries.
        'https://www.gazeta.uz/ru/rss/',
        'https://kun.uz/ru/news/rss',
        'https://vlast.kz/rss/',
        'https://ru.euronews.com/rss',
        'https://www.afisha.ru/rss/',
        'https://www.kinopoisk.ru/media/rss/',
        'https://www.sports.ru/rss/main.xml',
        'https://habr.com/ru/rss/news/',
        // עינת writes in Hebrew from Hebrew sources.
        'https://www.nasa.gov/news-release/feed/',
        'https://www.sciencealert.com/feed',
        'https://nplus1.ru/rss',
        'https://www.nature.com/nature.rss',
        'https://www.panet.co.il/rss/news',
        'https://www.bokra.net/rss.xml',
    ],
];
