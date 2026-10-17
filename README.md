# AnahnuClub

Новая платформа сообщества AnahnuClub вместо Joomla 3 + JomSocial.
Laravel 12 + PostgreSQL + React (Inertia, TypeScript, Tailwind).

## Что уже есть

- Лента, группы, обсуждения, объявления, комментарии и лайки.
- Перенос всего со старого сайта командой `php artisan import:joomla`: пользователи, профили, группы, участники, посты, комментарии, лайки, альбомы, фото, события. Старые пароли подходят (Joomla хранит bcrypt, Laravel проверяет его как есть). Входить можно по почте или по старому логину.
- Интерфейс на русском, иврите, английском и арабском. Для иврита и арабского страница переключается справа налево.
- Автоперевод постов и комментариев на язык интерфейса читателя. Переводы кэшируются в таблице `content_translations`, у каждого поста есть кнопка «Показать оригинал».

## Языки

Строки интерфейса лежат в `lang/{код}.json` (ключ — английский текст), сообщения Laravel — в `lang/{код}/*.php`.

Новый язык:

```bash
php artisan language:add de German Deutsch          # слева направо
php artisan language:add fa Persian فارسی --rtl     # справа налево
```

Команда добавит язык в переключатель и переведёт строки интерфейса машинно, если настроен переводчик. Файл `lang/de.json` потом стоит вычитать.

## Автоперевод

В `.env`:

```
TRANSLATION_DRIVER=claude        # null | claude | deepl
ANTHROPIC_API_KEY=...
ANTHROPIC_TRANSLATION_MODEL=claude-opus-5-5
# или
TRANSLATION_DRIVER=deepl
DEEPL_API_KEY=...
```

С `null` контент показывается как написан. Язык текста сначала угадывается по алфавиту (кириллица, иврит, арабский, латиница), поэтому русский пост русскоязычному читателю не переводится и денег не тратит. Для Claude можно поставить `ANTHROPIC_TRANSLATION_MODEL=claude-haiku-5-5`: заметно дешевле.

Новые посты и комментарии сразу ставятся в очередь на перевод во все языки. На хостинге очередь разбирает планировщик, для этого нужен cron раз в минуту:

```
* * * * * cd /путь/к/сайту && php artisan schedule:run >> /dev/null 2>&1
```

## Тестовый сервер Ubuntu

Виртуалка через [Multipass](https://multipass.run) (Windows, macOS, Linux):

```bash
multipass launch 24.04 --name anahnu --cpus 2 --memory 4G --disk 20G --cloud-init deploy/vm/cloud-init.yaml
multipass transfer old-dump.sql anahnu:/home/ubuntu/old.sql        # дамп старой базы
multipass shell anahnu
git clone https://github.com/nvr1965/anahnuclub.git && cd anahnuclub
sudo JOOMLA_SQL=/home/ubuntu/old.sql bash deploy/vm/provision.sh
```

Чтобы перенести и картинки, распакуйте `public_html` из архива сайта в виртуалку и добавьте `JOOMLA_FILES=/home/ubuntu/public_html`. В конце скрипт напечатает адрес сайта. Повторный запуск после `git pull` обновляет код, сборку и миграции.

## Локальная разработка

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer dev            # сервер, очередь и Vite
php artisan test
```

## SiteGround

SiteGround даёт PHP 8.3, Composer, SSH, cron и базы PostgreSQL в Site Tools. Node на сервере не нужен: React собирается заранее (`npm run build`), и папка `public/build` выкладывается вместе с кодом.

1. Site Tools → PostgreSQL: создать базу и пользователя. Старую MySQL-базу Joomla оставить, импорт читает её напрямую (`JOOMLA_DB_*` в `.env`).
2. Залить код по SSH или git в папку рядом с `public_html`, а `public_html` направить на `public/` (симлинк).
3. `composer install --no-dev -o`, `.env`, `php artisan key:generate`, `php artisan migrate --force --seed`.
4. `php artisan import:joomla --files=/путь/к/старому/public_html`, затем `php artisan storage:link`.
5. Cron из раздела выше.
