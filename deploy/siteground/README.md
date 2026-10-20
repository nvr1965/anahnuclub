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

## Видеозвонки

Звонки в сообщениях работают сразу: браузеры соединяются напрямую, сайт только передаёт
им данные для соединения. В некоторых сетях (часть мобильных операторов, офисные сети)
прямое соединение невозможно. Для них нужен TURN-сервер, который передаёт видео через себя.
Подключён Cloudflare TURN (dash.cloudflare.com → Realtime → TURN Server), в `.env`:

```
CLOUDFLARE_TURN_KEY_ID=...
CLOUDFLARE_TURN_API_TOKEN=...
```

Сайт сам получает у Cloudflare временный логин. Проверка:
`php artisan tinker --execute="echo json_encode(App\Http\Controllers\CallController::iceServers());"`
должна показать `turn.cloudflare.com`. Другой TURN-сервер вписывается строками `TURN_URLS`,
`TURN_USERNAME`, `TURN_CREDENTIAL`.

## Переход на anahnu.club

Новый сайт встаёт на место Joomla в `anahnu.club`. Старый сайт не удаляется: его файлы
переименовываются в `~/www/anahnu.club/public_html_joomla`, база Joomla не трогается,
так что вернуть всё назад можно одной командой.

### Заранее, в Site Tools сайта anahnu.club

1. **Security → Backups**: сделать резервную копию (Create Backup).
2. **Devs → PHP Manager**: для `anahnu.club` поставить PHP 8.3, в **PHP Variables**
   `upload_max_filesize` и `post_max_size` = `220M`. Joomla на PHP 8.3 может не открыться,
   поэтому этот шаг делается прямо перед переходом.
3. **Security → SSL Manager**: у `anahnu.club` должен быть сертификат (обычно уже есть).
4. В консолях Google и Facebook добавить адреса входа
   `https://anahnu.club/auth/google/callback` и `https://anahnu.club/auth/facebook/callback`.

### Переход

```bash
cd ~/www/test.anahnu.club/app && git pull && bash deploy/siteground/go-live.sh
```

Скрипт:

1. убирает Joomla в `public_html_joomla` и ставит заглушку «Сайт обновляется»;
2. переносит код в `~/www/anahnu.club/app` и меняет адрес в `.env` на `https://anahnu.club`;
3. очищает базу и загруженные файлы: всё, что делалось на тестовом сайте (участники,
   посты, сообщения, фото), на рабочий не попадает. Остаются только настройки из админки:
   языки, переводчики с ключами, страницы сайта и исправленные тексты интерфейса
   (копия настроек сохраняется в `storage/app/reset-kept-....json`);
4. заново переносит со старого сайта участников, группы, посты, фото и видео;
5. ставит сайт в `anahnu.club` и делает так, что `test.anahnu.club` переадресует на
   `anahnu.club`.

Старые ссылки на картинки Joomla (`/images/...`) в перенесённых постах продолжают работать.

### После перехода

- **Devs → Cron Jobs**: заменить старую задачу на ту, что скрипт напечатал в конце
  (с путём `~/www/anahnu.club/app`).
- Обновление теперь так:
  `cd ~/www/anahnu.club/app && git pull && bash deploy/siteground/deploy.sh`

### Если надо вернуть старый сайт

```bash
bash ~/www/anahnu.club/app/deploy/siteground/go-live.sh --back
```

Joomla снова откроется в `anahnu.club` (если PHP 8.3 ей не подходит, вернуть прежнюю
версию в PHP Manager), cron новой версии надо выключить.
