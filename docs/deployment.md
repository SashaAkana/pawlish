# Развёртывание: четыре контейнера, ни одного салона серверов

## Docker (рекомендуется)

Требуются Docker Engine / Desktop, Compose v2 с `up --wait`, свободный порт 18080 и доступ к реестрам для сборки. Создайте `src/.env` из примера. На Linux используйте команды из [README](../README.md). На Windows PowerShell:

```powershell
Copy-Item src/.env.example src/.env
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$rng.Dispose()
$key = 'base64:' + [Convert]::ToBase64String($bytes)
$p = (Resolve-Path src/.env).Path
$s = [IO.File]::ReadAllText($p).Replace("APP_KEY=", "APP_KEY=$key")
[IO.File]::WriteAllText($p, $s, [Text.UTF8Encoding]::new($false))
docker compose build app
docker compose up -d --wait
docker compose exec app php artisan make:filament-user
```

Используйте этот блок только для нового `src/.env`. Храните ключ отдельно от базы, не меняйте его при обновлении. Приложение создаёт SQLite и мигрирует схему при старте (`RUN_MIGRATIONS=1`). Команда `make:filament-user` интерактивно создаёт администратора; публичной регистрации нет. Если используете `db:seed`, задайте действительные `SEED_ADMIN_EMAIL` и `SEED_ADMIN_PASSWORD` (не менее 16 символов); сидер не меняет пароль существующего пользователя.

Проверка: `docker compose ps`, `curl http://127.0.0.1:18080/up`, вход на `/panel`. Логи — `docker compose logs -f app worker scheduler web`.

## HTTPS и доступ из сети

Локальная привязка `127.0.0.1:18080` намеренная. Для удалённого доступа используйте доверенный reverse proxy на хосте, HTTPS и ограничение доступа (при необходимости VPN). В `src/.env` установите `APP_URL=https://crm.example.com`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`. Пример Caddy на хосте:

```caddyfile
crm.example.com {
    reverse_proxy 127.0.0.1:18080
}
```

Если reverse proxy сам работает в Docker, подключите только `web` к общей внешней сети через override-файл; `app`, `worker` и `scheduler` остаются в backend-сети. Внешняя сеть больше не требуется по умолчанию. Не открывайте FPM на 9000 и не публикуйте базу.

## Локальная разработка

В `src/` потребуются PHP 8.4 с `pdo_sqlite`, `mbstring`, `intl`, `dom`, `xmlreader`, `zip`, Composer 2, Node.js 22, npm. Создайте `.env` из шаблона, установите `APP_URL=http://127.0.0.1:8000`, `APP_ENV=local` и при необходимости `APP_DEBUG=true` только локально.

```bash
cd src
cp .env.example .env
# Отредактируйте APP_URL и APP_ENV перед дальнейшими командами.
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan make:filament-user
php artisan serve
```

Для фона отдельно запустите `php artisan queue:work --timeout=60` и `php artisan schedule:work`. На Windows вместо `cp`/`touch` используйте `Copy-Item`/`New-Item`.

## Обновление существующего Mani Webs

Это изменение схемы и Compose. Не заменяйте старую папку/volume вслепую.

1. Отключите входящий трафик, остановите старый scheduler, дождитесь выполнения старой очереди напоминаний и остановите остальные процессы. Старые сериализованные задания отличаются от новых: не переносите незавершённые jobs без проверки. Сохраните старые `.env` и `APP_KEY` отдельно.
2. Сделайте согласованную резервную копию базы. Проверьте реальные имена volume (`docker volume ls`, `docker compose config`). Новый project name обычно создаст новый `sqlite_data`, а не подключит старый.
3. Разверните новую версию в отдельной папке с новым volume, восстановите копию SQLite **до запуска приложения**, сохранив исходный часовой пояс. В `src/.env` перенесите прежний `APP_KEY`, безопасные настройки и секреты, скорректируйте `APP_NAME`, `APP_URL` и cookie.
4. Соберите новый образ, выполните миграции и проверьте историю клиентов, финансовый отчёт, календарь. Миграция пересчитает статистику клиентов и добавит защитные триггеры; существующие пересечения и дубли расходов не удаляются.
5. Проверьте Telegram: ID чата теперь вводится администратором, webhook не привязывает незнакомые чаты. Старые отметки `reminder_sent` могли быть неверными; приложение не сбрасывает их массово, чтобы не рассылать дубли.
6. Переключите reverse proxy после успешной проверки. При откате верните старый код и согласованную копию до миграции, а не просто запускайте старый код на новой базе.

При обычном обновлении Pawlish: `docker compose stop worker scheduler`, резервная копия, `docker compose build app`, `docker compose up -d --force-recreate --wait`. Не используйте `down -v`, если база нужна.

## Бэкапы

SQLite WAL нельзя надёжно копировать по трём файлам во время записи. Используйте SQLite online backup (`.backup`) или остановите все писатели и выполните согласованное копирование. Пример онлайн-копии через sqlite3 внутри app (на Linux):

```bash
mkdir -p backups
docker compose exec -T app sqlite3 /var/www/html/database/database.sqlite ".backup '/tmp/pawlish-backup.sqlite'"
docker compose cp app:/tmp/pawlish-backup.sqlite backups/pawlish-backup.sqlite
docker compose exec -T app rm -f /tmp/pawlish-backup.sqlite
```

Проверяйте `PRAGMA integrity_check` на копии, храните бэкап в защищённом месте, периодически тестируйте восстановление. `APP_KEY` и `.env` сохраняйте отдельно в защищённом хранилище. Для восстановления остановите все сервисы, замените базу согласованной копией, удалите старые sidecar-файлы только в остановленном состоянии и запустите стек. Не восстанавливайте поверх работающего приложения.
