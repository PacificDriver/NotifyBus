<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            <h2>📨 Создать рассылку уведомлений</h2>
            
            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #555;">Шаг 1: Поиск отмененных рейсов</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Выберите маршрут и дату, чтобы найти отмененные рейсы.
                </p>
                <div class="grid">
                    <div class="form-group">
                        <label>Станция «Откуда»</label>
                        <select v-model="searchForm.from" required>
                            <option value="">Выберите станцию</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">
                                @{{ station.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Станция «Куда»</label>
                        <select v-model="searchForm.to" required>
                            <option value="">Выберите станцию</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">
                                @{{ station.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Дата рейса</label>
                        <input type="date" v-model="searchForm.date" required>
                    </div>
                </div>
                <button class="btn btn-primary" @click="searchCancelledRaces" :disabled="searching">
                    <span v-if="searching">⏳ Поиск...</span>
                    <span v-else>🔍 Найти отмененные рейсы</span>
                </button>
            </div>
        
            <!-- Выбор найденных рейсов -->
            <div v-if="races.length > 0" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #555;">Найдено отмененных рейсов: <strong>@{{ races.length }}</strong></h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <input type="checkbox" @change="toggleAllRaces" :checked="selectedRaces.length === races.length && races.length > 0" style="width: 18px; height: 18px; cursor: pointer;">
                        <strong>Выбрать все (@{{ selectedRaces.length }} из @{{ races.length }})</strong>
                    </label>
                </div>
                
                <div class="grid" style="max-height: 400px; overflow-y: auto; padding: 10px;">
                    <div v-for="race in races" :key="race.id" 
                         class="trip-item" 
                         :class="{ selected: selectedRaces.includes(race.id) }"
                         style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" 
                               :value="race.id" 
                               v-model="selectedRaces"
                               style="width: 18px; height: 18px; margin-top: 2px; cursor: pointer;">
                        <div style="flex: 1;">
                            <strong>Рейс ID: @{{ race.id }}</strong>
                            <div style="margin-top: 8px; font-size: 0.9rem; color: #666;">
                                <div>Отправление: @{{ formatDateTime(race.dt_depart) }}</div>
                                <div>Прибытие: @{{ formatDateTime(race.dt_arrive) }}</div>
                                <div>Часовой пояс: UTC+@{{ race.route_tz || 'N/A' }}</div>
                                <div style="margin-top: 5px;">
                                    <span class="badge badge-danger">Отменен</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button class="btn btn-success" @click="addRacesToTask" :disabled="selectedRaces.length === 0 || addingRaces">
                        <span v-if="addingRaces">⏳ Добавление...</span>
                        <span v-else>➕ Добавить выбранные рейсы в задачу (@{{ selectedRaces.length }} рейсов)</span>
                    </button>
                </div>
            </div>
            
            <div class="card" v-if="races.length === 0 && searchPerformed" style="margin-top: 20px;">
                <p style="color: #666; text-align: center; padding: 20px;">
                    Отмененных рейсов не найдено для выбранных параметров
                </p>
            </div>
        
        <!-- Шаг 3: Загрузка пассажиров и отправка -->
        <div class="card" v-if="currentTask && currentTaskRacesCount > 0">
            <h3 style="margin-bottom: 15px; color: #555;">Шаг 3: Загрузка пассажиров и отправка</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Задача: <strong>@{{ currentTask.title }}</strong><br>
                Рейсов в задаче: <strong>@{{ currentTaskRacesCount }}</strong>
            </p>
            
            <div class="form-group">
                <label>Выберите шаблон сообщения</label>
                <select v-model="notificationForm.templateId">
                    <option value="">Без шаблона (свой текст)</option>
                    <option v-for="template in templates" :key="template.id" :value="template.id">
                        @{{ template.name }}
                    </option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Текст сообщения</label>
                <textarea v-model="notificationForm.message" rows="6" 
                          placeholder="Введите текст сообщения..."></textarea>
                <small style="color: #666;">
                    Доступные переменные: <strong>{РЕЙС}, {ДАТА}, {ВРЕМЯ}</strong>
                </small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-primary" @click="loadPassengersForTask" :disabled="loadingPassengers">
                    <span v-if="loadingPassengers">⏳ Загрузка...</span>
                    <span v-else>📋 Загрузить список пассажиров</span>
                </button>
                
                <button class="btn btn-success" @click="sendNotifications" :disabled="!passengersLoaded || sending">
                    <span v-if="sending">⏳ Отправка...</span>
                    <span v-else>✉️ Отправить уведомления</span>
                </button>
            </div>
            
            <!-- Список загруженных пассажиров с возможностью исключения -->
            <div v-if="passengers.length > 0" style="margin-top: 20px;">
                <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                    <strong>Пассажиры загружены:</strong><br>
                    Всего: @{{ passengers.length }} | С email: @{{ passengersWithEmail }} | С телефоном: @{{ passengersWithPhone }}
                </div>
                
                <h4 style="margin-bottom: 10px; color: #555;">Список пассажиров</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <input type="checkbox" @change="toggleAllPassengers" :checked="selectedPassengers.length === passengers.length && passengers.length > 0" style="width: 18px; height: 18px; cursor: pointer;">
                        <strong>Выбрать всех (@{{ selectedPassengers.length }} из @{{ passengers.length }})</strong>
                    </label>
                </div>
                
                <div class="passenger-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px;">
                    <div v-for="passenger in passengers" :key="passenger.id" 
                         class="passenger-item"
                         :style="{ background: selectedPassengers.includes(passenger.id) ? '#f0f8ff' : 'white' }">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; flex: 1;">
                            <input type="checkbox" 
                                   :value="passenger.id" 
                                   v-model="selectedPassengers"
                                   style="width: 18px; height: 18px; cursor: pointer;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 4px;">
                                    @{{ passenger.last_name }} @{{ passenger.first_name }} @{{ passenger.middle_name }}
                                </div>
                                <div style="font-size: 0.9rem; color: #666;">
                                    <span v-if="passenger.email">📧 @{{ passenger.email }}</span>
                                    <span v-if="passenger.email && passenger.phone"> | </span>
                                    <span v-if="passenger.phone">📱 @{{ passenger.phone }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="stats" v-if="stats.total > 0" style="margin-top: 20px;">
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
                    stations: [],
                    searchForm: {
                        from: '',
                        to: '',
                        date: new Date().toISOString().split('T')[0]
                    },
                    taskForm: {
                        title: ''
                    },
                    races: [], // Отмененные рейсы из API
                    selectedRaces: [], // Выбранные ID рейсов
                    searchPerformed: false,
                    searching: false,
                    currentTask: null,
                    creatingTask: false,
                    addingRaces: false,
                    templates: [],
                    passengers: [], // Список всех загруженных пассажиров
                    selectedPassengers: [], // Выбранные ID пассажиров для отправки
                    passengersLoaded: false,
                    loadingPassengers: false,
                    sending: false,
                    notificationForm: {
                        templateId: '',
                        message: '❌ Рейс отменен. Ваш рейс {ДАТА} отправление в {ВРЕМЯ} по маршруту {РЕЙС} отменен.'
                    },
                    stats: {
                        total: 0,
                        sent: 0,
                        pending: 0,
                        failed: 0
                    }
                }
            },
            computed: {
                passengersWithEmail() {
                    return this.passengers.filter(p => p.email).length;
                },
                passengersWithPhone() {
                    return this.passengers.filter(p => p.phone).length;
                },
                currentTaskRacesCount() {
                    if (!this.currentTask || !this.currentTask.races_data) {
                        return 0;
                    }
                    return Array.isArray(this.currentTask.races_data) ? this.currentTask.races_data.length : 0;
                }
            },
            mounted() {
                this.loadStations();
                this.loadTemplates();
            },
            methods: {
                async loadStations() {
                    try {
                        const response = await fetch('/api/stations', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        if (data.success && data.data) {
                            this.stations = data.data;
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить станции');
                        }
                    } catch (error) {
                        console.error('Error loading stations:', error);
                        alert('⚠️ В текущий момент сервис недоступен.\n\nОшибка загрузки станций. Обратитесь к администратору для проверки настроек API Перевозчика.');
                        this.stations = [];
                    }
                },
                
                async loadTemplates() {
                    try {
                        const response = await fetch('/api/templates', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        if (data.success && data.data) {
                            this.templates = data.data.data || data.data || [];
                        }
                    } catch (error) {
                        console.error('Error loading templates:', error);
                        // Не показываем alert для шаблонов, так как это не критично
                        this.templates = [];
                    }
                },
                
                async searchCancelledRaces() {
                    if (!this.searchForm.from || !this.searchForm.to || !this.searchForm.date) {
                        alert('Пожалуйста, заполните все поля: станцию отправления, станцию прибытия и дату');
                        return;
                    }
                    
                    this.searching = true;
                    this.searchPerformed = true;
                    this.races = [];
                    this.selectedRaces = [];
                    
                    let response = null;
                    
                    try {
                        // Форматируем дату для API (Y-m-d)
                        const date = new Date(this.searchForm.date);
                        const formattedDate = date.toISOString().split('T')[0];
                        
                        // GET запрос: /races?from={id_from}&to={id_to}&date={DD.MM.YY}
                        // Но API принимает date в формате Y-m-d, а затем конвертирует в DD.MM.YY
                        response = await fetch(`/api/races?from=${this.searchForm.from}&to=${this.searchForm.to}&date=${formattedDate}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        let data;
                        try {
                            data = await response.json();
                        } catch (e) {
                            // Если ответ не JSON, используем текст
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        if (!response.ok) {
                            // Сервер вернул ошибку с деталями в JSON
                            throw new Error(data.message || data.error || `HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        if (data.success && data.data) {
                            // API уже возвращает только отмененные рейсы (active = false)
                            this.races = data.data;
                            if (this.races.length === 0) {
                                alert('Отмененных рейсов не найдено для выбранных параметров');
                            }
                        } else {
                            throw new Error(data.message || 'Неизвестная ошибка при поиске рейсов');
                        }
                    } catch (error) {
                        console.error('Error searching races:', error);
                        
                        // Получаем детали ошибки из ответа
                        let errorMessage = 'Неизвестная ошибка';
                        
                        // Пытаемся получить детали из ответа сервера
                        if (response && !response.ok) {
                            // response.json() уже был вызван выше, используем сообщение из error
                            errorMessage = error.message || `HTTP ${response.status}: ${response.statusText}`;
                        } else if (error.message) {
                            errorMessage = error.message;
                        }
                        
                        // Формируем понятное сообщение для пользователя
                        let userMessage = '⚠️ Ошибка подключения к API Перевозчика\n\n';
                        userMessage += errorMessage + '\n\n';
                        
                        // Добавляем подсказки в зависимости от типа ошибки
                        if (errorMessage.includes('400') || errorMessage.includes('Некорректный запрос')) {
                            userMessage += '💡 Совет: Проверьте правильность выбранных станций и даты.';
                        } else if (errorMessage.includes('401') || errorMessage.includes('авторизован')) {
                            userMessage += '💡 Совет: Проверьте настройки API ключа в админ-панели.';
                        } else if (errorMessage.includes('404') || errorMessage.includes('не найден')) {
                            userMessage += '💡 Совет: Проверьте настройки URL API в админ-панели.';
                        } else if (errorMessage.includes('подключиться') || errorMessage.includes('сеть')) {
                            userMessage += '💡 Совет: Проверьте доступность сервера API и настройки сети.';
                        } else {
                            userMessage += '💡 Совет: Обратитесь к администратору для проверки настроек API.';
                        }
                        
                        alert(userMessage);
                        this.races = [];
                        this.searchPerformed = true;
                    } finally {
                        this.searching = false;
                    }
                },
                
                toggleAllRaces(event) {
                    if (event.target.checked) {
                        this.selectedRaces = this.races.map(race => race.id);
                    } else {
                        this.selectedRaces = [];
                    }
                },
                
                async createTask() {
                    this.creatingTask = true;
                    
                    try {
                        const response = await fetch('/api/notification-tasks', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                title: this.taskForm.title || null,
                            }),
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.currentTask = data.data;
                            alert(`✅ Задача создана успешно! ID: ${data.data.id}\n\nТеперь можно добавить отмененные рейсы.`);
                            // Очищаем форму
                            this.taskForm.title = '';
                            this.races = [];
                            this.selectedRaces = [];
                            this.searchPerformed = false;
                        } else {
                            throw new Error(data.message || 'Не удалось создать задачу');
                        }
                    } catch (error) {
                        console.error('Error creating task:', error);
                        alert('⚠️ Ошибка при создании задачи: ' + error.message + '\n\nОбратитесь к администратору.');
                    } finally {
                        this.creatingTask = false;
                    }
                },
                
                async addRacesToTask() {
                    if (!this.currentTask || !this.currentTask.id) {
                        alert('Сначала создайте задачу');
                        return;
                    }
                    
                    if (this.selectedRaces.length === 0) {
                        alert('Выберите хотя бы один рейс для добавления');
                        return;
                    }
                    
                    this.addingRaces = true;
                    
                    try {
                        // Получаем полные данные выбранных рейсов
                        const racesData = this.races.filter(race => this.selectedRaces.includes(race.id));
                        
                        const response = await fetch(`/api/notification-tasks/${this.currentTask.id}/add-races`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                races_data: racesData,
                            }),
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Обновляем задачу
                            this.currentTask = data.data.task;
                            alert(`✅ Добавлено рейсов: ${data.data.added_count}\nВсего рейсов в задаче: ${data.data.total_races}`);
                            // Очищаем выбранные рейсы
                            this.selectedRaces = [];
                            // Опционально: очищаем список найденных рейсов
                            // this.races = [];
                            // this.searchPerformed = false;
                        } else {
                            throw new Error(data.message || 'Не удалось добавить рейсы');
                        }
                    } catch (error) {
                        console.error('Error adding races to task:', error);
                        alert('⚠️ Ошибка при добавлении рейсов: ' + error.message + '\n\nОбратитесь к администратору.');
                    } finally {
                        this.addingRaces = false;
                    }
                },
                
                async loadPassengersForTask() {
                    if (!this.currentTask || !this.currentTask.id) {
                        alert('Сначала создайте задачу');
                        return;
                    }
                    
                    if (this.currentTaskRacesCount === 0) {
                        alert('В задаче нет рейсов. Сначала добавьте отмененные рейсы в задачу.');
                        return;
                    }
                    
                    this.loadingPassengers = true;
                    
                    try {
                        const response = await fetch(`/api/notification-tasks/${this.currentTask.id}/load-passengers`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Загружаем список пассажиров для отображения
                            await this.fetchPassengersList();
                            this.passengersLoaded = true;
                            alert(`✅ Загружено пассажиров: ${data.data.saved_count || 0}`);
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить пассажиров');
                        }
                    } catch (error) {
                        console.error('Error loading passengers:', error);
                        alert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при загрузке пассажиров: ' + error.message + '\n\nОбратитесь к администратору для проверки настроек внешней БД.');
                        this.passengersLoaded = false;
                    } finally {
                        this.loadingPassengers = false;
                    }
                },
                
                async fetchPassengersList() {
                    if (!this.currentTask || !this.currentTask.trip_ids) {
                        return;
                    }
                    
                    try {
                        const response = await fetch(`/api/notification-tasks/${this.currentTask.id}/passengers`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success && data.data) {
                            this.passengers = data.data;
                            // По умолчанию выбираем всех пассажиров
                            this.selectedPassengers = this.passengers.map(p => p.id);
                        }
                    } catch (error) {
                        console.error('Error fetching passengers list:', error);
                        // Не показываем alert, так как это не критично
                    }
                },
                
                toggleAllPassengers(event) {
                    if (event.target.checked) {
                        this.selectedPassengers = this.passengers.map(p => p.id);
                    } else {
                        this.selectedPassengers = [];
                    }
                },
                formatDateTime(datetime) {
                    if (!datetime) return 'N/A';
                    try {
                        const date = new Date(datetime);
                        return date.toLocaleString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    } catch (e) {
                        return datetime;
                    }
                },
                async sendNotifications() {
                    if (!this.currentTask || !this.currentTask.id) {
                        alert('Сначала создайте задачу и загрузите пассажиров');
                        return;
                    }
                    
                    if (!this.passengersLoaded) {
                        alert('Сначала загрузите список пассажиров');
                        return;
                    }
                    
                    if (this.selectedPassengers.length === 0) {
                        alert('Выберите хотя бы одного пассажира для отправки');
                        return;
                    }
                    
                    if (!confirm(`Отправить уведомления ${this.selectedPassengers.length} пассажирам?`)) {
                        return;
                    }
                    
                    this.sending = true;
                    
                    try {
                        const response = await fetch(`/api/notification-tasks/${this.currentTask.id}/send`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                passenger_ids: this.selectedPassengers,
                                custom_message: this.notificationForm.message
                            })
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            alert(`✅ Уведомления поставлены в очередь!\nЗадача: ${this.currentTask.id}\nПолучателей: ${data.total_recipients || 0}`);
                            this.stats = {
                                total: data.total_recipients || 0,
                                sent: 0,
                                pending: data.total_notifications || 0,
                                failed: 0
                            };
                            
                            // Периодически обновляем статистику
                            this.updateStats(this.currentTask.id);
                        } else {
                            throw new Error(data.message || 'Не удалось отправить уведомления');
                        }
                    } catch (error) {
                        console.error('Error sending notifications:', error);
                        alert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при отправке уведомлений: ' + error.message + '\n\nОбратитесь к администратору для проверки настроек WhatsApp и Email.');
                    } finally {
                        this.sending = false;
                    }
                },
                
                async updateStats(taskId) {
                    try {
                        const response = await fetch(`/api/notification-tasks/${taskId}/status`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        if (data.success && data.data.stats) {
                            this.stats = {
                                total: data.data.stats.total || 0,
                                sent: data.data.stats.sent || 0,
                                pending: data.data.stats.pending || 0,
                                failed: data.data.stats.failed || 0
                            };
                        }
                    } catch (error) {
                        console.error('Error updating stats:', error);
                        // Не показываем alert для статистики, так как это не критично
                    }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>


