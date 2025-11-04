<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель оператора</title>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 1.5rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #51cf66;
            color: white;
        }
        
        .btn-success:hover {
            background: #40c057;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .trip-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .trip-item:hover {
            border-color: #667eea;
        }
        
        .trip-item.selected {
            background: #e7f3ff;
            border-color: #2196F3;
        }
        
        .passenger-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .passenger-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d3f9d8;
            color: #2f9e44;
        }
        
        .badge-warning {
            background: #fff3bf;
            color: #f08c00;
        }
        
        .badge-danger {
            background: #ffe0e0;
            color: #c92a2a;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-box {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-box .label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📱 Панель оператора</h1>
        <div style="display: flex; align-items: center; gap: 20px;">
            <span style="color: #666;">Оператор: <strong>{{ auth()->user()->name ?? 'Тестовый пользователь' }}</strong></span>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
                    Выйти
                </button>
            </form>
        </div>
    </div>
    
    <div id="app" class="container">
        <!-- Шаг 1: Поиск отмененных рейсов -->
        <div class="card">
            <h2>Шаг 1: Поиск отмененных рейсов</h2>
            <div class="grid">
                <div class="form-group">
                    <label>Станция отправления</label>
                    <select v-model="searchForm.departureStationId">
                        <option value="">Выберите станцию</option>
                        <option value="1">Смирных</option>
                        <option value="2">Южно-Сахалинск</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Станция прибытия</label>
                    <select v-model="searchForm.arrivalStationId">
                        <option value="">Выберите станцию</option>
                        <option value="1">Смирных</option>
                        <option value="2">Южно-Сахалинск</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Дата</label>
                    <input type="date" v-model="searchForm.date">
                </div>
            </div>
            <button class="btn btn-primary" @click="searchTrips">🔍 Найти отмененные рейсы</button>
        </div>
        
        <!-- Шаг 2: Выбор рейсов -->
        <div class="card" v-if="trips.length > 0">
            <h2>Шаг 2: Выбор рейсов для уведомления</h2>
            <div class="grid">
                <div v-for="trip in trips" :key="trip.id" 
                     class="trip-item" 
                     :class="{ selected: selectedTrips.includes(trip.id) }"
                     @click="toggleTrip(trip.id)">
                    <strong>Рейс №@{{ trip.trip_number }}</strong>
                    <div style="margin-top: 8px; font-size: 0.9rem; color: #666;">
                        Время: @{{ trip.departure_time }}<br>
                        Пассажиров: @{{ trip.passengers_count || 0 }}
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" @click="loadPassengers" :disabled="selectedTrips.length === 0">
                📋 Загрузить пассажиров (@{{ selectedTrips.length }} рейсов)
            </button>
        </div>
        
        <!-- Шаг 3: Список пассажиров -->
        <div class="card" v-if="passengers.length > 0">
            <h2>Шаг 3: Список пассажиров (@{{ passengers.length }})</h2>
            <div class="passenger-list">
                <div v-for="passenger in passengers" :key="passenger.id" class="passenger-item">
                    <div>
                        <strong>@{{ passenger.full_name }}</strong><br>
                        <span style="color: #666; font-size: 0.9rem;">
                            @{{ passenger.email || 'Нет email' }} | @{{ passenger.phone || 'Нет телефона' }}
                        </span>
                    </div>
                    <div>
                        <span class="badge badge-success" v-if="passenger.email && passenger.phone">✓ Email + WhatsApp</span>
                        <span class="badge badge-warning" v-else-if="passenger.email || passenger.phone">⚠ Один канал</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Шаг 4: Подготовка сообщения -->
        <div class="card" v-if="passengers.length > 0">
            <h2>Шаг 4: Подготовка сообщения</h2>
            <div class="form-group">
                <label>Выберите шаблон</label>
                <select v-model="notificationForm.templateId">
                    <option value="">Без шаблона (свой текст)</option>
                    <option value="1">Уведомление об отмене рейса</option>
                    <option value="2">Уведомление о задержке</option>
                </select>
            </div>
            <div class="form-group">
                <label>Текст сообщения</label>
                <textarea v-model="notificationForm.message" rows="6" 
                          placeholder="Введите текст сообщения..."></textarea>
                <small style="color: #666;">
                    Доступные переменные: @{{passenger_full_name}}, @{{trip_number}}, @{{departure_time}}, @{{departure_station}}, @{{arrival_station}}
                </small>
            </div>
        </div>
        
        <!-- Шаг 5: Отправка -->
        <div class="card" v-if="passengers.length > 0">
            <h2>Шаг 5: Отправка уведомлений</h2>
            <button class="btn btn-success" @click="sendNotifications">
                ✉️ Отправить уведомления (@{{ passengers.length }} пассажиров)
            </button>
            
            <div class="stats" v-if="stats.total > 0">
                <div class="stat-box">
                    <div class="number">@{{ stats.total }}</div>
                    <div class="label">Всего</div>
                </div>
                <div class="stat-box">
                    <div class="number">@{{ stats.sent }}</div>
                    <div class="label">Отправлено</div>
                </div>
                <div class="stat-box">
                    <div class="number">@{{ stats.pending }}</div>
                    <div class="label">В очереди</div>
                </div>
                <div class="stat-box">
                    <div class="number">@{{ stats.failed }}</div>
                    <div class="label">Ошибки</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    searchForm: {
                        departureStationId: '',
                        arrivalStationId: '',
                        date: new Date().toISOString().split('T')[0]
                    },
                    trips: [],
                    selectedTrips: [],
                    passengers: [],
                    notificationForm: {
                        templateId: '',
                        message: 'Уважаемый {{passenger_full_name}},\n\nСообщаем, что рейс №{{trip_number}} {{departure_station}} → {{arrival_station}}, запланированный на {{departure_time}}, был отменен.\n\nПриносим извинения за неудобства.'
                    },
                    stats: {
                        total: 0,
                        sent: 0,
                        pending: 0,
                        failed: 0
                    },
                    currentTaskId: null
                }
            },
            methods: {
                async searchTrips() {
                    alert('Поиск отмененных рейсов...\n\nЭто демо-версия интерфейса. Для работы необходимо настроить API.');
                    // Пример: имитация данных
                    this.trips = [
                        { id: 1, trip_number: '507', departure_time: '10:30', passengers_count: 15 },
                        { id: 2, trip_number: '508', departure_time: '14:00', passengers_count: 23 }
                    ];
                },
                toggleTrip(tripId) {
                    const index = this.selectedTrips.indexOf(tripId);
                    if (index > -1) {
                        this.selectedTrips.splice(index, 1);
                    } else {
                        this.selectedTrips.push(tripId);
                    }
                },
                async loadPassengers() {
                    alert('Загрузка пассажиров...');
                    // Имитация данных
                    this.passengers = [
                        { id: 1, full_name: 'Иванов Иван Иванович', email: 'ivanov@example.com', phone: '+79001234567' },
                        { id: 2, full_name: 'Петров Петр Петрович', email: 'petrov@example.com', phone: '+79001234568' }
                    ];
                },
                async sendNotifications() {
                    if (confirm('Отправить уведомления всем пассажирам?')) {
                        alert('Уведомления поставлены в очередь!\n\nЭто демо-версия. В реальной системе здесь будет отправка через API.');
                        this.stats = {
                            total: this.passengers.length * 2,
                            sent: 0,
                            pending: this.passengers.length * 2,
                            failed: 0
                        };
                    }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>


