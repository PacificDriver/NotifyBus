# 🏗 Архитектура системы

## Обзор

Система построена на современном стеке технологий с использованием Laravel 11, обеспечивающим надежность, масштабируемость и простоту разработки.

## 📊 Диаграмма компонентов

```
┌─────────────────────────────────────────────────────────────┐
│                      Клиенты                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Браузер    │  │  Mobile App  │  │  API Client  │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Nginx (Reverse Proxy)                    │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Application                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  Web Routes │  │ API Routes  │  │  Middleware │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Controllers Layer                      │   │
│  │  • StationController                                │   │
│  │  • TripController                                   │   │
│  │  • PassengerController                             │   │
│  │  • NotificationTaskController                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Services Layer                         │   │
│  │  • EmailService                                     │   │
│  │  • WhatsAppService                                  │   │
│  │  • CarrierApiService                               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Models Layer (Eloquent)                │   │
│  │  • User, Station, Route, Trip                      │   │
│  │  • Passenger, MessageTemplate                       │   │
│  │  • NotificationTask, Notification                   │   │
│  └─────────────────────────────────────────────────────┘   │
└───────────────┬─────────────────────┬───────────────────────┘
                │                     │
                ▼                     ▼
    ┌────────────────────┐  ┌──────────────────┐
    │   PostgreSQL       │  │      Redis       │
    │   (Основная БД)    │  │  (Кэш, Очереди)  │
    └────────────────────┘  └──────────────────┘
                                      │
                                      ▼
                          ┌───────────────────────┐
                          │  Queue Workers        │
                          │  (Supervisor)         │
                          │  • SendNotificationJob│
                          └───────────────────────┘
                                      │
                    ┌─────────────────┴─────────────────┐
                    ▼                                   ▼
        ┌───────────────────┐              ┌──────────────────┐
        │  Email Service    │              │  WhatsApp API    │
        │  (SMTP)           │              │  (External)      │
        └───────────────────┘              └──────────────────┘
```

## 🔄 Поток данных

### 1. Создание задачи на рассылку

```
Оператор → Web UI → Controller → Validation
                                     ↓
                               Create Task
                                     ↓
                          Load Passengers (API)
                                     ↓
                          Create Notifications
                                     ↓
                          Queue Jobs (Redis)
```

### 2. Отправка уведомления

```
Redis Queue → Job Worker → SendNotificationJob
                                     ↓
                          Check Notification Status
                                     ↓
                    ┌─────────────────┴──────────────┐
                    ▼                                ▼
            Email Service                   WhatsApp Service
                    ↓                                ↓
            SMTP Server                       WhatsApp API
                    ↓                                ↓
            Update Status                     Update Status
                    └─────────────────┬──────────────┘
                                     ▼
                          Update Task Statistics
```

## 📦 Слои приложения

### Presentation Layer (Представление)

**Ответственность**: Взаимодействие с пользователем

**Компоненты**:
- Blade шаблоны (`resources/views/`)
- Vue.js компоненты (inline)
- API Controllers
- Form Requests (Validation)

**Пример**:
```php
// Controller принимает запрос, валидирует, вызывает сервис
public function send(Request $request, int $id): JsonResponse
{
    $validated = $request->validate([...]);
    $result = $this->notificationService->send($id);
    return response()->json($result);
}
```

### Business Logic Layer (Бизнес-логика)

**Ответственность**: Основная логика приложения

**Компоненты**:
- Services (`app/Services/`)
- Jobs (`app/Jobs/`)
- Events & Listeners (опционально)

**Пример**:
```php
// Service содержит бизнес-логику
class WhatsAppService
{
    public function send(string $to, string $message): bool
    {
        // Проверка лимитов
        if (!$this->checkDailyLimit()) {
            throw new \Exception('Daily limit exceeded');
        }
        
        // Отправка
        $response = Http::post(...);
        
        // Обновление счетчиков
        $this->incrementDailyCounter();
        
        return $response->successful();
    }
}
```

### Data Access Layer (Доступ к данным)

**Ответственность**: Работа с базой данных

**Компоненты**:
- Eloquent Models (`app/Models/`)
- Database Migrations
- Seeders
- Query Scopes

**Пример**:
```php
// Model определяет структуру и отношения
class Trip extends Model
{
    // Отношения
    public function passengers()
    {
        return $this->hasMany(Passenger::class);
    }
    
    // Scopes
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
```

## 🗄 Структура базы данных

### Основные таблицы и связи

```
users (1) ───┐
             │
             ├─── (N) notification_tasks
             │              │
             └─── (N) message_templates
                            │
                            │
stations (1) ─── (N) routes (1) ─── (N) trips (1) ─── (N) passengers
                                           │                   │
                                           │                   │
                                           └─── (N) notification_tasks (1)
                                                       │
                                                       └─── (N) notifications
```

### Ключевые индексы

- `trips.departure_time, trips.status` - быстрый поиск рейсов
- `notifications.notification_task_id, notifications.status` - статистика задач
- `passengers.trip_id` - загрузка пассажиров рейса
- `notifications.status` - фильтрация по статусу

## ⚙️ Асинхронная обработка

### Архитектура очередей

