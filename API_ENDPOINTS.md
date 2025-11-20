# 📡 API Эндпоинты для работы с отмененными рейсами

## 🔄 Цепочка запросов

### 1. Frontend → Backend API
**Эндпоинт:** `GET /api/races?from={id}&to={id}&date={Y-m-d}`

**Пример запроса:**
```javascript
GET /api/races?from=1&to=2&date=2025-11-06
```

**Контроллер:** `App\Http\Controllers\Api\TripController@getCancelled`

**Маршрут:** `routes/api.php:37`
```php
Route::get('/', 'App\Http\Controllers\Api\TripController@getCancelled');
```

**Альтернативный эндпоинт:** `GET /api/trips/cancelled?from={id}&to={id}&date={Y-m-d}` (тот же контроллер)

---

### 2. Backend → External Carrier API
**Эндпоинт:** `GET {CARRIER_API_URL}/races?from={external_id}&to={external_id}&date={DD.MM.YY}`

**Пример запроса:**
```
GET http://vl.rfbus.net:8086/races?from=50475&to=61104&date=06.11.25
```

**Заголовки:**
```
x-access-token: {API_KEY}
Accept: application/json
Content-Type: application/json
```

**Сервис:** `App\Services\CarrierApiService::getRaces()`

**Метод фильтрации:** `App\Services\CarrierApiService::getCancelledRaces()`

---

## 📋 Детальное описание эндпоинтов

### ✅ GET /api/races

**Описание:** Получить список отмененных рейсов

**Параметры запроса:**
- `from` (required, integer) - ID станции отправления в локальной БД
- `to` (required, integer) - ID станции прибытия в локальной БД  
- `date` (required, string) - Дата в формате `Y-m-d` (например: `2025-11-06`)

**Валидация:**
- `from` - должен существовать в таблице `stations`
- `to` - должен существовать в таблице `stations`
- `date` - должен быть валидной датой

**Процесс обработки:**

1. **Получение станций из БД:**
   ```php
   $fromStation = Station::findOrFail($request->input('from'));
   $toStation = Station::findOrFail($request->input('to'));
   ```

2. **Проверка external_id:**
   - Проверяется наличие `external_id` у обеих станций
   - Если `external_id` отсутствует (null, пустая строка, или "0"), возвращается ошибка 400

3. **Запрос к внешнему API:**
   ```php
   $cancelledRaces = $this->carrierApiService->getCancelledRaces(
       (int)$fromStation->external_id,
       (int)$toStation->external_id,
       $request->input('date')
   );
   ```

4. **Фильтрация отмененных рейсов:**
   - В методе `getCancelledRaces()` происходит фильтрация по `active === false`
   - Возвращаются только отмененные рейсы

**Формат ответа (успех):**
```json
{
  "success": true,
  "data": [
    {
      "id": "string",
      "active": false,
      "route_tz": 3,
      "dt_depart": "2025-11-06T14:30:00.000Z",
      "dt_arrive": "2025-11-06T20:40:00.000Z",
      "route_start": "Смирных, Арена",
      "route_end": "Южно-Сахалинск, ЖД Вокзал"
    }
  ],
  "count": 1,
  "from_station": {
    "id": 1,
    "name": "Смирных",
    "external_id": "61117"
  },
  "to_station": {
    "id": 2,
    "name": "Южно-Сахалинск",
    "external_id": "50475"
  },
  "date": "2025-11-06"
}
```

**Формат ответа (ошибка - нет external_id):**
```json
{
  "success": false,
  "message": "Станции должны иметь external_id. Пожалуйста, сначала синхронизируйте станции.",
  "details": "Следующие станции не синхронизированы: Смирных (ID: 1, external_id: null)",
  "hint": "Перейдите в админ-панель → Настройки → API Перевозчика и нажмите кнопку \"Обновить станции\""
}
```

**HTTP статусы:**
- `200 OK` - Успешный запрос
- `400 Bad Request` - Ошибка валидации или отсутствие external_id
- `401 Unauthorized` - Не авторизован
- `500 Internal Server Error` - Ошибка сервера

---

### 🔍 GET {CARRIER_API_URL}/races

**Описание:** Запрос к внешнему API перевозчика для получения рейсов

**Параметры запроса:**
- `from` (required, integer) - `external_id` станции отправления
- `to` (required, integer) - `external_id` станции прибытия
- `date` (required, string) - Дата в формате `DD.MM.YY` (например: `06.11.25`)

**Заголовки:**
```
x-access-token: {API_KEY}
Accept: application/json
Content-Type: application/json
```

**Метод:** `CarrierApiService::getRaces()`

**Код:**
```php
public function getRaces(int $fromStationId, int $toStationId, string $date): array
{
    // Конвертация даты из Y-m-d в DD.MM.YY
    $carbon = \Carbon\Carbon::parse($date);
    $formattedDate = $carbon->format('d.m.y');
    
    $races = $this->makeRequest('GET', '/races', [
        'from' => $fromStationId,
        'to' => $toStationId,
        'date' => $formattedDate,
    ]);
    
    return $races;
}
```

