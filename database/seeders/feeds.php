<?php

/*
 * The club's authors at the start of the feed filling, and their sources.
 * Read by `php artisan feeds:setup`, which adds what is missing and never changes what an admin edited.
 * The feed addresses were written from memory: the first run of feeds:fetch shows on the admin page
 * which of them answer; fix or switch off the others there.
 */
return [
    ['slug' => 'david', 'name' => 'Давид', 'locale' => 'he', 'limit' => 3, 'topic' => 'Израиль: главные новости', 'sources' => [
        ['Ynet', 'https://www.ynet.co.il/Integration/StoryRss2.xml', 'he'],
        ['Kan News', 'https://www.kan.org.il/rss/', 'he'],
        ['Times of Israel', 'https://www.timesofisrael.com/feed/', 'en'],
        ['Jerusalem Post', 'https://www.jpost.com/rss/rssfeedsheadlines.aspx', 'en'],
    ]],
    ['slug' => 'marina', 'name' => 'Марина', 'locale' => 'ru', 'limit' => 4, 'topic' => 'Израиль по-русски, репатриантам: деньги, права, иврит', 'sources' => [
        ['NEWSru.co.il', 'https://www.newsru.co.il/il/www/news/rss.xml', 'ru'],
        ['Вести', 'https://www.vesty.co.il/rss', 'ru'],
        ['nofinfo.com', 'https://nofinfo.com/rss', 'ru'],
    ]],
    ['slug' => 'ali', 'name' => 'Али', 'locale' => 'ar', 'limit' => 3, 'topic' => 'العرب في إسرائيل والعالم العربي', 'sources' => [
        ['Panet', 'https://www.panet.co.il/rss/news', 'ar'],
        ['Bokra', 'https://www.bokra.net/rss.xml', 'ar'],
        ['BBC Arabic', 'https://feeds.bbci.co.uk/arabic/rss.xml', 'ar'],
        ['Arab News', 'https://www.arabnews.com/rss.xml', 'en'],
    ]],
    ['slug' => 'farik', 'name' => 'Фарик', 'locale' => 'ru', 'limit' => 2, 'topic' => 'Узбекистан: новости и разное интересное', 'sources' => [
        ['Gazeta.uz', 'https://www.gazeta.uz/ru/rss/', 'ru'],
        ['Kun.uz', 'https://kun.uz/ru/news/rss', 'ru'],
        ['Podrobno.uz', 'https://podrobno.uz/rss/', 'ru'],
        ['Daryo', 'https://daryo.uz/feed/', 'uz'],
    ]],
    ['slug' => 'aigul', 'name' => 'Айгуль', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Казахстан, Кыргызстан, Таджикистан', 'sources' => [
        ['Tengrinews', 'https://tengrinews.kz/news.rss', 'ru'],
        ['Vlast.kz', 'https://vlast.kz/rss/', 'ru'],
        ['24.kg', 'https://24.kg/rss/', 'ru'],
        ['Asia-Plus', 'https://asiaplustj.info/ru/rss', 'ru'],
    ]],
    ['slug' => 'sergey', 'name' => 'Сергей', 'locale' => 'ru', 'limit' => 2, 'topic' => 'Россия', 'sources' => [
        ['BBC Русская служба', 'https://feeds.bbci.co.uk/russian/rss.xml', 'ru'],
        ['DW по-русски', 'https://rss.dw.com/xml/rss-ru-all', 'ru'],
        ['Медуза', 'https://meduza.io/rss/all', 'ru'],
        ['РБК', 'https://rssexport.rbc.ru/rbcnews/news/30/full.rss', 'ru'],
    ]],
    ['slug' => 'oksana', 'name' => 'Оксана', 'locale' => 'uk', 'limit' => 2, 'topic' => 'Україна', 'sources' => [
        ['Українська правда', 'https://www.pravda.com.ua/rss/', 'uk'],
        ['BBC News Україна', 'https://feeds.bbci.co.uk/ukrainian/rss.xml', 'uk'],
        ['Hromadske', 'https://hromadske.ua/rss', 'uk'],
    ]],
    ['slug' => 'vitrikh', 'name' => 'Витрих', 'locale' => 'de', 'limit' => 2, 'topic' => 'Deutschland', 'sources' => [
        ['Tagesschau', 'https://www.tagesschau.de/xml/rss2/', 'de'],
        ['DW', 'https://rss.dw.com/xml/rss-de-all', 'de'],
        ['Spiegel', 'https://www.spiegel.de/schlagzeilen/index.rss', 'de'],
    ]],
    ['slug' => 'jake', 'name' => 'Джейк', 'locale' => 'en', 'limit' => 2, 'topic' => 'USA and Canada', 'sources' => [
        ['NPR', 'https://feeds.npr.org/1001/rss.xml', 'en'],
        ['CBC', 'https://www.cbc.ca/webfeed/rss/rss-topstories', 'en'],
        ['AP News', 'https://feedx.net/rss/ap.xml', 'en'],
    ]],
    ['slug' => 'camille', 'name' => 'Камиль', 'locale' => 'fr', 'limit' => 2, 'topic' => 'France et Europe', 'sources' => [
        ['France 24', 'https://www.france24.com/fr/rss', 'fr'],
        ['Le Monde', 'https://www.lemonde.fr/rss/une.xml', 'fr'],
        ['Euronews', 'https://fr.euronews.com/rss', 'fr'],
    ]],
    ['slug' => 'lev', 'name' => 'Лев', 'locale' => 'ru', 'limit' => 2, 'topic' => 'Мир за день', 'sources' => [
        ['Euronews', 'https://ru.euronews.com/rss', 'ru'],
        ['BBC World', 'https://feeds.bbci.co.uk/news/world/rss.xml', 'en'],
        ['DW', 'https://rss.dw.com/xml/rss-en-all', 'en'],
    ]],
    ['slug' => 'einat', 'name' => 'Эйнат', 'locale' => 'he', 'limit' => 2, 'topic' => 'מדע', 'sources' => [
        ['NASA', 'https://www.nasa.gov/news-release/feed/', 'en'],
        ['ScienceAlert', 'https://www.sciencealert.com/feed', 'en'],
        ['N+1', 'https://nplus1.ru/rss', 'ru'],
        ['Nature', 'https://www.nature.com/nature.rss', 'en'],
    ]],
    ['slug' => 'ron', 'name' => 'Рон', 'locale' => 'en', 'limit' => 2, 'topic' => 'Tech and startups', 'sources' => [
        ['Geektime', 'https://www.geektime.co.il/feed/', 'he'],
        ['Calcalist Tech', 'https://www.calcalistech.com/ctechnews/rss', 'en'],
        ['Хабр', 'https://habr.com/ru/rss/news/', 'ru'],
        ['The Verge', 'https://www.theverge.com/rss/index.xml', 'en'],
    ]],
    ['slug' => 'tali', 'name' => 'Тали', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Здоровье: только официальные источники', 'sources' => [
        ['ВОЗ', 'https://www.who.int/rss-feeds/news-english.xml', 'en'],
        ['ScienceDaily', 'https://www.sciencedaily.com/rss/health_medicine.xml', 'en'],
    ]],
    ['slug' => 'vera', 'name' => 'Вера', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Культура и кино', 'sources' => [
        ['Афиша', 'https://www.afisha.ru/rss/', 'ru'],
        ['Кинопоиск', 'https://www.kinopoisk.ru/media/rss/', 'ru'],
    ]],
    ['slug' => 'moshe', 'name' => 'Моше', 'locale' => 'he', 'limit' => 1, 'topic' => 'ספורט', 'sources' => [
        ['Sport5', 'https://www.sport5.co.il/RSS.aspx', 'he'],
        ['Sports.ru', 'https://www.sports.ru/rss/main.xml', 'ru'],
    ]],
    ['slug' => 'asya', 'name' => 'Ася', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Путешествия', 'sources' => [
        ['Lonely Planet', 'https://www.lonelyplanet.com/news/feed/atom/', 'en'],
    ]],
    // Recipes come from the curator: the "Add by hand" form on the admin page.
    ['slug' => 'faina', 'name' => 'Фаина', 'locale' => 'ru', 'limit' => 1, 'topic' => 'Кухня и рецепты', 'sources' => []],
    // Folk jokes, added by the curator by hand (a few hundred at once); jokes of other sites only as links.
    ['slug' => 'izya', 'name' => 'Изя', 'locale' => 'ru', 'limit' => 2, 'topic' => 'Анекдоты и юмор', 'sources' => []],
];
