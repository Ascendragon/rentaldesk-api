# Контекст RentalDesk для следующего диалога

**Актуализировано 30.10.2026.** Сначала читать `PRODUCT_VISION.md`, `ARCHITECTURE.md`, `ENGINEERING_PROCESS.md`, `CURRENT_STATE.md`, `API_CONTRACTS.md`, `ROADMAP.md` и ADR. Git-коммиты и актуальные тесты приоритетнее этого снимка.

## Цель и разделение ответственности

RentalDesk — инженерный проект проката строительного инструмента. Пользователь осваивает зрелую backend-разработку, архитектуру, PostgreSQL, тестирование и Git с постепенным переносом ответственности. **Пользователь самостоятельно пишет PHP и защищает решения**, ассистент ставит бизнес-тикеты, проводит ревью, постепенно обучает Git, **создаёт Vue/TS frontend и поддерживает документацию**. Не подменять его backend-код готовым решением без запроса. Учить переносимому мышлению Senior Engineer, а не только Symfony.

## Стек

PHP 8.5.12, Symfony 7.4, Doctrine ORM/DBAL, PostgreSQL/Docker, WSL; Vue 3, TypeScript, Vite, Graphite & Ember. Модульный монолит и REST. Без технологического карго-культа.

## Реально сделано

- `InventoryItem`: CHECK для остатков; GET/POST каталога.
- `Asset`: физическая единица, статус `active/maintenance/retired`, уникальный `inventoryCode`, цена в копейках, `purchasedAt`, ORM migration/persistence.
- `POST /api/inventory/{id}/assets`: регистрация и `201 AssetResponse`; 409 при конфликте кода, 404, 422.
- `GET /api/inventory/{id}/assets` (**RENTAL-005.6**): 200 root array sorted id ASC, 200 [], 404 RFC 9457. Тест `AssetListApiTest`: 3/19.
- `ApiExceptionListener` RFC 9457 для 400/404/405/409/422/500; отдельные 6/48 тесты.
- **Полный PHPUnit: 21 тест / 96 assertions, PASS**, подтверждён пользователем 30.10.2026.
- PostgreSQL `EXPLAIN ANALYZE`: Bitmap Index Scan → Bitmap Heap Scan → Sort, 1 строка, 0.102 мс; уже есть индекс по `inventory_item_id`, дополнительный индекс пока не нужен.
- `feature/asset-list-api` → **`2aeb597`**, push на GitHub выполнен, upstream установлен. Ветку пока не сливать напрямую в main без учёта stacked зависимостей.

## Frontend / Docs

У пользователя отдельный Vue frontend; ассистент подготовил **v0.5** с реальным GET-реестром, поиском, фильтрами, загрузкой/ошибками/пустым состоянием, повторным GET после POST. **Сборка пользователя и браузерная проверка не подтверждены**. В локальной среде ассистента npm-registry недоступен; обещать собранный продукт нельзя. Backend-документы актуализированы после RENTAL-005.6.

## Главный риск и ближайшее направление

`InventoryItem.stock/reserved` пока не связаны с числом `Asset`; нельзя молча считать одно производным от другого. Стратегия миграции/источника истины требует ADR. `RentalOrder`/`OrderItem` незавершены и изолированы. После подтверждения Vue v0.5 переходить к осмысленной продуктовой фиче заказа RENTAL-006 и её проектированию. Git передавать постепенно: branch/status, stage/diff, commit/push уже разобраны; следующий шаг fetch/pull и PR.
