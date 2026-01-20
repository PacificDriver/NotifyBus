<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель оператора</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js"></script>
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
        
        .trip-item.cancelled {
            background: #fff5f5;
            border-left: 4px solid #dc3545;
        }
        
        .trip-item.cancelled:hover {
            border-color: #dc3545;
            background: #ffe0e0;
        }
        
        .trip-item.cancelled.selected {
            background: #ffe0e0;
            border-color: #dc3545;
        }
        
        .trip-item.active {
            background: #f8fff9;
            border-left: 4px solid #2b8a3e;
        }
        
        .trip-item.active:hover {
            border-color: #2b8a3e;
            background: #e6f9e8;
        }
        
        .trip-item.active.selected {
            background: #e6f9e8;
            border-color: #2b8a3e;
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
            background: #2b8a3e;
            color: white;
        }
        
        .badge-warning {
            background: #fff3bf;
            color: #f08c00;
        }
        
        .badge-danger {
            background: #ffe0e0;
            color: #c92a2a;
        }
        
        /* Модальные окна */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 31, 54, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.25s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: #ffffff;
            border-radius: 18px;
            width: min(520px, 92vw);
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(20, 31, 54, 0.25);
            animation: slideUp 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 26px 32px;
            color: #fff;
        }

        .modal-header.success {
            background: linear-gradient(135deg, #64b5f6, #5c7cfa);
        }

        .modal-header.error {
            background: linear-gradient(135deg, #ff6b6b, #f06595);
        }

        .modal-header.warning {
            background: linear-gradient(135deg, #ffd43b, #ffa94d);
            color: #3c2f00;
        }

        .modal-header.info {
            background: linear-gradient(135deg, #74c0fc, #5f5af0);
        }

        .modal-icon {
            font-size: 2.4rem;
            line-height: 1;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }

        .modal-body {
            padding: 28px 32px 12px;
            color: #444;
            font-size: 1rem;
            line-height: 1.6;
        }

        .modal-body p + p {
            margin-top: 12px;
        }

        .modal-footer {
            padding: 18px 32px 26px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .modal-btn {
            padding: 11px 26px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .modal-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(17, 68, 179, 0.16);
        }

        .modal-btn-primary {
            background: #667eea;
            color: #fff;
        }

        .modal-btn-secondary {
            background: #f1f3f5;
            color: #495057;
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
        <h1>
            <a href="/" style="text-decoration: none; color: #667eea;">📱 Панель оператора</a>
        </h1>
        <div style="display: flex; align-items: center; gap: 20px;">
            <a href="/dashboard/drivers" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-block;">
                🚗 Водители
            </a>
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
        <div v-if="modal.visible" class="modal-overlay" @click.self="closeModal">
            <div class="modal-content">
                <div :class="['modal-header', modal.type || 'info']">
                    <div class="modal-icon">@{{ modal.icon }}</div>
                    <div class="modal-title">@{{ modal.title }}</div>
                </div>
                <div class="modal-body" v-html="modal.message"></div>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-primary" @click="closeModal">Понятно</button>
                </div>
            </div>
        </div>
        <!-- Шаг 1: Создание задачи на рассылку -->
        <div class="card" v-if="!currentTask">
            <h2>📨 Создать рассылку уведомлений</h2>
            
            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: #555;">Шаг 1: Создать задачу на рассылку</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Название задачи будет сгенерировано автоматически на основе текущей даты и времени.
                </p>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-success" @click="createTask" :disabled="creatingTask">
                        <span v-if="creatingTask">⏳ Создание...</span>
                        <span v-else>📝 Создать задачу</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Шаг 2: Поиск рейсов -->
        <div class="card" v-if="currentTask && currentTaskRacesCount === 0" ref="stepSearch">
            <h3 style="margin-bottom: 15px; color: #555;">Шаг 2: Найти рейсы</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Задача «<strong>@{{ currentTask.title }}</strong>» создана. Теперь найдите рейсы для рассылки.
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
                        <input
                            type="text"
                            id="trip-date-picker"
                            v-model="searchForm.date"
                            required
                            placeholder="Выберите дату рейса">
                    </div>
                </div>
                <button class="btn btn-primary" @click="searchCancelledRaces" :disabled="searching">
                    <span v-if="searching">⏳ Поиск...</span>
                    <span v-else>🔍 Найти рейсы</span>
                </button>
            
            <!-- Выбор найденных рейсов -->
            <div v-if="races.length > 0" style="margin-top: 30px;">
                <h4 style="margin-bottom: 15px; color: #555;">Шаг 3: Выбор рейсов</h4>
                <div style="padding: 12px; background: #fff3cd; border-left: 4px solid #f08c00; border-radius: 4px; margin-bottom: 15px;">
                    <p style="margin: 0; color: #856404;">
                        <strong>ℹ️ Найдено рейсов: @{{ races.length }}</strong>
                        <span style="margin-left: 10px; color: #c92a2a;">Отмененных: @{{ cancelledCount }}</span>
                        <span style="margin-left: 10px; color: #2b8a3e;">Активных: @{{ activeCount }}</span>
                    </p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <input type="checkbox" @change="toggleAllRaces" :checked="selectedRaces.length === races.length && races.length > 0" style="width: 18px; height: 18px; cursor: pointer;">
                        <strong>Выбрать все (@{{ selectedRaces.length }} из @{{ races.length }})</strong>
                    </label>
                </div>
                
                <div style="padding: 10px;">
                    <div v-for="race in races" :key="race.id" 
                         :class="['trip-item', race.active === false ? 'cancelled' : 'active', { selected: selectedRaces.includes(race.id) }]"
                         style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                        <input type="checkbox" 
                               :value="race.id" 
                               v-model="selectedRaces"
                               style="width: 18px; height: 18px; margin-top: 2px; cursor: pointer;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <strong>Рейс ID: @{{ race.id }}</strong>
                                <span v-if="race.route || race.trip_number" style="color: #666; font-weight: normal;">
                                    (№@{{ race.route || race.trip_number }})
                                </span>
                                <span :class="race.active === false ? 'badge badge-danger' : 'badge badge-success'" style="font-size: 0.75rem; padding: 2px 8px;">
                                    @{{ race.active === false ? '❌ Отменен' : '✅ Активен' }}
                                </span>
                            </div>
                            <div style="margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 0.95rem; font-weight: 600; color: #495057;">
                                    🚌 Рейс №@{{ race.route || race.trip_number || race.id }}: @{{ fromStationName }} → @{{ toStationName }}
                                </div>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.9rem; color: #666;">
                                <div v-if="race.dt_depart" style="margin-bottom: 4px;">
                                    <span style="color: #888;">🕐</span> <strong>Отправление:</strong> @{{ formatDateTime(race.dt_depart, race.route_tz) }}
                                </div>
                                <div v-if="race.dt_arrive" style="margin-bottom: 4px;">
                                    <span style="color: #888;">🕐</span> <strong>Прибытие:</strong> @{{ formatDateTime(race.dt_arrive, race.route_tz) }}
                                </div>
                                <div v-if="race.route_tz !== undefined && race.route_tz !== null" style="margin-bottom: 4px;">
                                    <span style="color: #888;">🌍</span> <strong>Часовой пояс:</strong> UTC+@{{ race.route_tz }}
                                </div>
                                <div v-if="race.provider" style="margin-bottom: 4px;">
                                    <span style="color: #888;">🚌</span> <strong>Провайдер:</strong> @{{ race.provider }}
                                </div>
                                <div style="margin-top: 10px;">
                                    <label style="font-size: 0.9rem; color: #555; font-weight: 600; display: block; margin-bottom: 4px;">Статус рейса:</label>
                                    <select v-model="race.status" 
                                            @change="updateRaceStatus(race)"
                                            style="padding: 6px 10px; border-radius: 6px; border: 2px solid #ddd; font-size: 0.9rem; width: 100%; max-width: 200px;">
                                        <option value="scheduled">Запланирован</option>
                                        <option value="cancelled">Отменен</option>
                                        <option value="delayed">Задержан</option>
                                        <option value="completed">Завершен</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button class="btn btn-success" @click="addRacesToTask" :disabled="selectedRaces.length === 0 || addingRaces">
                        <span v-if="addingRaces">⏳ Добавление...</span>
                        <span v-else>➕ Добавить выбранные рейсы в задачу (@{{ selectedRaces.length }})</span>
                    </button>
                </div>
            </div>
            
            <!-- Сообщение, когда поиск выполнен, но рейсов не найдено -->
            <div v-if="races.length === 0 && searchPerformed && !searching" style="margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 8px; color: #856404;">
                <strong>ℹ️ Рейсов не найдено</strong>
                <div style="margin-top: 10px; font-size: 0.9rem;">
                    <p>Для выбранных параметров рейсов не найдено:</p>
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>Станция отправления: <strong>@{{ getStationName(searchForm.from) }}</strong></li>
                        <li>Станция прибытия: <strong>@{{ getStationName(searchForm.to) }}</strong></li>
                        <li>Дата: <strong>@{{ formatDate(searchForm.date) }}</strong></li>
                    </ul>
                    <p style="margin-top: 10px;">Попробуйте выбрать другую дату или другие станции.</p>
                </div>
            </div>
            
            <!-- Сообщение, когда станции не загружены -->
            <div v-if="stations.length === 0" style="margin-top: 20px; padding: 20px; background: #ffe0e0; border-radius: 8px; color: #c92a2a;">
                <strong>⚠️ Станции не загружены</strong>
                <div style="margin-top: 10px; font-size: 0.9rem;">
                    <p>Не удалось загрузить список станций. Возможные причины:</p>
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>API перевозчика недоступен</li>
                        <li>Станции не синхронизированы</li>
                        <li>Проблемы с настройками API</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Решение:</strong> Обратитесь к администратору для проверки настроек API Перевозчика и синхронизации станций.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Шаг 4: Загрузка пассажиров и отправка -->
        <div class="card" v-if="currentTask && currentTaskRacesCount > 0" ref="stepPassengers">
            <h3 style="margin-bottom: 15px; color: #555;">Шаг 4: Загрузка пассажиров и отправка</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Задача: <strong>@{{ currentTask.title }}</strong><br>
                Рейсов в задаче: <strong>@{{ currentTaskRacesCount }}</strong>
            </p>
            
            <!-- Отображение добавленных рейсов со статусами -->
            <div v-if="currentTask && currentTask.races_data && currentTask.races_data.length > 0" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h5 style="margin-bottom: 10px; color: #555;">Добавленные рейсы:</h5>
                <div v-for="race in currentTask.races_data" :key="race.id" 
                     style="padding: 10px; background: white; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid #667eea;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>Рейс ID:</strong> @{{ race.id }}
                            <span v-if="race.trip_number" style="margin-left: 10px; color: #666;">
                                (@{{ race.trip_number }})
                            </span>
                        </div>
                        <span :class="getStatusBadgeClass(race.status || 'scheduled')">
                            @{{ getStatusLabel(race.status || 'scheduled') }}
                        </span>
                    </div>
                    <div v-if="race.from_station_name && race.to_station_name" style="margin-top: 5px; font-size: 0.9rem; color: #666;">
                        @{{ race.from_station_name }} → @{{ race.to_station_name }}
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div v-if="loadingPassengers" style="padding: 12px; background: #e8f0fe; border-radius: 8px; color: #1a54b3;">
                    ⏳ Загружаем список пассажиров...
                </div>
                <button v-else type="button" class="btn btn-link" style="padding: 0; color: #1a54b3; text-decoration: underline;"
                        @click="loadPassengersForTask({ silent: true })">
                    ↻ Обновить список пассажиров
                </button>
            </div>
            
            <!-- Шаг 5: Выбор пассажиров -->
            <div v-if="passengers.length > 0" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                <h4 style="margin-bottom: 15px; color: #555;">Шаг 5: Выбор/исключение пассажиров</h4>
                <div style="padding: 15px; background: #e8f5e9; border-radius: 8px; margin-bottom: 15px;">
                    <strong>✅ Пассажиры загружены:</strong><br>
                    Всего: @{{ passengers.length }} | С email: @{{ passengersWithEmail }} | С телефоном: @{{ passengersWithPhone }}
                </div>
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
                
                <!-- Шаг 6: Выбор шаблона и отправка -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <h4 style="margin-bottom: 15px; color: #555;">Шаг 6: Выбор шаблона и отправка</h4>
                    
                    <div class="form-group">
                        <label>Выберите шаблон сообщения (или введите свой текст)</label>
                        <select v-model="notificationForm.templateId" style="margin-bottom: 15px;">
                            <option value="">Без шаблона (свой текст)</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                @{{ template.name }}
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Текст сообщения</label>
                        <textarea ref="messageInput"
                                  v-model="notificationForm.message" rows="6" 
                                  placeholder="Введите текст сообщения или выберите шаблон..."></textarea>
                        <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                            <strong style="font-size:0.95rem; color:#555;">Быстрая вставка переменных:</strong>
                            <button v-for="variable in variablesPalette"
                                    :key="`var-${variable.token}`"
                                    type="button"
                                    class="btn btn-secondary"
                                    style="padding:8px 14px; font-size:0.9rem; display:flex; flex-direction:column; align-items:flex-start; gap:2px;"
                                    @click="insertVariable(variable.token)">
                                <span style="font-weight:600;">@{{ variable.label }}</span>
                                <code v-text="variable.token" style="font-size:0.85rem;"></code>
                            </button>
                        </div>
                    </div>

                    <div v-if="snippetTemplates.length" style="margin-top: 20px;">
                        <h5 style="margin-bottom: 10px; color: #555;">Быстрые вставки</h5>
                        <p style="color:#777; margin-bottom:12px;">Нажмите «Добавить», чтобы вставить дополнительный текст в сообщение.</p>
                        <div v-for="snippet in snippetTemplates" :key="`snippet-${snippet.id}`"
                             style="display:flex; gap:12px; align-items:flex-start; margin-bottom:12px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:240px;">
                                <label style="font-weight:600; color:#555;">@{{ snippet.name }}</label>
                                <textarea :value="snippet.body" rows="3" readonly
                                          style="width:100%; margin-top:6px; background:#f8f9fa; border:1px solid #e1e1e1; border-radius:8px; padding:10px; font-size:0.95rem;"></textarea>
                            </div>
                            <button type="button" class="btn btn-secondary" style="height:42px;"
                                    @click="appendSnippet(snippet.body)">
                                ➕ Добавить в сообщение
                            </button>
                        </div>
                    </div>
                    
                    <button class="btn btn-success" @click="sendNotifications" 
                            :disabled="!notificationForm.message || selectedPassengers.length === 0 || sending"
                            style="margin-top: 15px;">
                        <span v-if="sending">⏳ Отправка уведомлений...</span>
                        <span v-else>✉️ Отправить уведомления (@{{ selectedPassengers.length }} пассажирам)</span>
                    </button>
                </div>
            </div>
            
            <!-- Статистика отправки -->
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

        <div class="card">
            <h3 style="margin-bottom: 15px; color: #555;">🕘 История поиска</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Здесь отображаются последние запросы рейсов. Используйте фильтр, чтобы увидеть все запросы или только те, где были найдены рейсы.
            </p>

            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button class="btn" :class="historyFilter === 'all' ? 'btn-primary' : 'btn-secondary'" @click="changeHistoryFilter('all')" style="min-width: 120px;">
                    Все
                </button>
                <button class="btn" :class="historyFilter === 'cancelled' ? 'btn-primary' : 'btn-secondary'" @click="changeHistoryFilter('cancelled')" style="min-width: 120px;">
                    Только отмененные
                </button>
            </div>

            <div v-if="historyLoading" style="padding: 20px; text-align: center; color: #666;">
                ⏳ Загрузка истории...
            </div>

            <div v-else>
                <div v-if="searchHistory.length === 0" style="padding: 20px; background: #f8f9fa; border-radius: 8px; color: #666;">
                    История пока пуста. Выполните поиск рейсов, чтобы запись появилась в списке.
                </div>

                <div v-else style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f0f0f0; text-align: left;">
                                <th style="padding: 10px; border-bottom: 1px solid #ddd;">Когда</th>
                                <th style="padding: 10px; border-bottom: 1px solid #ddd;">Маршрут</th>
                                <th style="padding: 10px; border-bottom: 1px solid #ddd;">Дата рейса</th>
                                <th style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">Найдено рейсов</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in searchHistory" :key="item.id">
                                <td style="padding: 10px; border-bottom: 1px solid #f0f0f0;">
                                    @{{ formatDateTimeLocal(item.created_at) }}
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #f0f0f0;">
                                    <div style="font-weight: 600;">
                                        @{{ item.from_station_name || '—' }} → @{{ item.to_station_name || '—' }}
                                    </div>
                                    <div style="font-size: 0.9rem; color: #666;">
                                        ID: @{{ item.from_station_id || '—' }} → @{{ item.to_station_id || '—' }}
                                    </div>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #f0f0f0;">
                                    @{{ item.trip_date ? formatDate(item.trip_date) : '—' }}
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #f0f0f0; text-align: center;">
                                    <span :class="item.cancelled_count > 0 ? 'badge badge-danger' : 'badge badge-warning'">
                                        @{{ item.cancelled_count }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        @verbatim
        const { createApp } = Vue;
        
        createApp({
            data() {
                const defaultTemplateMessage = 'Ваш рейс №{{trip_number}} {{departure_station}} → {{arrival_station}} на {{departure_time}} отменен.';
                return {
                    stations: [],
                    searchForm: {
                        from: '',
                        to: '',
                        date: new Date().toISOString().split('T')[0]
                    },
                    races: [], // Рейсы из API (активные и отмененные)
                    selectedRaces: [], // Выбранные ID рейсов
                    fromStationName: '', // Название станции отправления
                    toStationName: '', // Название станции прибытия
                    searchPerformed: false,
                    searching: false,
                    currentTask: null,
                    creatingTask: false,
                    addingRaces: false,
                    loadingStations: false,
                    templates: [],
                    passengers: [], // Список всех загруженных пассажиров
                    selectedPassengers: [], // Выбранные ID пассажиров для отправки
                    passengersLoaded: false,
                    loadingPassengers: false,
                    autoLoadingPassengers: false,
                    sending: false,
                    defaultTemplateMessage,
                    variablesPalette: [
                        { label: 'Номер рейса', token: '{{trip_number}}' },
                        { label: 'Станция отправления', token: '{{departure_station}}' },
                        { label: 'Станция прибытия', token: '{{arrival_station}}' },
                        { label: 'Время отправления', token: '{{departure_time}}' },
                    ],
                    notificationForm: {
                        templateId: '',
                        message: defaultTemplateMessage
                    },
                    stats: {
                        total: 0,
                        sent: 0,
                        pending: 0,
                        failed: 0
                    },
                    searchHistory: [],
                    historyFilter: 'all',
                    historyLoading: false,
                    historyPagination: null,
                    datePickerInstance: null,
                    modal: {
                        visible: false,
                        type: 'info',
                        icon: 'ℹ️',
                        title: 'Сообщение',
                        message: ''
                    }
                }
            },
            computed: {
                cancelledCount() {
                    return this.races.filter(race => race.active === false).length;
                },
                activeCount() {
                    return this.races.filter(race => race.active !== false).length;
                },
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
                },
                snippetTemplates() {
                    const braceRegex = /{{.*?}}/;
                    return this.templates.filter((template) => {
                        if (!template || !template.body) {
                            return false;
                        }
                        const body = String(template.body);
                        return !braceRegex.test(body);
                    });
                }
            },
            watch: {
                'searchForm.date'(newVal) {
                    if (this.datePickerInstance && newVal) {
                        const currentValue = this.datePickerInstance.input.value;
                        if (currentValue !== newVal) {
                            this.datePickerInstance.setDate(newVal, false);
                        }
                    }
                },
                'notificationForm.templateId'(newValue) {
                    this.applyTemplateContent(newValue);
                },
                currentTask() {
                    this.$nextTick(() => this.initDatePicker());
                },
                currentTaskRacesCount(newVal, oldVal) {
                    if (newVal === 0) {
                        this.$nextTick(() => this.initDatePicker());
                    } else {
                        this.destroyDatePicker();
                    }

                    if (newVal > 0 && newVal !== oldVal) {
                        this.autoLoadPassengers(true);
                    }
                }
            },
            mounted() {
                this.loadStations();
                this.loadTemplates();
                this.loadSearchHistory();
                window.addEventListener('keydown', this.handleKeydown);
                this.$nextTick(() => this.initDatePicker());
            },
            beforeUnmount() {
                window.removeEventListener('keydown', this.handleKeydown);
                this.destroyDatePicker();
            },
            methods: {
                scrollToSection(refName) {
                    const target = this.$refs[refName];
                    if (target && typeof target.scrollIntoView === 'function') {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                },
                closeModal() {
                    this.modal.visible = false;
                },
                handleKeydown(event) {
                    if (event.key === 'Escape' && this.modal.visible) {
                        this.closeModal();
                    }
                },
                showModal({ title = 'Сообщение', message = '', type = 'info' } = {}) {
                    const allowedTypes = ['success', 'error', 'warning', 'info'];
                    const icons = {
                        success: '✅',
                        error: '⛔',
                        warning: '⚠️',
                        info: 'ℹ️'
                    };

                    const safeType = allowedTypes.includes(type) ? type : 'info';
                    this.modal.type = safeType;
                    this.modal.icon = icons[safeType] || icons.info;
                    this.modal.title = title || 'Сообщение';
                    this.modal.message = (message ?? '').toString().replace(/\n/g, '<br>');
                    this.modal.visible = true;
                },
                initDatePicker() {
                    if (typeof flatpickr === 'undefined') {
                        console.warn('Flatpickr не загружен');
                        return;
                    }
                    const input = document.getElementById('trip-date-picker');
                    if (!input) {
                        return;
                    }
                    if (this.datePickerInstance) {
                        this.datePickerInstance.destroy();
                        this.datePickerInstance = null;
                    }
                    if (flatpickr.l10ns?.ru) {
                        flatpickr.localize(flatpickr.l10ns.ru);
                    }
                    this.datePickerInstance = flatpickr(input, {
                        defaultDate: this.searchForm.date,
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd.m.Y',
                        disableMobile: true,
                        onChange: (_, dateStr) => {
                            this.searchForm.date = dateStr;
                        }
                    });
                },
                destroyDatePicker() {
                    if (this.datePickerInstance) {
                        this.datePickerInstance.destroy();
                        this.datePickerInstance = null;
                    }
                },
                applyTemplateContent(templateId) {
                    if (!templateId) {
                        this.notificationForm.message = this.defaultTemplateMessage;
                        return;
                    }

                    const selectedTemplate = this.templates.find(template => String(template.id) === String(templateId));
                    if (selectedTemplate) {
                        this.notificationForm.message = selectedTemplate.body || this.defaultTemplateMessage;
                    } else {
                        this.notificationForm.message = this.defaultTemplateMessage;
                    }
                },
                appendSnippet(text) {
                    if (!text) {
                        return;
                    }
                    const snippet = text.trim();
                    if (!snippet) {
                        return;
                    }

                    const current = this.notificationForm.message?.trim() || '';
                    this.notificationForm.message = current
                        ? `${current}\n\n${snippet}`
                        : snippet;
                },
                async autoLoadPassengers(scrollAfter = false) {
                    if (this.loadingPassengers) {
                        return;
                    }
                    this.autoLoadingPassengers = true;
                    try {
                        await this.loadPassengersForTask({ silent: true });
                        if (scrollAfter) {
                            this.$nextTick(() => this.scrollToSection('stepPassengers'));
                        }
                    } catch (error) {
                        console.error('Auto load passengers failed:', error);
                    } finally {
                        this.autoLoadingPassengers = false;
                    }
                },
                insertVariable(variable) {
                    if (!variable) {
                        return;
                    }
                    const textarea = this.$refs.messageInput;
                    if (!textarea) {
                        // Подстраховка: добавляем в конец
                        this.notificationForm.message = `${this.notificationForm.message || ''}${variable}`;
                        return;
                    }

                    const { selectionStart = 0, selectionEnd = 0, value = '' } = textarea;
                    const before = value.slice(0, selectionStart);
                    const after = value.slice(selectionEnd);
                    this.notificationForm.message = `${before}${variable}${after}`;

                    this.$nextTick(() => {
                        const position = selectionStart + variable.length;
                        textarea.focus();
                        textarea.setSelectionRange(position, position);
                    });
                },
                async loadStations() {
                    this.loadingStations = true;
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
                            const errorData = await response.json().catch(() => ({}));
                            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        if (data.success && data.data) {
                            this.stations = Array.isArray(data.data) ? data.data : [];
                            
                            if (this.stations.length === 0) {
                                console.warn('Список станций пуст. Возможно, нужно синхронизировать станции.');
                            } else {
                                console.log(`Загружено станций: ${this.stations.length}`);
                            }
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить станции');
                        }
                    } catch (error) {
                        console.error('Error loading stations:', error);
                        
                        let errorMessage = '⚠️ Ошибка загрузки станций\n\n';
                        errorMessage += error.message || 'Неизвестная ошибка';
                        errorMessage += '\n\n💡 Совет: Обратитесь к администратору для проверки настроек API Перевозчика.';
                        errorMessage += '\n\nЕсли станции не загружаются, возможно, нужно синхронизировать их в админ-панели.';
                        this.showModal({
                            type: 'error',
                            title: 'Ошибка загрузки станций',
                            message: errorMessage
                        });
                        this.stations = [];
                    } finally {
                        this.loadingStations = false;
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
                            if (this.notificationForm.templateId) {
                                this.applyTemplateContent(this.notificationForm.templateId);
                            }
                        }
                    } catch (error) {
                        console.error('Error loading templates:', error);
                        // Не показываем alert для шаблонов, так как это не критично
                        this.templates = [];
                    }
                },
                
                async searchCancelledRaces() {
                    if (!this.searchForm.from || !this.searchForm.to || !this.searchForm.date) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нужно заполнить все поля',
                            message: 'Пожалуйста, выберите станцию отправления, станцию прибытия и дату перед поиском рейсов.'
                        });
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
                        
                        // GET запрос: /races/all?from={id_from}&to={id_to}&date={Y-m-d}
                        // API возвращает все рейсы (активные и отмененные)
                        response = await fetch(`/api/races/all?from=${this.searchForm.from}&to=${this.searchForm.to}&date=${formattedDate}`, {
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
                            // Сохраняем данные для обработки в catch блоке
                            const error = new Error(data.message || data.error || `HTTP ${response.status}: ${response.statusText}`);
                            error.responseData = data; // Сохраняем данные ответа
                            throw error;
                        }
                        
                        if (data.success) {
                            // Проверяем, что data.data существует и является массивом
                            const racesData = Array.isArray(data.data) ? data.data : [];
                            
                            // Сохраняем названия станций из ответа API
                            this.fromStationName = data.from_station?.name || '';
                            this.toStationName = data.to_station?.name || '';
                            
                            if (racesData.length === 0) {
                                // Если массив пустой, это нормально - просто нет рейсов
                                this.races = [];
                                this.searchPerformed = true;
                                return; // Выходим, не показывая ошибку
                            }
                            
                            // Дедуплицируем рейсы по id_route
                            // Если есть рейс с to_id равным выбранной станции, выбираем его, иначе первый
                            const racesMap = new Map();
                            const selectedToId = String(this.searchForm.to || '');
                            
                            racesData.forEach(race => {
                                const routeId = String(race.id_route || race.id || '');
                                if (!racesMap.has(routeId)) {
                                    racesMap.set(routeId, race);
                                } else {
                                    // Если текущий рейс соответствует выбранной станции прибытия, заменяем
                                    const currentToId = String(race.to_id || '');
                                    if (currentToId === selectedToId) {
                                        racesMap.set(routeId, race);
                                    }
                                }
                            });
                            const uniqueRaces = Array.from(racesMap.values());
                            
                            // Фильтруем только по наличию обязательных полей и инициализируем статус
                            this.races = uniqueRaces.filter(race => {
                                return race.id && (race.dt_depart || race.dt_arrive);
                            }).map(race => ({
                                ...race,
                                status: race.status || (race.active === false ? 'cancelled' : 'scheduled') // Инициализация статуса
                            }));
                            
                            // Сортируем: сначала отмененные (active = false), потом активные (active = true)
                            this.races.sort((a, b) => {
                                if (a.active === false && b.active !== false) return -1;
                                if (a.active !== false && b.active === false) return 1;
                                return 0;
                            });
                            
                            if (this.races.length > 0) {
                                const cancelledCount = this.races.filter(race => race.active === false).length;
                                const activeCount = this.races.filter(race => race.active !== false).length;
                                console.log(`Найдено рейсов: ${this.races.length} (отмененных: ${cancelledCount}, активных: ${activeCount})`);
                            }
                        } else {
                            // Если success = false, но нет ошибки в response.ok
                            throw new Error(data.message || data.error || 'Неизвестная ошибка при поиске рейсов');
                        }
                    } catch (error) {
                        console.error('Error searching races:', error);
                        
                        // Получаем детали ошибки из ответа
                        let errorMessage = 'Неизвестная ошибка';
                        const errorData = error.responseData || {}; // Используем сохраненные данные из ответа
                        
                        // Пытаемся получить детали из ответа сервера
                        if (response && !response.ok) {
                            errorMessage = error.message || errorData.message || errorData.error || `HTTP ${response.status}: ${response.statusText}`;
                        } else if (error.message) {
                            errorMessage = error.message;
                        }
                        
                        // Формируем понятное сообщение для пользователя
                        let userMessage = '⚠️ Ошибка подключения к API Перевозчика\n\n';
                        userMessage += errorMessage + '\n\n';
                        
                        // Добавляем детали из ответа сервера, если есть
                        if (errorData.details) {
                            userMessage += 'Детали: ' + errorData.details + '\n\n';
                        }
                        
                        // Добавляем подсказки в зависимости от типа ошибки
                        if (errorMessage.includes('external_id') || errorMessage.includes('синхронизированы') || errorMessage.includes('синхронизируйте')) {
                            userMessage += '💡 Совет: Станции должны быть синхронизированы с API перевозчика.\n';
                            userMessage += 'Перейдите в админ-панель → Настройки → API Перевозчика и нажмите кнопку "Обновить станции".';
                        } else if (errorMessage.includes('400') || errorMessage.includes('Некорректный запрос')) {
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
                        
                        // Добавляем hint из ответа сервера, если есть
                        if (errorData.hint) {
                            userMessage += '\n\n' + errorData.hint;
                        }
                        
                        this.showModal({
                            type: 'error',
                            title: 'Ошибка запроса к API перевозчика',
                            message: userMessage
                        });
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
                        // Название задачи генерируется автоматически на сервере
                        const response = await fetch('/api/notification-tasks', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                            body: JSON.stringify({}),
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.currentTask = data.data;
                            this.$nextTick(() => this.scrollToSection('stepSearch'));
                        } else {
                            throw new Error(data.message || 'Не удалось создать задачу');
                        }
                    } catch (error) {
                        console.error('Error creating task:', error);
                        this.showModal({
                            type: 'error',
                            title: 'Ошибка при создании задачи',
                            message: '⚠️ Ошибка при создании задачи: ' + (error.message || 'Неизвестная ошибка') + '\n\nОбратитесь к администратору.'
                        });
                    } finally {
                        this.creatingTask = false;
                    }
                },
                
                async addRacesToTask() {
                    if (!this.currentTask || !this.currentTask.id) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нет активной задачи',
                            message: 'Сначала создайте задачу, чтобы добавить в неё рейсы.'
                        });
                        return;
                    }
                    
                    if (this.selectedRaces.length === 0) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нет выбранных рейсов',
                            message: 'Выберите хотя бы один рейс, чтобы добавить его в задачу.'
                        });
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
                            this.currentTask = data.data.task;
                            this.selectedRaces = [];
                            await this.autoLoadPassengers(true);
                        } else {
                            throw new Error(data.message || 'Не удалось добавить рейсы');
                        }
                    } catch (error) {
                        console.error('Error adding races to task:', error);
                        this.showModal({
                            type: 'error',
                            title: 'Ошибка при добавлении рейсов',
                            message: '⚠️ Ошибка при добавлении рейсов: ' + (error.message || 'Неизвестная ошибка') + '\n\nОбратитесь к администратору.'
                        });
                    } finally {
                        this.addingRaces = false;
                    }
                },
                
                async loadPassengersForTask(options = {}) {
                    const { silent = false } = options;
                    if (!this.currentTask || !this.currentTask.id) {
                        if (!silent) {
                            this.showModal({
                                type: 'warning',
                                title: 'Нет активной задачи',
                                message: 'Сначала создайте задачу, чтобы загрузить пассажиров.'
                            });
                        }
                        return;
                    }
                    
                    if (this.currentTaskRacesCount === 0) {
                        if (!silent) {
                            this.showModal({
                                type: 'warning',
                                title: 'Нет рейсов в задаче',
                                message: 'Добавьте рейсы в задачу, прежде чем загружать пассажиров.'
                            });
                        }
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
                            await this.fetchPassengersList();
                            this.passengersLoaded = true;
                            if (!silent) {
                                this.showModal({
                                    type: 'success',
                                    title: 'Пассажиры загружены',
                                    message: `Загружено пассажиров: ${data.data.saved_count || 0}`
                                });
                            }
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить пассажиров');
                        }
                    } catch (error) {
                        console.error('Error loading passengers:', error);
                        if (!silent) {
                            this.showModal({
                                type: 'error',
                                title: 'Ошибка загрузки пассажиров',
                                message: '⚠️ В текущий момент сервис недоступен.\n\nОшибка при загрузке пассажиров: ' + (error.message || 'Неизвестная ошибка') + '\n\nОбратитесь к администратору для проверки настроек внешней БД.'
                            });
                        }
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
                getStationName(stationId) {
                    if (!stationId) return 'Не выбрана';
                    const station = this.stations.find(s => s.id == stationId);
                    return station ? station.name : `ID: ${stationId}`;
                },
                
                formatDate(dateString) {
                    if (!dateString) return 'Не выбрана';
                    try {
                        const date = new Date(dateString);
                        return date.toLocaleDateString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        });
                    } catch (e) {
                        return dateString;
                    }
                },
                
                formatDateTime(datetime, routeTz = null) {
                    if (!datetime) return 'N/A';
                    try {
                        let value = datetime;

                        if (typeof value === 'string') {
                            const trimmed = value.trim();
                            if (trimmed && !/[Zz]|[+\-]\d\d:?\d\d$/.test(trimmed)) {
                                value = trimmed.replace(' ', 'T') + 'Z';
                            }
                        }

                        const date = new Date(value);

                        if (Number.isNaN(date.getTime())) {
                            return datetime;
                        }
                        
                        const offset = Number.isFinite(routeTz) ? routeTz : 11;
                        if (Number.isFinite(offset)) {
                            date.setUTCHours(date.getUTCHours() + offset);
                        }

                        return date.toLocaleString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            timeZone: 'UTC' // Учитываем, что дата в UTC
                        });
                    } catch (e) {
                        console.warn('Error formatting datetime:', datetime, e);
                        return datetime;
                    }
                },
                formatDateTimeLocal(datetime) {
                    if (!datetime) return '—';
                    try {
                        const date = new Date(datetime);
                        if (Number.isNaN(date.getTime())) {
                            return datetime;
                        }
                        // Конвертируем в Asia/Sakhalin (UTC+11) и вычитаем 3 часа
                        const sakhalinTime = new Date(date.getTime() + (11 * 60 * 60 * 1000) - (3 * 60 * 60 * 1000));
                        return sakhalinTime.toLocaleString('ru-RU', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            timeZone: 'UTC'
                        });
                    } catch (e) {
                        return datetime;
                    }
                },
                async loadSearchHistory(page = 1) {
                    this.historyLoading = true;
                    try {
                        const response = await fetch(`/api/search-history?filter=${this.historyFilter}&page=${page}&per_page=25`, {
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
                            this.searchHistory = data.data.data || data.data;
                            this.historyPagination = {
                                current_page: data.data.current_page || 1,
                                last_page: data.data.last_page || 1,
                            };
                        }
                    } catch (error) {
                        console.error('Error loading search history:', error);
                        this.searchHistory = [];
                        this.historyPagination = null;
                    } finally {
                        this.historyLoading = false;
                    }
                },
                changeHistoryFilter(filter) {
                    if (this.historyFilter !== filter) {
                        this.historyFilter = filter;
                        this.loadSearchHistory();
                    }
                },
                async sendNotifications() {
                    if (!this.currentTask || !this.currentTask.id) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нет активной задачи',
                            message: 'Сначала создайте задачу и загрузите пассажиров.'
                        });
                        return;
                    }
                    
                    if (!this.passengersLoaded) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нет данных пассажиров',
                            message: 'Сначала загрузите список пассажиров для выбранной задачи.'
                        });
                        return;
                    }
                    
                    if (this.selectedPassengers.length === 0) {
                        this.showModal({
                            type: 'warning',
                            title: 'Нет выбранных пассажиров',
                            message: 'Выберите хотя бы одного пассажира перед отправкой уведомлений.'
                        });
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
                            this.showModal({
                                type: 'success',
                                title: 'Уведомления запущены',
                                message: `Задача: ${this.currentTask.id}\nПолучателей: ${data.total_recipients || 0}\nУведомления поставлены в очередь.`
                            });
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
                        this.showModal({
                            type: 'error',
                            title: 'Ошибка при отправке уведомлений',
                            message: '⚠️ В текущий момент сервис недоступен.\n\nОшибка при отправке уведомлений: ' + (error.message || 'Неизвестная ошибка') + '\n\nОбратитесь к администратору для проверки настроек WhatsApp и Email.'
                        });
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
                },
                updateRaceStatus(race) {
                    // Обновляем статус в локальном массиве races
                    const raceIndex = this.races.findIndex(r => r.id === race.id);
                    if (raceIndex !== -1) {
                        this.races[raceIndex].status = race.status;
                    }
                },
                getStatusLabel(status) {
                    const labels = {
                        'scheduled': 'Запланирован',
                        'cancelled': 'Отменен',
                        'delayed': 'Задержан',
                        'completed': 'Завершен'
                    };
                    return labels[status] || status;
                },
                getStatusBadgeClass(status) {
                    const classes = {
                        'scheduled': 'badge badge-success',
                        'cancelled': 'badge badge-danger',
                        'delayed': 'badge badge-warning',
                        'completed': 'badge badge-success'
                    };
                    return classes[status] || 'badge';
                }
            }
        }).mount('#app');
        @endverbatim
    </script>
</body>
</html>


