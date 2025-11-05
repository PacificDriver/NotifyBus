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
        <!-- Создание задачи на рассылку -->
        <div class="card">
            <h2>📨 Создать рассылку уведомлений</h2>
            
            <!-- Шаг 1: Поиск отмененных рейсов -->
            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #555;">Шаг 1: Поиск отмененных рейсов</h3>
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
        
        <!-- Шаг 2: Выбор рейсов -->
        <div class="card" v-if="races.length > 0">
            <h3 style="margin-bottom: 15px; color: #555;">Шаг 2: Выбор отмененных рейсов</h3>
            <p style="margin-bottom: 15px; color: #666;">Найдено отмененных рейсов: <strong>@{{ races.length }}</strong></p>
            
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
                <button class="btn btn-primary" @click="createTask" :disabled="selectedRaces.length === 0 || creatingTask">
                    <span v-if="creatingTask">⏳ Создание задачи...</span>
                    <span v-else>📝 Создать задачу на рассылку (@{{ selectedRaces.length }} рейсов)</span>
                </button>
            </div>
        </div>
        
        <div class="card" v-if="races.length === 0 && searchPerformed">
            <p style="color: #666; text-align: center; padding: 20px;">
                Отмененных рейсов не найдено для выбранных параметров
            </p>
        </div>
        
        <!-- Шаг 3: Загрузка пассажиров и отправка -->
        <div class="card" v-if="currentTask">
            <h3 style="margin-bottom: 15px; color: #555;">Шаг 3: Загрузка пассажиров и отправка</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Задача создана: <strong>@{{ currentTask.title }}</strong><br>
                Выбрано рейсов: <strong>@{{ selectedRaces.length }}</strong>
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
                    Доступные переменные: {РЕЙС}, {ДАТА}, {ВРЕМЯ}, {passenger_full_name}, {trip_number}, {departure_station}, {arrival_station}
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
            
            <div v-if="passengersInfo.total > 0" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong>Пассажиры загружены:</strong><br>
                Всего: @{{ passengersInfo.total }} | С email: @{{ passengersInfo.withEmail }} | С телефоном: @{{ passengersInfo.withPhone }}
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
                    races: [], // Отмененные рейсы из API
                    selectedRaces: [], // Выбранные ID рейсов
                    searchPerformed: false,
                    searching: false,
                    currentTask: null,
                    creatingTask: false,
                    templates: [],
                    passengersInfo: {
                        total: 0,
                        withEmail: 0,
                        withPhone: 0
                    },
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
                    
                    try {
                        // Форматируем дату для API (Y-m-d)
                        const date = new Date(this.searchForm.date);
                        const formattedDate = date.toISOString().split('T')[0];
                        
                        // GET запрос: /races?from={id_from}&to={id_to}&date={DD.MM.YY}
                        // Но API принимает date в формате Y-m-d, а затем конвертирует в DD.MM.YY
                        const response = await fetch(`/api/races?from=${this.searchForm.from}&to=${this.searchForm.to}&date=${formattedDate}`, {
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
                        alert('⚠️ В текущий момент сервис недоступен.\n\nОшибка подключения к API Перевозчика: ' + error.message + '\n\nОбратитесь к администратору для проверки настроек API.');
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
                    if (this.selectedRaces.length === 0) {
                        alert('Выберите хотя бы один рейс');
                        return;
                    }
                    
                    this.creatingTask = true;
                    
                    try {
                        // Получаем полные данные выбранных рейсов
                        const racesData = this.races.filter(race => this.selectedRaces.includes(race.id));
                        
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
                                title: `Рассылка уведомлений - ${new Date().toLocaleDateString('ru-RU')}`,
                                races_data: racesData,
                                template_id: this.notificationForm.templateId || null,
                                custom_message: this.notificationForm.message,
                            }),
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.currentTask = data.data;
                            alert(`✅ Задача создана успешно! ID: ${data.data.id}`);
                        } else {
                            throw new Error(data.message || 'Не удалось создать задачу');
                        }
                    } catch (error) {
                        console.error('Error creating task:', error);
                        alert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при создании задачи: ' + error.message + '\n\nОбратитесь к администратору.');
                    } finally {
                        this.creatingTask = false;
                    }
                },
                
                async loadPassengersForTask() {
                    if (!this.currentTask || !this.currentTask.id) {
                        alert('Сначала создайте задачу');
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
                            this.passengersInfo = {
                                total: data.data.total_loaded || 0,
                                withEmail: 0, // TODO: получить из ответа
                                withPhone: 0  // TODO: получить из ответа
                            };
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
                    
                    if (!confirm(`Отправить уведомления пассажирам?`)) {
                        return;
                    }
                    
                    this.sending = true;
                    
                    try {
                        const response = await fetch(`/api/notification-tasks/${this.currentTask.id}/send`, {
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


