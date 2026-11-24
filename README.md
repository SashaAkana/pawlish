# Pawlish

*Хороший мастер знает цену своему времени. Кот — цену свободному креслу.*

![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4?style=flat-square&logo=php&logoColor=white)
![Laravel 12](https://img.shields.io/badge/Laravel-12-ff2d20?style=flat-square&logo=laravel&logoColor=white)
![Filament 3](https://img.shields.io/badge/Filament-3-f59e0b?style=flat-square)
![SQLite](https://img.shields.io/badge/SQLite-WAL-003b57?style=flat-square&logo=sqlite)
![MPL 2.0](https://img.shields.io/badge/license-MPL--2.0-64748b?style=flat-square)

<img src="src/public/android-chrome-192x192.png" width="88" alt="Pawlish" align="right">

**Pawlish** — небольшая self-hosted CRM для мастера маникюра и других специалистов сферы услуг. Записи, клиенты, расходы и отчёты в одном месте. Без ежемесячной подписки и таблицы Excel, которую страшно открыть в понедельник.

Название — от *paw* (лапка) и *polish* (лак, полировка). Интерфейс на русском языке.

## Что умеет

- Календарь с рабочим графиком, длительностью услуг и защитой от двойной записи.
- Карточки клиентов, история визитов, услуги и оплата.
- Доходы, расходы, ежемесячные платежи и ориентировочный налог.
- Экспорт финансового отчёта в CSV и PDF.
- Напоминания мастеру в личный чат Telegram (по желанию).

Один календарь, одна команда, одна база. Если на одно время записались двое, виноват не кот.

## Быстрый старт

Понадобится Docker с Compose v2. Выполните из корня проекта в Bash:

```bash
cp src/.env.example src/.env
# Создаём постоянный ключ приложения. Не меняйте его при обновлении.
key=$(openssl rand -base64 32)
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$key|" src/.env
docker compose build app
docker compose up -d --wait
docker compose exec app php artisan make:filament-user
```

Откройте [http://127.0.0.1:18080/panel](http://127.0.0.1:18080/panel), войдите и настройте рабочий день, валюту и часовой пояс **до первой записи**. По умолчанию приложение слушает только loopback.

Для PowerShell, HTTPS, обновления существующего Mani Webs и Telegram — [инструкция по развёртыванию](docs/deployment.md).

## Под капотом, без запаха ацетона

| Документ | О чём |
|---|---|
| [Архитектура](docs/architecture.md) | Почему небольшой CRM достаточно SQLite и одного календаря. |
| [Работа с записями и деньгами](docs/workflows.md) | Когда визит становится доходом и почему аренда не должна размножаться. |
| [Развёртывание](docs/deployment.md) | Docker, локальная разработка, обновления, бэкапы. |
| [Безопасность](docs/security.md) | Секреты, Telegram, ограничения и границы доверия. |
| [Тесты](docs/testing.md) | Проверки PHP, SQLite и контейнеров. |
| [CHANGELOG](CHANGELOG.md) | Что поменялось после Mani Webs. |

## Важно

Это панель для одного мастера или одной доверенной команды с общим доступом. Не SaaS, не публичная онлайн-запись и не бухгалтерская система. Налог рассчитывается ориентировочно по введённой ставке, не заменяет фискальные документы.

## Лицензия

Исходный код Pawlish распространяется по [Mozilla Public License 2.0 (MPL-2.0)](LICENSE). При распространении изменённых файлов, подпадающих под MPL, их исходный код должен оставаться доступным на условиях MPL 2.0. Сторонние зависимости распространяются по своим лицензиям.

Copyright (c) 2026 Alexander Motorin (Crimson). См. [NOTICE](NOTICE).

*No double bookings. No double coats.*
