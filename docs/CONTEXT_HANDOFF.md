# Контекст RentalDesk для следующего диалога

**Обновлено 30.10.2026.** Первым делом читать `docs/README.md`, `PRODUCT_VISION.md`, `ARCHITECTURE.md`, `ENGINEERING_PROCESS.md`, `CURRENT_STATE.md`, `API_CONTRACTS.md`, `ROADMAP.md` и ADR. Файлы Git-репозитория имеют приоритет над этим снимком.

## Зачем мы делаем проект

RentalDesk — инженерный учебно-практический продукт проката инструмента, а не самоцельная «уникальная SaaS-идея». Цель пользователя — самостоятельно освоить backend уровня настоящей PHP/Symfony-коммерческой разработки. Ассистент ставит цельный тикет, обсуждает архитектуру, ревьюит, **сам отвечает за документацию и Vue/TS frontend**, но **не пишет backend вместо пользователя**, если тот прямо не попросил.

## Технологии и принципы

PHP 8.5, Symfony 7.4, Doctrine ORM/DBAL, PostgreSQL в Docker, WSL; Vue 3/TypeScript/Vite, Graphite & Ember. REST API, модульный монолит. Не добавлять Kafka/K8s/RabbitMQ и прочие технологии без задачи. API DTO/Validator → application service → Entity → ORM/DBAL/PostgreSQL. Деньги в целых копейках; бизнес-инварианты в домене и БД; конкурентные операции должны быть атомарными.

## Подтверждённое состояние

- `InventoryItem` + миграция + DB CHECK, GET/POST `/api/inventory`, создание из Vue Pro v0.3.1 **работает**.
- `Asset` + `AssetStatus` реализованы; `AssetTest` **9 тестов / 18 assertions**.
- Миграция `Version20261029215033`; пользователь подтвердил её доработку/применение. Валидность ORM mapping проверена после изоляции незавершённых заказов.
- `AssetPersistenceTest` с PostgreSQL успешно выполнен **дважды** (по 8 assertions), для изоляции согласован rollback.
- Backend Git baseline **`433fa85`**; активная ветка `feature/asset-model`. Предложенные коммиты с PHPUnit, доменом, миграцией и docs **ещё не подтверждены**.
- `src/DTO/Request/CreateAssetRequest.php` существует в рабочей папке, но **ещё не просмотрен**.

## Немедленный следующий тикет

**RENTAL-005.5:** API создания физического экземпляра `POST /api/inventory/{id}/assets`. Входные поля: `inventoryCode`, `purchasePriceKopecks?`, `purchasedAt?`; статус `active` задаётся сервером. Ответ `201`, несуществующая модель `404`, валидация `422`, дубликат кода `409`, неправильный JSON `400`. Начать с code review уже существующего `CreateAssetRequest.php`; дальше Service, Controller, Response DTO, реальные HTTP-проверки. Код пишет пользователь.

## Важнейшие риски

`InventoryItem.stock/reserved` всё ещё используется и **не синхронизируется** с `Asset`. Нельзя создавать второй источник истины без стратегии перехода/backfill. `RentalOrder`, `OrderItem` — незавершённые классы, ранее ломали `doctrine:schema:validate`; держать изолированно до отдельного тикета. Значимые изменения фиксировать Git-коммитами и обновлять `docs/`. Ассистент не имеет прямого доступа к пользовательской WSL-папке: готовит docs ZIP/patch для применения и ревью пользователем.
