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
 * 'was_group' moves an author that is still in that group to its 'group'. A source may carry options
 * (['full_text' => true]: its items are posted whole, for jokes and recipes).
 * 'group' is the topic group the author posts in (the list 'groups' below). Each club group has one language, and its
 * author writes in it: "Новости Израиля" in Russian, "חדשות ישראל" in Hebrew. 'dropped' are the author's earlier
 * sources in another language (or given to another author): feeds:setup switches them off for this author.
 * 'retired' are addresses given earlier that do not work: feeds:setup switches them off.
 */
return [
    ['slug' => 'david', 'group' => 'israel-he', 'was_group' => 'israel', 'name' => 'דוד', 'locale' => 'he', 'limit' => 3, 'topic' => 'ישראל: החדשות העיקריות', 'sources' => [
        ['Ynet', 'https://www.ynet.co.il/Integration/StoryRss2.xml', 'he'],
        ['Kan News', 'https://www.kan.org.il/rss/', 'he'],
        ['Israel Hayom', 'https://www.israelhayom.co.il/rss.xml', 'he'],
        ['Haaretz · Telegram', 'https://t.me/s/haaretz_news', 'he'],
    ], 'dropped' => ['https://www.timesofisrael.com/feed/', 'https://www.jpost.com/rss/rssfeedsheadlines.aspx']],
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
    ], 'dropped' => ['https://www.arabnews.com/rss.xml']],
    ['slug' => 'farik', 'group' => 'uzbekistan', 'was_group' => 'central-asia', 'name' => 'Farik', 'was' => 'Фарик', 'locale' => 'uz', 'min' => 1, 'limit' => 2, 'topic' => 'O‘zbekiston: yangiliklar va qiziqarli voqealar', 'sources' => [
        ['Kun.uz', 'https://kun.uz/news/rss', 'uz'],
        ['Daryo', 'https://daryo.uz/feed/', 'uz'],
        ['Gazeta.uz', 'https://www.gazeta.uz/oz/rss/', 'uz'],
        ['BBC O‘zbek', 'https://feeds.bbci.co.uk/uzbek/rss.xml', 'uz'],
        ['Kun.uz · Telegram', 'https://t.me/s/kunuzofficial', 'uz'],
    ], 'dropped' => ['https://podrobno.uz/rss/']],
    ['slug' => 'aigul', 'group' => 'kazakhstan', 'was_group' => 'central-asia', 'name' => 'Айгүл', 'was' => 'Айгуль', 'locale' => 'kk', 'limit' => 1, 'topic' => 'Қазақстан жаңалықтары', 'sources' => [
        ['Tengrinews · қазақша', 'https://kaz.tengrinews.kz/news.rss', 'kk'],
    ], 'dropped' => ['https://feeds.bbci.co.uk/kyrgyz/rss.xml', 'https://tengrinews.kz/news.rss', 'https://24.kg/rss/', 'https://asiaplustj.info/ru/rss']],
    // Central Asia in Russian: the Russian sources Farik and Айгүл had.
    ['slug' => 'gulnara', 'group' => 'central-asia', 'name' => 'Гульнара', 'locale' => 'ru', 'min' => 1, 'limit' => 2, 'topic' => 'Средняя Азия по-русски: Узбекистан, Казахстан, Кыргызстан, Таджикистан', 'sources' => [
        ['Podrobno.uz', 'https://podrobno.uz/rss/', 'ru'],
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
    // Latin America in Spanish: the region's own and the big international Spanish services.
    ['slug' => 'lucia', 'group' => 'latam', 'name' => 'Lucía', 'locale' => 'es', 'min' => 1, 'limit' => 2, 'topic' => 'América Latina: las noticias del día', 'sources' => [
        ['BBC News Mundo', 'https://feeds.bbci.co.uk/mundo/rss.xml', 'es'],
        ['El País América', 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/section/america/portada', 'es'],
        ['Infobae', 'https://www.infobae.com/arc/outboundfeeds/rss/', 'es'],
        ['DW Español', 'https://rss.dw.com/xml/rss-sp-all', 'es'],
        ['France 24 Español', 'https://www.france24.com/es/rss', 'es'],
    ]],
    ['slug' => 'camille', 'group' => 'france', 'was_group' => 'europe', 'name' => 'Camille', 'locale' => 'fr', 'limit' => 2, 'topic' => 'France et Europe', 'sources' => [
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
    ['slug' => 'ron', 'group' => 'it', 'was_group' => 'science', 'name' => 'Ron', 'locale' => 'en', 'min' => 2, 'limit' => 3, 'topic' => 'IT, tech and startups', 'sources' => [
        ['Calcalist Tech', 'https://www.calcalistech.com/ctechnews/rss', 'en'],
        ['The Verge', 'https://www.theverge.com/rss/index.xml', 'en'],
        ['TechCrunch', 'https://techcrunch.com/feed/', 'en'],
        ['Ars Technica', 'https://feeds.arstechnica.com/arstechnica/index', 'en'],
    ], 'dropped' => ['https://www.geektime.co.il/feed/']],
    // Her sources are English, as the group "Interesting": she writes in English.
    ['slug' => 'tali', 'group' => 'interesting', 'name' => 'Tali', 'was' => ['טלי', 'Тали'], 'locale' => 'en', 'limit' => 1, 'topic' => 'Health: from official sources only', 'sources' => [
        ['WHO', 'https://www.who.int/rss-feeds/news-english.xml', 'en'],
        ['ScienceDaily', 'https://www.sciencedaily.com/rss/health_medicine.xml', 'en'],
    ], 'dropped' => ['https://davidson.weizmann.ac.il/rss.xml']],
    ['slug' => 'vera', 'group' => 'interesting', 'name' => 'Vera', 'was' => 'Вера', 'locale' => 'en', 'limit' => 1, 'topic' => 'Culture and cinema', 'sources' => [
        ['The Guardian · Culture', 'https://www.theguardian.com/culture/rss', 'en'],
        ['Variety', 'https://variety.com/feed/', 'en'],
    ]],
    ['slug' => 'moshe', 'group' => 'sport', 'name' => 'משה', 'locale' => 'he', 'limit' => 1, 'topic' => 'ספורט', 'sources' => [
        ['Sport5', 'https://www.sport5.co.il/RSS.aspx', 'he'],
    ], 'dropped' => ['https://feeds.bbci.co.uk/sport/rss.xml']],
    ['slug' => 'asya', 'group' => 'interesting', 'name' => 'Asya', 'was' => 'Ася', 'locale' => 'en', 'limit' => 1, 'topic' => 'Travel', 'sources' => [
        ['Lonely Planet', 'https://www.lonelyplanet.com/news/feed/atom/', 'en'],
        ['The Guardian · Travel', 'https://www.theguardian.com/travel/rss', 'en'],
    ]],
    // Recipes come from the curator: the "Add by hand" form on the admin page.
    // Recipes: the sites below and what the curator adds by hand.
    // Was Фаина: her recipes are English, so is her name.
    ['slug' => 'faina', 'group' => 'cooking', 'was_group' => 'interesting', 'name' => 'Linda', 'was' => 'Фаина', 'locale' => 'en', 'min' => 1, 'limit' => 2, 'topic' => 'Cooking and recipes', 'sources' => [
        ['Bon Appétit', 'https://www.bonappetit.com/feed/rss', 'en'],
    ], 'dropped' => ['https://www.foody.co.il/feed']],
    ['slug' => 'mia', 'group' => 'beauty', 'name' => 'Mia', 'locale' => 'en', 'min' => 1, 'limit' => 2, 'topic' => 'Beauty and self-care', 'sources' => [
        ['Allure', 'https://www.allure.com/feed/rss', 'en'],
        ['Glamour', 'https://www.glamour.com/feed/rss', 'en'],
    ]],
    // Folk jokes, added by the curator by hand (a few hundred at once); jokes of other sites only as links.
    // Jokes, whole: anekdot.ru's jokes of the day, YouTube channels the curator adds (Odessa jokes), and by hand.
    ['slug' => 'izya', 'group' => 'humor', 'name' => 'Изя', 'locale' => 'ru', 'min' => 2, 'limit' => 3, 'topic' => 'Анекдоты и юмор', 'sources' => [
        ['Анекдоты дня', 'https://www.anekdot.ru/rss/export_j.xml', 'ru', ['full_text' => true]],
    ]],
    /*
     * The club's groups, one language each: 'name' and 'description' in it, 'locale', the picture 'avatar'
     * (resources/club-groups/<avatar>.png). 'was' is the group's earlier name: a group that still has it is renamed
     * (and gets the new description and language). Groups of one 'family' are one topic in several languages:
     * an author's earlier posts in the family move to the group of their language.
     */
    'groups' => [
        'israel' => ['name' => 'Новости Израиля', 'description' => 'Главное в Израиле за день: новости из проверенных источников, со ссылками.', 'locale' => 'ru', 'family' => 'israel', 'avatar' => 'israel'],
        'israel-he' => ['name' => 'חדשות ישראל', 'description' => 'החדשות העיקריות בישראל מהיום, ממקורות בדוקים ועם קישורים.', 'locale' => 'he', 'family' => 'israel', 'avatar' => 'israel-he'],
        'arab-world' => ['name' => 'أخبار العالم العربي', 'was' => 'Новости арабского мира', 'description' => 'العرب في إسرائيل والعالم العربي: بانيت، بكرا، بي بي سي عربي وغيرها، مع الروابط.', 'locale' => 'ar', 'avatar' => 'arab-world'],
        'central-asia' => ['name' => 'Средняя Азия', 'description' => 'Узбекистан, Казахстан, Кыргызстан, Таджикистан: новости по-русски, со ссылками.', 'locale' => 'ru', 'family' => 'central-asia', 'avatar' => 'central-asia'],
        'uzbekistan' => ['name' => 'O‘zbekiston yangiliklari', 'description' => 'O‘zbekistondagi asosiy yangiliklar va qiziqarli voqealar, havolalar bilan.', 'locale' => 'uz', 'family' => 'central-asia', 'avatar' => 'uzbekistan'],
        'kazakhstan' => ['name' => 'Қазақстан жаңалықтары', 'description' => 'Қазақстандағы басты жаңалықтар, тексерілген дереккөздерден, сілтемелерімен.', 'locale' => 'kk', 'family' => 'central-asia', 'avatar' => 'kazakhstan'],
        'russia' => ['name' => 'Новости России', 'description' => 'Россия: новости из проверенных источников, со ссылками.', 'locale' => 'ru', 'avatar' => 'russia'],
        'ukraine' => ['name' => 'Новини України', 'was' => 'Новости Украины', 'description' => 'Україна: новини з перевірених джерел, з посиланнями.', 'locale' => 'uk', 'avatar' => 'ukraine'],
        'europe' => ['name' => 'Nachrichten aus Deutschland', 'was' => 'Европа', 'description' => 'Deutschland und Europa: Nachrichten aus geprüften Quellen, mit Links.', 'locale' => 'de', 'family' => 'europe', 'avatar' => 'germany'],
        'france' => ['name' => 'France et Europe', 'description' => 'L’actualité de la France et de l’Europe, de sources vérifiées, avec les liens.', 'locale' => 'fr', 'family' => 'europe', 'avatar' => 'france'],
        'latam' => ['name' => 'Noticias de América Latina', 'description' => 'Lo principal de México, Centro y Sudamérica y el Caribe, de fuentes verificadas y con enlaces.', 'locale' => 'es', 'avatar' => 'latam'],
        'america' => ['name' => 'USA & Canada', 'was' => 'США и Канада', 'description' => 'North American news from checked sources, with links.', 'locale' => 'en', 'avatar' => 'america'],
        'world' => ['name' => 'World News', 'was' => 'В мире', 'description' => 'The main world news of the day.', 'locale' => 'en', 'avatar' => 'world'],
        'science' => ['name' => 'מדע', 'was' => 'Наука и технологии', 'description' => 'תגליות, חלל, טבע ורפואה: מדע בעברית ממקורות בדוקים.', 'locale' => 'he', 'avatar' => 'science'],
        'interesting' => ['name' => 'Interesting', 'was' => 'Интересно', 'description' => 'Health, culture and cinema, travel: things worth reading.', 'locale' => 'en', 'avatar' => 'interesting'],
        'sport' => ['name' => 'ספורט', 'was' => 'Спорт', 'description' => 'חדשות הספורט בישראל ובעולם.', 'locale' => 'he', 'avatar' => 'sport'],
        'humor' => ['name' => 'Юмор и анекдоты', 'description' => 'Анекдоты и весёлое: для настроения.', 'locale' => 'ru', 'avatar' => 'humor'],
        'beauty' => ['name' => 'Beauty', 'was' => 'Красота', 'description' => 'Skin care, make-up, fashion and self-care.', 'locale' => 'en', 'avatar' => 'beauty'],
        'cooking' => ['name' => 'Cooking', 'was' => 'Кулинария', 'description' => 'Recipes and everything about food.', 'locale' => 'en', 'avatar' => 'cooking'],
        'it' => ['name' => 'Tech & IT', 'was' => 'IT и технологии', 'description' => 'IT news, gadgets, startups and programming.', 'locale' => 'en', 'avatar' => 'it'],
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
