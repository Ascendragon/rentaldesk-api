# Текущее состояние RentalDesk

**Актуализировано 31.10.2026 по подтверждениям пользователя и ветке `feature/rental-order-draft`.** Это снимок, а не мониторинг локального WSL или CI.

## Подтверждённый backend

- PHP 8.5.12, Symfony 7.4, Doctrine ORM/DBAL, PostgreSQL в Docker и WSL.
- `InventoryItem` с DB CHECK; `GET/POST /api/inventory` с JSON DTO и валидацией.
- `Asset`, `AssetStatus`, UNIQUE `inventory_code`, связь ManyToOne к `InventoryItem`, миграция и реальная ORM-persistence.
- `POST /api/inventory/{id}/assets`: сохранение Asset и `AssetResponse`, обработка конфликта `409`.
- `GET /api/inventory/{id}/assets`: корневой массив `AssetResponse[]`, сортировка по `id ASC`, `200 []` для пустой модели и RFC 9457 `404` для отсутствующей.
- `ApiExceptionListener`: RFC 9457 `application/problem+json`, коды `400/404/405/409/422/500`; тестовая ошибка `SECRET-123` не раскрывается в HTTP.
- **Последний подтверждённый полный запуск:** `php bin/phpunit` → **25 тестов, 112 assertions, PASS, 0.445 с**. Позднее отдельно запущен новый `RentalOrderPersistenceTest` → **1 тест, 29 assertions, PASS, 0.128 с**. Общий прогон с этим тестом пока не подтверждён.
- `EXPLAIN (ANALYZE, BUFFERS)` для SELECT Asset по модели: `Bitmap Index Scan` → `Bitmap Heap Scan` → `Sort`; 1 фактическая строка, 0.102 мс. Существующий индекс `(inventory_item_id)`, составной пока не вводится.

## RENTAL-006: заказ (домен и persistence готовы; HTTP API отсутствует)

- `RentalOrder` содержит статус `draft`, время создания и коллекцию `RentalOrderItem`; изменение состава разрешено только для черновика. `getTotalPriceKopecks()` суммирует позиции; сохранённого поля итога пока нет.
- `RentalOrderItem` хранит `InventoryItem`, `quantity`, `startsAt`, `endsAt`, снимок `dailyRateKopecks`. Конструктор принимает дату начала и число календарных дней, вычисляет конец, валидирует входы и регистрирует позицию в заказе через `addItem()`.
- `InventoryItem` получил текущий `dailyRateKopecks` с дефолтом 0 для совместимости со старым кодом. При создании заказа через будущий API тариф должен брать сервер, а не пользователь; нулевой тариф пока не защищён бизнес-правилом, проверка отрицательного тарифа в конструкторе ещё не реализована.
- Миграция `src/Migrations/Version20261030233513.php` создала таблицы заказов/позиций, FK/индексы/DB CHECK, добавила тариф и backfill по названиям моделей. Применена пользователем в dev и test; `APP_ENV=test doctrine:schema:validate` подтвердил mapping и синхронизацию схемы. Backfill по имени хрупок и не обрабатывает неизвестные имена.
- `RentalOrderTest`: **4 unit-теста / 17 assertions, PASS**. `RentalOrderPersistenceTest`: **1 интеграционный тест / 29 assertions, PASS** после `persist`, `flush`, `clear`, повторного чтения; транзакционный rollback и проверка `current_database() = rentaldesk_test`.
- `POST /api/rental-orders` и UI оформления заказа **ещё не реализованы**. Черновик не резервирует `Asset` и не меняет `stock/reserved`.

## Git

- `main`: `433fa85` baseline.
- `feature/asset-model`: `2646333` (head из вывода Git пользователя).
- `feature/api-problem-details`: `3cd4475`, RFC 9457.
- `feature/asset-list-api`: **`2aeb597`**, `feat(asset): add asset listing endpoint and functional tests`, отправлен в `origin`, upstream установлен.
- **`feature/rental-order-draft`: `6deb5f02b8b6018d41c12d4a0b6748e58e6075e1`**, `feat: implement rental order domain and persistence`, запушен на GitHub. Ветка опережает `feature/asset-list-api` на один коммит (проверено через GitHub compare).
- `.env.test` был неотслеживаемым локальным файлом: не добавлять в Git без проверки секретов. Текущее локальное состояние WSL не видно.
- Ветки зависимы; их интеграцию в `main` планировать отдельно, без `push --force`.

## Frontend

- Vue 3/TypeScript/Vite, Graphite & Ember. Реальный каталог и регистрация через POST.
- Пакет v0.4.2 для RFC 9457 подготовлен ранее; локальная сборка пользователя не подтверждена.
- **Пакет v0.5 подготовлен ассистентом:** реальный GET-реестр, состояния загрузки/ошибки/пустого ответа, поиск, фильтр статуса, обновление, повторное GET после успешного POST. Не считать внедрённым до `npm run build` и проверки в браузере.

## Открытые риски / невыполненное

- `InventoryItem.stock/reserved` не синхронизированы с `Asset`; два источника истины запрещены. Нужен ADR по миграции и backfill до изменения учёта.
- Доменные `RentalOrder`/`RentalOrderItem` и их ORM-persistence реализованы; создание заказов через HTTP, подтверждение и конкурентное бронирование ещё не реализованы.
- В новой миграции `down()` удаляет `rental_order` перед `rental_order_item`: порядок нужно исправить новой согласованной правкой до использования rollback. Миграция уже применялась, не менять опубликованную историю миграций необдуманно.
- В изменении `composer.json` неожиданно добавлен `ext-cassandra`: проверить необходимость, поскольку проект не использует Cassandra.
- Не проверены окончательно правила временных зон (в БД `timestamp without time zone`), нулевого тарифа и смены тарифа после создания заказа.
- Нет подтверждённого CI, отдельного HTTP-теста успеха `POST Asset 201`, UI v0.5 на WSL.
- Логи тестового исключения содержат `SECRET-123`: ответ безопасен, но отдельная политика логирования требуется.

## Следующий шаг

1. Проверить мелкие регрессии RENTAL-006: неотрицательный тариф в конструкторе, смысл нулевого тарифа, `composer.json`, поведение миграции `down()` и политике времени.
2. Реализовать `POST /api/rental-orders` как один атомарный сценарий (Request DTO → service → Doctrine → Response DTO), серверный снимок действующего тарифа, функциональные HTTP-тесты и RFC 9457.
3. После подтверждения API контракта обновить Vue frontend: реальная форма заказа; текущую v0.5 по Asset проверить сборкой и в браузере.
4. Продолжить отдельный ADR о переходе `stock/reserved` к `Asset` как источнику истины. До реализации RENTAL-007 не считать физическую доступность работающей.
