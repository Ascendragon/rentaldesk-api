# HTTP API: зафиксированные контракты

**Актуализировано:** 30.10.2026. Не путать подтверждённые API с планами.

## РЕАЛИЗОВАНО: `GET /api/inventory`

**Ответ:** `200 OK`, **корневой массив** (не объект `{ "data": [...] }`).

```json
[
  { "id": 1, "name": "Дрель Bosch", "stock": 10, "reserved": 3, "available": 7 },
  { "id": 2, "name": "Генератор", "stock": 5, "reserved": 1, "available": 4 }
]
```

Это образец подтверждённого ответа на предыдущем этапе, **не гарантия актуальных данных**. `available = stock - reserved` вычисляется доменной моделью. Другие HTTP-коды и сортировка пока не зафиксированы.

## РЕАЛИЗОВАНО: `POST /api/inventory`

**Request:**

```http
POST /api/inventory
Content-Type: application/json
```

```json
{ "name": "Перфоратор Makita", "stock": 8 }
```

**Ответ:** `201 Created` с данными созданной позиции:

```json
{ "id": 3, "name": "Перфоратор Makita", "stock": 8, "reserved": 0, "available": 8 }
```

`id` назначается базой, приведённый номер иллюстративный. `reserved = 0` устанавливается сервером, не принимается от клиента как редактируемое поле. Проверять: `name` непустое после `trim`, до 128 символов; `stock` — целое число >= 0; ошибочный JSON — `400`, ошибка валидируемых полей — `422` согласно согласованному контракту. Сценарии ошибок `400` и `422` проверены функциональными тестами `ApiProblemDetailsTest`; см. раздел Problem Details ниже.

**Подтверждено пользователем:** создание из Vue успешно работает; новая запись появляется в интерфейсе без перезагрузки, уведомление об успехе отображается. Был исправлен баг с `v-model` числового поля на Vue (нельзя вызывать `.trim()` у числа).

## РЕАЛИЗОВАНО: `POST /api/inventory/{id}/assets`

**Контракт RENTAL-005.5.** DTO, Service, Controller и Response DTO реализованы. `404`, `409`, `422`, `400` прошли функциональные HTTP-тесты с `WebTestCase` 30.10.2026; успешная регистрация `201` подтверждалась ранее пользователем и отдельный функциональный тест `201` здесь не представлен. Владелец экземпляра задаётся `{id}` пути, а не дублируется в теле. Запрос:

```http
POST /api/inventory/3/assets
Content-Type: application/json
```

```json
{ "inventoryCode": "MK-005", "purchasePriceKopecks": 1500000, "purchasedAt": "2026-10-30" }
```

`inventoryCode` — обязательная непустая строка до 64 символов после `trim`, глобально уникальная. `purchasePriceKopecks` — необязательный `int >= 0` или `null`. `purchasedAt` — необязательная валидная календарная дата в формате `YYYY-MM-DD` или `null`. `status` выставляет сервер (`active`), не принимает его от клиента. Ошибка синтаксиса JSON — `400`; несуществующий `InventoryItem` — `404`; нарушение бизнес-валидации — `422`; совпадающий код при вставке — `409` (источник истины — UNIQUE в PostgreSQL).

**Согласованный успешный ответ `201 Created`:**

```json
{ "id": 5, "inventoryItemId": 3, "inventoryCode": "MK-005", "purchasePriceKopecks": 1500000, "purchasedAt": "2026-10-30", "status": "active" }
```

Все ID иллюстративны. Формат ошибок зафиксирован в разделе RFC 9457 ниже и подтверждён функциональными тестами. Повторный POST с тем же `inventoryCode` не считается идемпотентным и должен получить конфликт. Эндпоинт не меняет `InventoryItem.stock/reserved` до отдельного решения о миграции количества.

## РЕАЛИЗОВАНО: `GET /api/inventory/{id}/assets` (RENTAL-005.6)

**Ответ для существующей модели:** `200 OK`, корневой JSON-массив `AssetResponse[]`, **по `id ASC`**. Фильтрация по `inventoryItem` через Doctrine Repository. У модели без экземпляров ответ `200 []`.