```
┌───────────────────────────────────────────────────┐
│              Notification Task                    │
│  Created → Generates N notifications              │
└───────────────────┬───────────────────────────────┘
                    │
                    ▼
┌───────────────────────────────────────────────────┐
│           Redis Queue (FIFO)                      │
│  Job1 → Job2 → Job3 → Job4 → ...                 │
└───────────────────┬───────────────────────────────┘
                    │
        ┌───────────┴────────────┬──────────────┐
        ▼                        ▼              ▼
┌─────────────┐          ┌─────────────┐  ┌─────────────┐
│  Worker 1   │          │  Worker 2   │  │  Worker 3   │
│ Processing  │          │ Processing  │  │  Processing  │
│   Job1      │          │   Job2      │  │   Job3      │
└─────────────┘          └─────────────┘  └─────────────┘
```

### Job Lifecycle

```
Pending → Queued → Processing → [Success/Failed]
                        ↓
                    Retry (max 3)
                        ↓
                    Failed Jobs Table
```

## 🔐 Безопасность

### Уровни защиты

1. **Network Level**
   - Nginx (Reverse Proxy)
   - Firewall (UFW)
   - HTTPS (Let's Encrypt)

2. **Application Level**
   - CSRF Protection (Laravel)
   - XSS Protection
   - SQL Injection Protection (Eloquent)
   - Rate Limiting

3. **Authentication & Authorization**
   - Laravel Sanctum (API)
   - Session-based auth (Web)
   - Role-based access control

4. **Data Level**
   - Password hashing (bcrypt)
   - Encrypted connections (PostgreSQL, Redis)
   - Input validation

## 📈 Масштабирование

### Вертикальное масштабирование

- Увеличение ресурсов сервера (CPU, RAM)
- Оптимизация запросов к БД
- Кэширование (Redis)
- Connection pooling (PostgreSQL)

### Горизонтальное масштабирование

**Возможные улучшения**:

1. **Database**
   - Read replicas для PostgreSQL
   - Partitioning больших таблиц
   - Sharding по типу данных

2. **Queue Workers**
   - Добавление worker серверов
   - Распределение по типам задач
   - Priority queues

3. **Load Balancing**
   - Multiple application servers
   - Nginx load balancer
   - Session sharing (Redis)

4. **Caching**
   - Redis cluster
   - CDN для статики
   - Edge caching

## 🔧 Мониторинг и логирование

### Уровни логирования

```php
// Emergency: система недоступна
Log::emergency('Redis connection failed');

// Error: требует немедленного внимания
Log::error('Failed to send notification', ['id' => $id]);

// Warning: предупреждение, но система работает
Log::warning('Daily WhatsApp limit approaching', ['count' => 950]);

// Info: важная информация
Log::info('Notification task created', ['task_id' => $task->id]);

// Debug: отладочная информация
Log::debug('Processing notification', ['data' => $data]);
```

### Метрики для мониторинга

- Количество задач в очереди
- Время обработки уведомлений
- Rate of failed notifications
- API response times
- Database query performance
- Memory usage workers
- Redis memory usage

## 🚀 Performance Optimizations

### Database

1. **Eager Loading**
```php
// Плохо (N+1 query)
$trips = Trip::all();
foreach ($trips as $trip) {
    echo $trip->route->name; // N queries
}

// Хорошо
$trips = Trip::with('route')->all();
foreach ($trips as $trip) {
    echo $trip->route->name; // 1 query
}
```

2. **Chunk Processing**
```php
// Обработка больших данных порциями
Passenger::where('trip_id', $tripId)
    ->chunk(100, function ($passengers) {
        foreach ($passengers as $passenger) {
            // Обработка
        }
    });
```

3. **Indexing**
```php
// В миграциях
$table->index(['status', 'created_at']);
$table->index('email');
```

### Caching

```php
// Кэширование тяжелых запросов
$stations = Cache::remember('active_stations', 3600, function () {
    return Station::active()->get();
});

// Очистка кэша при изменении
Cache::forget('active_stations');
```

### Queue Optimization

```php
// Батчинг задач
Bus::batch([
    new SendNotificationJob($notification1),
    new SendNotificationJob($notification2),
    // ...
])->dispatch();

// Приоритеты
SendNotificationJob::dispatch($notification)
    ->onQueue('high-priority');
```

## 🧪 Тестирование

### Типы тестов

1. **Unit Tests** - тестирование отдельных методов
2. **Feature Tests** - тестирование API endpoints
3. **Integration Tests** - тестирование интеграций

### Пример структуры

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── EmailServiceTest.php
│   │   └── WhatsAppServiceTest.php
│   └── Models/
│       └── PassengerTest.php
└── Feature/
    ├── Api/
    │   ├── TripControllerTest.php
    │   └── NotificationTaskControllerTest.php
    └── Jobs/
        └── SendNotificationJobTest.php
```

## 🔄 Deployment Pipeline

```
Development → Testing → Staging → Production

1. Local Development
   ↓
2. Git Push → GitHub
   ↓
3. CI/CD (GitHub Actions)
   - Run Tests
   - Code Quality Checks
   ↓
4. Deploy to Staging
   - Run Migrations
   - Smoke Tests
   ↓
5. Manual Approval
   ↓
6. Deploy to Production
   - Zero-downtime deployment
   - Database migrations
   - Cache warming
```

## 📚 Дополнительные ресурсы

- [Laravel Documentation](https://laravel.com/docs)
- [PostgreSQL Best Practices](https://www.postgresql.org/docs/)
- [Redis Documentation](https://redis.io/documentation)
- [Nginx Configuration](https://nginx.org/en/docs/)

---

**Версия документа**: 1.0  
**Дата обновления**: 2024-10-31


