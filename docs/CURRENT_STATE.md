# Текущее состояние RentalDesk

**Актуализировано 30.10.2026 по подтверждениям пользователя и ветке `feature/asset-list-api`.** Это снимок, а не мониторинг локального WSL или CI.

## Подтверждённый backend

- PHP 8.5.12, Symfony 7.4, Doctrine ORM/DBAL, PostgreSQL в Docker и WSL.
- `InventoryItem` с DB CHECK; `GET/POST /api/inventory` с JSON DTO и валидацией.
- `Asset`, `AssetStatus`, UNIQUE `inventory_code`, связь ManyToOne к `InventoryItem`, миграция и реальная ORM-persistence.
- `POST /api/inventory/{id}/assets`: сохранение Asset и `AssetResponse`, обработка конфликта `409`.
- `GET /api/inventory/{id}/assets`: корневой массив `AssetResponse[]`, сортировка по `id ASC`, `200 []` для пустой модели и RFC 9457 `404` для отсутствующей.
- `ApiExceptionListener`: RFC 9457 `application/problem+json`, коды `400/404/405/409/422/500`; тестовая ошибка `SECRET-123` не раскрывается в HTTP.
- **Последний полный запуск:** `php bin/phpunit` → **21 тест, 96 assertions, PASS, 0.433 с**; `AssetListApiTest` → 3 теста, 19 assertions. `rentaldesk_test` проверяется в тестах GET.
- `EXPLAIN (ANALYZE, BUFFERS)` для SELECT Asset по модели: `Bitmap Index Scan` → `Bitmap Heap Scan` → `Sort`; 1 фактическая строка, 0.102 мс. Существующий индекс `(inventory_item_id)`, составной пока не вводится.

## Git

- `main`: `433fa85` baseline.
- `feature/asset-model`: `2646333` (head из вывода Git пользователя).
- `feature/api-problem-details`: `3cd4475`, RFC 9457.
- `feature/asset-list-api`: **`2aeb597`**, `feat(asset): add asset listing endpoint and functional tests`, отправлен в `origin`, upstream установлен.
- `.env.test` остался неотслеживаемым локальным файлом: не добавлять в Git без проверки секретов.
- Ветки зависимы; их интеграцию в `main` планировать отдельно, без `push --force`.

## Frontend

- Vue 3/TypeScript/Vite, Graphite & Ember. Реальный каталог и регистрация через POST.
- Пакет v0.4.2 для RFC 9457 подготовлен ранее; локальная сборка пользователя не подтверждена.
- **Пакет v0.5 подготовлен ассистентом:** реальный GET-реестр, состояния загрузки/ошибки/пустого ответа, поиск, фильтр статуса, обновление, повторное GET после успешного POST. Не считать внедрённым до `npm run build` и проверки в браузере.

## Открытые риски / невыполненное

- `InventoryItem.stock/reserved` не синхронизированы с `Asset`; два источника истины запрещены. Нужен ADR по миграции и backfill до изменения учёта.
- `RentalOrder` и `OrderItem` остаются незавершёнными и изолированными; бронирование/конкурентность/заказы ещё не реализованы.
- Нет подтверждённого CI, отдельного HTTP-теста успеха `POST Asset 201`, UI v0.5 на WSL.
- Логи тестового исключения содержат `SECRET-123`: ответ безопасен, но отдельная политика логирования требуется.

## Следующий шаг

1. Применить и собрать frontend v0.5, проверить в браузере список после обновления страницы, `200 []`, ошибку `404`, POST→GET.
2. Освоить следующий Git-шаг: синхронизацию локальной и удалённой ветки; интеграция зависимых веток в `main` отдельным решением.
3. Перед RENTAL-006 принять стратегию источника истины для количественного и поэкземплярного учёта.