```http
GET /api/inventory/3/assets
Accept: application/json
```

```json
[
  { "id": 5, "inventoryItemId": 3, "inventoryCode": "MK-005", "purchasePriceKopecks": 1500000, "purchasedAt": "2026-10-30", "status": "active" }
]
```

Идентификаторы и данные иллюстративны. Если модель отсутствует, возвращается `404 application/problem+json` с `code: "inventory_item_not_found"` в формате RFC 9457. Постраничная выдача и серверная фильтрация по статусу пока **не реализованы**.

**Проверка 30.10.2026:** `AssetListApiTest` 3 функциональных HTTP-теста / 19 assertions: изоляция двух моделей, пустой результат, 404. Полный PHPUnit: **21 тест / 96 assertions / PASS**. Использована `rentaldesk_test` и транзакционный rollback.

**План PostgreSQL на одной строке:** `Bitmap Index Scan` по `inventory_item_id` → `Bitmap Heap Scan` → `Sort (id)`, `Execution Time: 0.102 ms`. Есть индекс `(inventory_item_id)`; **добавлять составной индекс `(inventory_item_id, id)` сейчас не обосновано**. Вывод на одной строке не экстраполировать на production-нагрузку.

## ЗАПЛАНИРОВАНО: Rental Order / Reservation API

Создание черновика, позиции, тарифы, даты, резервирование, выдача, возврат. Контракты предварительные; не считать `POST /api/orders` реализованным. Отдельно определить ошибки конкуренции и идемпотентность.


## РЕАЛИЗОВАНО: единый контракт ошибок API (RENTAL-CORE-001)

**Состояние 30.10.2026:** `ApiExceptionListener` формирует `application/problem+json` для `/api/*`, сохранение специальных HTTP-заголовков (например, `Allow` при `405`) проверено функционально. На присланном пользователем запуске: **6 тестов, 48 assertions, PASS**. Полный PHPUnit подтверждён пользователем 30.10.2026: **21 тест, 96 assertions, PASS**. Отдельный CI пока не подтверждён.

Ответ следует RFC 9457. Собственные расширения: `code` и необязательный `violations`. При `type: about:blank` используется стандартная HTTP-фраза в `title`.

```json
{
  "type": "about:blank",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "Request validation failed.",
  "code": "validation_failed",
  "violations": [
    { "field": "inventoryCode", "message": "This value should not be blank." }
  ]
}
```

| HTTP | code | Условие |
|---|---|---|
| 400 | `malformed_request` | Некорректный запрос/JSON (текущий обработчик трактует любой HTTP 400 так) |
| 404 | `inventory_item_not_found` | Запрошенная модель отсутствует, предметная ошибка |
| 405 | `http_error` | Неподдерживаемый HTTP-метод; заголовок `Allow` сохранён |
| 409 | `inventory_code_conflict` | PostgreSQL UNIQUE при регистрации Asset |
| 422 | `validation_failed` | Symfony DTO validator; `violations[]` с `field` и `message` |
| 500 | `internal_error` | Непредвиденный Throwable; `detail` безопасный, исходный текст не попадает в HTTP |

Прочие HTTP-исключения используют `http_error` для статусов 4xx и `internal_error` для 5xx; HTTP-заголовки исходного `HttpExceptionInterface` сохраняются. В тесте 500 строка `SECRET-123` не попала в HTTP-ответ, но **оказалась в диагностическом журнале**; безопасное логирование остаётся отдельным улучшением. Тест 409 использует настоящую БД и DBAL-cleanup; изоляция `rentaldesk_test` должна проверяться отдельно при новых окружениях. При ошибке `flush()` тот же EntityManager может быть закрыт.

Клиент Vue v0.4.2 читает `code`, `detail`, `violations[].field/message`; переходная поддержка старого `propertyPath/title` сохранена. Фронтенд-патч подготовлен, но его сборка в WSL пользователя и визуальная проверка ещё не подтверждены.

**Frontend v0.5:** подготовлен пакет для полного чтения реестра из GET API, поиском, фильтрами по статусу и re-fetch после POST. Сборка/браузерная проверка на WSL пользователя пока не подтверждена; не считать этот этап внедрённым до проверки.
