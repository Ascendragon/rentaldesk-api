# Текущее состояние RentalDesk

**Актуализировано 31.10.2026 по подтверждениям пользователя и ветке `feature/rental-order-draft`.** Это снимок, а не мониторинг локального WSL или CI.

## Подтверждённый backend

- PHP 8.5.12, Symfony 7.4, Doctrine ORM/DBAL, PostgreSQL в Docker и WSL.
- `InventoryItem` с DB CHECK; `GET/POST /api/inventory` с JSON DTO и валидацией.
- `Asset`, `AssetStatus`, UNIQUE `inventory_code`, связь ManyToOne к `InventoryItem`, миграция и реальная ORM-persistence.
- `POST /api/inventory/{id}/assets`: сохранение Asset и `AssetResponse`, обработка конфликта `409`.
- `GET /api/inventory/{id}/assets`: корневой массив `AssetResponse[]`, сортировка по `id ASC`, `200 []` для пустой модели и RFC 9457 `404` для отсутствующей.
- `ApiExceptionListener`: RFC 9457 `application/problem+json`, коды `400/404/405/409/422/500`; тестовая ошибка `SECRET-123` не раскрывается в HTTP.
- **Последний подтверждённый полный прогон до UTC-миграции:** **27 тестов / 143 assertions, PASS** (скриншот пользователя). После миграции отдельно подтверждён `testPreservesRentalInstantsAfterDatabaseReload`: **1 тест / 12 assertions, PASS**. Полный регрессионный прогон после UTC-миграции пока не подтверждён.
- `EXPLAIN (ANALYZE, BUFFERS)` для SELECT Asset по модели: `Bitmap Index Scan` → `Bitmap Heap Scan` → `Sort`; 1 фактическая строка, 0.102 мс. Существующий индекс `(inventory_item_id)`, составной пока не вводится.

## RENTAL-006: заказ (домен и persistence готовы; HTTP API отсутствует)

- `RentalOrder` — корень агрегата. `addItem(InventoryItem, quantity, startsAt, rentalDays)` проверяет статус `draft`, берёт действующий тариф из `InventoryItem`, требует тариф > 0, создаёт `RentalOrderItem` и добавляет его в коллекцию. Вызывающий код не передаёт сумму позиции.
- `RentalOrderItem` сохраняет snapshot `dailyRateKopecks`, количество и точные моменты `startsAt`/`endsAt`. Входное время переводится в UTC; **1 сутки = 24 часа**. Конструктор более не регистрирует себя в заказе. Его `@internal` сейчас оформлен обычным комментарием и нуждается в PHPDoc `/** ... */`.
- `InventoryItem` проверяет неотрицательный `dailyRateKopecks`, значение 0 допускается для ещё не настроенного тарифа, но такая модель не может войти в заказ.
- `rental_order.created_at`, `rental_order_item.starts_at` и `ends_at` переведены в PostgreSQL `TIMESTAMP WITH TIME ZONE` миграцией `src/Migrations/Version20261031140429.php` (старые значения при миграции интерпретируются как UTC). Doctrine использует `DATETIMETZ_IMMUTABLE`.
- Есть unit-тесты для цены, инвариантов, UTC и DST, а также два интеграционных теста ORM; `testPreservesRentalInstantsAfterDatabaseReload` прошёл **1/12**. Чтобы тест времени выявлял случайный возврат к `timestamp without time zone`, стоит дополнительно проверить тип колонок через `information_schema`.
- В существующей Inventory API пока **нет** `dailyRateKopecks` во входном/выходном DTO и сервисе; новые модели создаются с тарифом 0. Добавить тариф в API — блокер для `POST /api/rental-orders`.
- `ApiExceptionListener` пока не сопоставляет предметные исключения заказа с корректными 4xx: обычные `DomainException` и `InvalidArgumentException` превратятся в 500; необходимо исправить при разработке HTTP API.
- Создание заказа через HTTP, подтверждение, блокировки, аллокации конкретных `Asset` и Vue-форма **ещё не реализованы**. Черновик не резервирует оборудование. Повторные позиции допускаются на этапе draft; доступность проверяется при будущем подтверждении.

## Git

- `main`: `433fa85` baseline.
- `feature/asset-model`: `2646333` (head из вывода Git пользователя).
- `feature/api-problem-details`: `3cd4475`, RFC 9457.
- `feature/asset-list-api`: **`2aeb597`**, `feat(asset): add asset listing endpoint and functional tests`, отправлен в `origin`, upstream установлен.
- **`feature/rental-order-draft`: `e22bc226a49ea5f951fa7eebd49ed7bb9278d50c`**, UTC-модель и тесты; ранее `cab0d51` — рефакторинг агрегата. Ветка опубликована, история включает прежние зависимые feature-ветки.
- `.env.test` был неотслеживаемым локальным файлом: не добавлять в Git без проверки секретов. Текущее локальное состояние WSL не видно.
- Ветки зависимы; их интеграцию в `main` планировать отдельно, без `push --force`.

## Frontend

- Vue 3/TypeScript/Vite, Graphite & Ember. Реальный каталог и регистрация через POST.
- Пакет v0.4.2 для RFC 9457 подготовлен ранее; локальная сборка пользователя не подтверждена.
- **Пакет v0.5 подготовлен ассистентом:** реальный GET-реестр, состояния загрузки/ошибки/пустого ответа, поиск, фильтр статуса, обновление, повторное GET после успешного POST. Не считать внедрённым до `npm run build` и проверки в браузере.

## Открытые риски / невыполненное

- `InventoryItem.stock/reserved` не синхронизированы с `Asset`; два источника истины запрещены. Нужен ADR по миграции и backfill до изменения учёта.
- Доменные `RentalOrder`/`RentalOrderItem` и их ORM-persistence реализованы; создание заказов через HTTP, подтверждение и конкурентное бронирование ещё не реализованы.
- Случайный `ext-cassandra` **удалён** из `composer.json`; `composer.lock` синхронизирован в GitHub. Проверка установки зависимостей в чистом CI ещё нужна.
- `TIMESTAMPTZ` уже внедрён, но API должен явно форматировать даты в UTC; следовало бы зафиксировать timezone PostgreSQL-сессии. В старой dev-базе данные могли сдвинуться, если до миграции сохранялись в местной зоне.
- Нулевой тариф запрещён при добавлении в заказ, но прямой публичный конструктор `RentalOrderItem` пока допускает 0; `rental_order.status` всё ещё без SQL CHECK.
- В миграции `Version20261030233513.php` в `down()` нарушен порядок удаления связанных таблиц; не переписывать историю применённых миграций без плана.
- Нет подтверждённого CI, отдельного HTTP-теста успеха `POST Asset 201`, UI v0.5 на WSL.
- Логи тестового исключения содержат `SECRET-123`: ответ безопасен, но отдельная политика логирования требуется.

## Следующий шаг

1. Укрепить существующий тест времени: проверить фактические SQL-типы `starts_at` и `ends_at`; исправить `@internal` на PHPDoc.
2. Добавить `dailyRateKopecks` в `POST/GET /api/inventory` и тесты без нарушения старого контракта.
3. Спроектировать обработку предметных ошибок и реализовать `POST /api/rental-orders` как атомарную операцию с RFC 9457.
4. Подготовить Vue-форму после согласования работающего HTTP-контракта.
5. После полного прогона — интеграция цепочки веток в `main` и минимальный CI. До RENTAL-007 отдельно решить ADR-0002/ADR-0005.
