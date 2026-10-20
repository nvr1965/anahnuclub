# Новый сайт на SiteGround: test.anahnu.club

Новый клуб ставится рядом со старым сайтом, на поддомен `test.anahnu.club`.
Старый Joomla в `anahnu.club` остаётся как есть. Код лежит в `~/www/test.anahnu.club/app`
(из интернета туда не попасть), а в `public_html` поддомена скрипт кладёт только то, что
нужно браузеру.

Поддомен, а не папка `anahnu.club/test`: сайт рассчитан на работу с корня адреса,
и в письмах будет нормальный домен вместо IP (письмо с IP SiteGround уже резал как спам).

## 1. Один раз в Site Tools

1. **Domain → Subdomains**: создать `test` для `anahnu.club`.
2. **Security → SSL Manager**: выпустить Let's Encrypt для `test.anahnu.club`.
3. **Devs → PHP Manager**: для `test.anahnu.club` выбрать PHP 8.3.
   В **PHP Variables** поставить `upload_max_filesize` и `post_max_size` = `220M` (видео до 200 МБ).
4. **PostgreSQL**: создать базу и пользователя. Имя, пользователь и пароль понадобятся в шаге 3.
5. **Devs → SSH Keys Manager**: добавить свой ключ. Там же, в «SSH Credentials», видны
   адрес, пользователь и порт (обычно 18765).

## 2. Скачать код

```bash
ssh -p 18765 ПОЛЬЗОВАТЕЛЬ@ХОСТ
cd ~/www/test.anahnu.club
git clone https://github.com/nvr1965/anahnuclub.git app
cd app
bash deploy/siteground/deploy.sh
```

Первый запуск только проверит PHP, создаст `.env` и остановится.

## 3. Заполнить `.env`

```bash
nano .env
```

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: база PostgreSQL из шага 1.
- `MAIL_PASSWORD`: пароль ящика `noreply@anahnu.club`.
- `JOOMLA_DB_*`: база старого сайта, чтобы перенести участников и посты.
  Всё есть в `~/www/anahnu.club/public_html/configuration.php`:
  `$host` → `JOOMLA_DB_HOST` (обычно `localhost`), `$db` → `JOOMLA_DB_DATABASE`,
  `$user` → `JOOMLA_DB_USERNAME`, `$password` → `JOOMLA_DB_PASSWORD`, `$dbprefix` → `JOOMLA_DB_PREFIX`.
- Ключи Google/Facebook, если уже есть (см. инструкцию про почту и вход через соцсети).

Сохранить: Ctrl+O, Enter, Ctrl+X.

## 4. Установить и перенести данные

```bash
IMPORT=1 bash deploy/siteground/deploy.sh
```

Скрипт поставит зависимости, возьмёт собранный фронтенд с GitHub (сборка на самом хостинге упирается в лимиты памяти,
сборка делается там автоматически после каждого обновления кода), создаст таблицы,
перенесёт участников, группы, посты, фото и видео со старого сайта
(файлы берутся из `~/www/anahnu.club/public_html`) и разложит `public_html`.
Повторный запуск с `IMPORT=1` безопасен: уже перенесённое не дублируется.

## 5. Cron

**Devs → Cron Jobs**, каждую минуту (команду скрипт печатает в конце, с точным путём):

```
cd /home/ПОЛЬЗОВАТЕЛЬ/www/test.anahnu.club/app && php artisan schedule:run >> /dev/null 2>&1
```

Без cron не уходят письма и не работают переводы и карточки ссылок.
Если меньше 1 минуты Site Tools не позволяет, поставить самый частый вариант.

## Обновление

```bash
cd ~/www/test.anahnu.club/app && git pull && bash deploy/siteground/deploy.sh
```

Если скрипт пишет «waiting for GitHub to build», он ждёт сборку фронтенда для нового
кода (обычно пара минут) и продолжит сам.

## Если что-то не так

- **PHP is too old / PHP is missing ...**: в SSH другая версия PHP, чем у сайта. Запустить
  с `PHP=php83 bash deploy/siteground/deploy.sh` или сменить версию в PHP Manager.
- **Ошибка 500 в браузере**: последние строки лога
  `tail -50 storage/logs/laravel.log`.
- Скрипт не трогает папку, где лежит Joomla (`configuration.php`), так что старый сайт
  случайно не перезапишется.