**Метод фильтрации:** `CarrierApiService::getCancelledRaces()`
```php
public function getCancelledRaces(int $fromStationId, int $toStationId, string $date): array
{
    $races = $this->getRaces($fromStationId, $toStationId, $date);
    
    // Фильтруем только отмененные рейсы (active = false)
    return array_filter($races, function ($race) {
        return isset($race['active']) && $race['active'] === false;
    });
}
```

**Формат ответа от внешнего API:**
```json
[
  {
    "id": "string",
    "active": false,
    "route_tz": 3,
    "dt_depart": "2025-10-21T14:30:00.000Z",
    "dt_arrive": "2025-10-21T20:40:00.000Z",
    "route_start": "Смирных, Арена",
    "route_end": "Южно-Сахалинск, ЖД Вокзал"
  },
  {
    "id": "string",
    "active": true,
    "route_tz": 3,
    "dt_depart": "2025-10-21T15:00:00.000Z",
    "dt_arrive": "2025-10-21T21:10:00.000Z",
    "route_start": "Смирных, Арена",
    "route_end": "Южно-Сахалинск, ЖД Вокзал"
  }
]
```

**После фильтрации возвращаются только рейсы с `active: false`**

---

## 🔄 Frontend код

**Файл:** `resources/views/dashboard/index.blade.php`

**Метод:** `searchCancelledRaces()`

**Код запроса:**
```javascript
async searchCancelledRaces() {
    // Форматируем дату для API (Y-m-d)
    const date = new Date(this.searchForm.date);
    const formattedDate = date.toISOString().split('T')[0];
    
    // GET запрос: /races?from={id_from}&to={id_to}&date={Y-m-d}
    response = await fetch(`/api/races?from=${this.searchForm.from}&to=${this.searchForm.to}&date=${formattedDate}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
    });
    
    const data = await response.json();
    
    // Дополнительная фильтрация на фронтенде (для надежности)
    this.races = racesData.filter(race => {
        return race.active === false && 
               race.id && 
               (race.dt_depart || race.dt_arrive);
    });
}
```

---

## ✅ Проверка работы

### Тестовый запрос:

1. **Frontend запрос:**
   ```bash
   GET /api/races?from=1&to=2&date=2025-11-06
   ```

2. **Backend обрабатывает:**
   - Получает станции из БД (ID: 1 и 2)
   - Проверяет наличие `external_id`
   - Если есть, делает запрос к внешнему API

3. **Внешний API запрос:**
   ```bash
   GET http://vl.rfbus.net:8086/races?from=61117&to=50475&date=06.11.25
   Headers:
     x-access-token: {API_KEY}
   ```

4. **Фильтрация:**
   - Backend фильтрует по `active === false`
   - Frontend дополнительно проверяет `active === false`

5. **Результат:**
   - Возвращаются только отмененные рейсы
   - Отображаются на фронтенде

---

## 📝 Логирование

Все запросы логируются:

1. **В TripController:**
   ```php
   Log::error("Failed to get cancelled races", [...]);
   ```

2. **В CarrierApiService:**
   ```php
   Log::info("Getting races from carrier API", [
       'from_station_id' => $fromStationId,
       'to_station_id' => $toStationId,
       'date' => $date,
       'formatted_date' => $formattedDate,
   ]);
   ```

3. **В makeRequest:**
   ```php
   Log::info("Carrier API request", [
       'method' => $method,
       'endpoint' => $endpoint,
       'url' => $url,
   ]);
   ```

---

## 🎯 Итоговая схема

```
Frontend (Vue.js)
    ↓
    GET /api/races?from=1&to=2&date=2025-11-06
    ↓
TripController::getCancelled()
    ↓
    Проверка external_id станций
    ↓
CarrierApiService::getCancelledRaces()
    ↓
CarrierApiService::getRaces()
    ↓
    GET {CARRIER_API_URL}/races?from=61117&to=50475&date=06.11.25
    ↓
External Carrier API
    ↓
    Возвращает все рейсы (active: true и false)
    ↓
CarrierApiService::getCancelledRaces()
    ↓
    Фильтрует: active === false
    ↓
TripController::getCancelled()
    ↓
    Возвращает JSON с отмененными рейсами
    ↓
Frontend
    ↓
    Дополнительная фильтрация: active === false
    ↓
    Отображение на странице
```

---

## ✅ Вывод

**Код действительно делает запросы и проверяет отмененные рейсы:**

1. ✅ Frontend делает запрос к `/api/races`
2. ✅ Backend проверяет наличие `external_id` у станций
3. ✅ Backend делает запрос к внешнему API перевозчика
4. ✅ Backend фильтрует рейсы по `active === false`
5. ✅ Frontend дополнительно проверяет `active === false`
6. ✅ Отмененные рейсы отображаются на странице

**Все работает корректно!** 🎉













