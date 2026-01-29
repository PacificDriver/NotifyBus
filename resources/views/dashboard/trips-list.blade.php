<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Список рейсов</title>
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
        
        .header-links {
            display: flex;
            gap: 15px;
        }
        
        .header-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .header-links a:hover {
            background: #f0f4ff;
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
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus {
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
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
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
        
        .badge-danger {
            background: #ffe0e0;
            color: #c92a2a;
        }
        
        .badge-info {
            background: #e7f3ff;
            color: #1a54b3;
        }
        
        .badge-warning {
            background: #fff3bf;
            color: #f08c00;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 3px 6px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.85rem;
            line-height: 1.2;
        }
        
        td {
            padding: 3px 6px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
            line-height: 1.2;
        }
        
        tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: #e8f0ff;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error-state {
            padding: 20px;
            background: #ffe0e0;
            border-radius: 8px;
            color: #c92a2a;
        }
        
        .system-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .system-rfbas {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .system-artmark {
            background: #fff3e0;
            color: #f57c00;
        }
        
        /* Контекстное меню */
        .context-menu {
            position: fixed;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 8px 0;
            min-width: 200px;
            z-index: 1000;
        }
        
        .context-menu-item {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .context-menu-item:hover {
            background: #f0f4ff;
        }
        
        .context-menu-icon {
            font-size: 16px;
        }
        
        tbody tr {
            cursor: pointer;
        }
        
        /* Модальное окно */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 1600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .modal-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .modal-header h2 {
            color: #667eea;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .manifest-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
        }
        
        .manifest-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .manifest-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .manifest-info-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .manifest-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }
        
        .manifest-value {
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }
        
        .control-cell {
            cursor: pointer;
            user-select: none;
            text-align: center;
            padding: 10px 8px;
            transition: background 0.2s;
        }
        
        .control-cell:hover {
            background: #e8f0ff;
        }
        
        .control-checked {
            color: #2b8a3e;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .control-unchecked {
            color: #999;
        }
        
        @media (max-width: 768px) {
            .manifest-info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .modal-content {
                max-width: 100%;
                max-height: 100vh;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="header">
            <h1>🚌 Список рейсов</h1>
            <div class="header-links">
                <a href="/dashboard">← Назад к панели</a>
            </div>
        </div>
        
        <div class="container">
            <!-- Форма фильтрации -->
            <div class="card">
                <h2>Фильтры поиска</h2>
                <div class="grid">
                    <div class="form-group">
                        <label for="fromStation">Станция отправления</label>
                        <select id="fromStation" v-model="filters.from" @change="onMainFilterChange">
                            <option value="">Выберите станцию...</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">
                                @{{ station.name }}
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="toStation">Станция прибытия</label>
                        <select id="toStation" v-model="filters.to" @change="onMainFilterChange">
                            <option value="">Выберите станцию...</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">
                                @{{ station.name }}
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tripDate">Дата рейса</label>
                        <input type="text" id="tripDate" v-model="filters.date" placeholder="Выберите дату..." readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="routeNumber">Номер рейса (Маршрут)</label>
                        <input type="text" id="routeNumber" v-model="filters.routeNumber" @input="onFilterChange" placeholder="Введите номер рейса...">
                    </div>
                </div>
            </div>
            
            <!-- Таблица рейсов -->
            <div class="card" v-if="filters.from && filters.to && filters.date">
                <h2>Рейсы (@{{ filteredTrips.length }})<span v-if="filters.routeNumber && filteredTrips.length !== trips.length" style="color: #666; font-size: 0.9rem; font-weight: normal;"> из @{{ trips.length }}</span></h2>
                
                <div v-if="loading" class="loading">
                    ⏳ Загрузка рейсов...
                </div>
                
                <div v-else-if="error" style="padding: 20px; background: #ffe0e0; border-radius: 8px; color: #c92a2a;">
                    <strong>⚠️ Ошибка:</strong> @{{ error }}
                </div>
                
                <div v-else-if="trips.length === 0" class="empty-state">
                    <p>Рейсов не найдено для выбранных параметров</p>
                </div>
                
                <div v-else class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Время отправления</th>
                                <th>Время прибытия</th>
                                <th>Маршрут (Номер)</th>
                                <th>Рейс (Откуда-Куда)</th>
                                <th>Перевозчик</th>
                                <th>Продано</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="trip in filteredTrips" 
                                :key="trip.id" 
                                @click="goToTripDetails(trip)"
                                @contextmenu.prevent="showContextMenu($event, trip)">
                                <td style="font-weight: 600;">@{{ formatTime(trip.dt_depart) }}</td>
                                <td style="font-weight: 600;">@{{ formatTime(trip.dt_arrive) }}</td>
                                <td style="font-weight: 700; color: #667eea;">@{{ trip.route || trip.route_number || trip.id || '—' }}</td>
                                <td style="white-space: nowrap;">
                                    <span style="font-weight: 600;">@{{ trip.from_station_name || trip.route_start || '—' }}</span>
                                    <span style="color: #667eea; margin: 0 4px;">→</span>
                                    <span style="font-weight: 600;">@{{ trip.to_station_name || trip.route_end || '—' }}</span>
                                    <span v-if="getSystemName(trip)" :class="['system-badge', getSystemClass(trip)]" style="margin-left: 6px;">
                                        @{{ getSystemName(trip) }}
                                    </span>
                                </td>
                                <td>@{{ trip.perevoz || trip.carrier_name || '—' }}</td>
                                <td>
                                    <span v-if="trip.total_seats || trip.seats">
                                    @{{ trip.sold_tickets || trip.sold_seats || trip.passengers_count || 0 }} / @{{ trip.total_seats || trip.seats }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div v-else class="card">
                <div class="empty-state">
                    <p>Выберите станции отправления, прибытия и дату для отображения рейсов</p>
                </div>
            </div>
        </div>
        
        <!-- Контекстное меню -->
        <div v-if="contextMenu.show" 
             class="context-menu" 
             :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
             @click="hideContextMenu">
            <div class="context-menu-item" @click="downloadManifestPdf">
                <span class="context-menu-icon">📄</span>
                <span>Посадочная ведомость (PDF)</span>
            </div>
        </div>
        
        <!-- Модальное окно посадочной ведомости -->
        <div v-if="manifestModal.show" class="modal-overlay" @click.self="closeManifestModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>📋 Ведомость рейса</h2>
                    <button class="modal-close" @click="closeManifestModal" title="Закрыть">×</button>
                </div>
                <div class="modal-body">
                    <div class="manifest-card" v-if="manifestModal.trip">
                        <h2 class="manifest-title">ПОСАДОЧНАЯ ВЕДОМОСТЬ</h2>
                        <div class="manifest-info-grid">
                            <div class="manifest-info-row">
                                <span class="manifest-label">Сформировано:</span>
                                <span class="manifest-value">@{{ formatManifestDate() }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Диспетчер:</span>
                                <span class="manifest-value">{{ auth()->user()->name ?? '—' }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Перевозчик:</span>
                                <span class="manifest-value">@{{ manifestModal.trip.perevoz || manifestModal.trip.carrier_name || '—' }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Дата и время отправления:</span>
                                <span class="manifest-value">@{{ formatDepartureDateTime() }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Водители:</span>
                                <span class="manifest-value">@{{ formatDrivers() }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Транспортное средство:</span>
                                <span class="manifest-value">@{{ formatVehicle() }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Рейс:</span>
                                <span class="manifest-value">@{{ formatRouteInfo() }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Путевой лист No:</span>
                                <span class="manifest-value">@{{ manifestModal.trip.waybill_number || manifestModal.trip.waybill || '—' }}</span>
                            </div>
                            <div class="manifest-info-row">
                                <span class="manifest-label">Станция отправления:</span>
                                <span class="manifest-value">@{{ manifestModal.trip.from_station_name || manifestModal.trip.route_start || '—' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2>Пассажиры (@{{ manifestModal.passengers.length }})</h2>
                        
                        <div v-if="manifestModal.loading" class="loading">
                            ⏳ Загрузка пассажиров...
                        </div>
                        
                        <div v-else-if="manifestModal.error" class="error-state">
                            <strong>⚠️ Ошибка:</strong> @{{ manifestModal.error }}
                        </div>
                        
                        <div v-else class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Место</th>
                                        <th>Вид</th>
                                        <th>Серия</th>
                                        <th>Номер</th>
                                        <th>Ст.отправления</th>
                                        <th>Ст.назначения</th>
                                        <th>Тариф</th>
                                        <th>Пассажир</th>
                                        <th>Контр.</th>
                                        <th>Тел.</th>
                                        <th>Кассир</th>
                                        <th>Регистрация</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="manifestModal.passengers.length === 0">
                                        <td colspan="12" style="text-align: center; padding: 40px; color: #666;">
                                            Пассажиры не найдены для данного рейса
                                        </td>
                                    </tr>
                                    <tr v-for="(passenger, index) in manifestModal.passengers" :key="passenger.id || index">
                                        <td>@{{ getSeatNumber(passenger) }}</td>
                                        <td>@{{ formatPassengerType(passenger) }}</td>
                                        <td>@{{ passenger.document_series || '—' }}</td>
                                        <td>@{{ passenger.document_number || '—' }}</td>
                                        <td>@{{ manifestModal.trip?.from_station_name || manifestModal.trip?.route_start || '—' }}</td>
                                        <td>@{{ manifestModal.trip?.to_station_name || manifestModal.trip?.route_end || '—' }}</td>
                                        <td>@{{ getTicketPrice(passenger) }}</td>
                                        <td>@{{ formatFullName(passenger) }}</td>
                                        <td class="control-cell" @click="toggleCheckIn(passenger)">
                                            <span v-if="isCheckedIn(passenger)" class="control-checked">✓</span>
                                            <span v-else class="control-unchecked">—</span>
                                        </td>
                                        <td>@{{ formatPhone(passenger.phone) }}</td>
                                        <td>—</td>
                                        <td>@{{ formatDateTime(passenger.ticket_purchased_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                    trips: [],
                    loading: false,
                    error: null,
                    filters: {
                        from: '',
                        to: '',
                        date: '',
                        routeNumber: ''
                    },
                    allTrips: [], // Все загруженные рейсы (до фильтрации по номеру)
                    datePicker: null,
                    contextMenu: {
                        show: false,
                        x: 0,
                        y: 0,
                        trip: null
                    },
                    manifestModal: {
                        show: false,
                        trip: null,
                        manifest: null,
                        passengers: [],
                        loading: false,
                        error: null
                    }
                };
            },
            computed: {
                filteredTrips() {
                    if (!this.filters.routeNumber || this.filters.routeNumber.trim() === '') {
                        return this.trips;
                    }
                    
                    const searchTerm = this.filters.routeNumber.trim().toLowerCase();
                    return this.trips.filter(trip => {
                        // Ищем по номеру маршрута в разных полях
                        const route = String(trip.route || trip.route_number || trip.id || '').toLowerCase();
                        const routeNumber = String(trip.route_number || '').toLowerCase();
                        const tripNumber = String(trip.trip_number || '').toLowerCase();
                        const id = String(trip.id || '').toLowerCase();
                        
                        return route.includes(searchTerm) || 
                               routeNumber.includes(searchTerm) || 
                               tripNumber.includes(searchTerm) ||
                               id.includes(searchTerm);
                    });
                }
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
                            this.stations = Array.isArray(data.data) ? data.data : [];
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить станции');
                        }
                    } catch (error) {
                        console.error('Error loading stations:', error);
                        this.error = 'Ошибка загрузки станций: ' + error.message;
                    }
                },
                
                initDatePicker() {
                    this.$nextTick(() => {
                        const dateInput = document.getElementById('tripDate');
                        if (dateInput && !this.datePicker) {
                            this.datePicker = flatpickr(dateInput, {
                                locale: 'ru',
                                dateFormat: 'Y-m-d',
                                defaultDate: new Date(),
                                onChange: (selectedDates, dateStr) => {
                                    this.filters.date = dateStr;
                                    this.onMainFilterChange();
                                }
                            });
                            // Устанавливаем сегодняшнюю дату по умолчанию
                            this.filters.date = new Date().toISOString().split('T')[0];
                        }
                    });
                },
                
                onMainFilterChange() {
                    // Перезагружаем данные при изменении основных фильтров (from, to, date)
                    if (this.filters.from && this.filters.to && this.filters.date) {
                        this.loadTrips();
                    } else {
                        this.trips = [];
                        this.allTrips = [];
                        this.error = null;
                    }
                },
                
                onFilterChange() {
                    // Для routeNumber фильтрация происходит автоматически через computed свойство filteredTrips
                    // Ничего не делаем, просто позволяем Vue обновить computed свойство
                },
                
                async loadTrips() {
                    if (!this.filters.from || !this.filters.to || !this.filters.date) {
                        return;
                    }
                    
                    this.loading = true;
                    this.error = null;
                    this.trips = [];
                    
                    try {
                        const formattedDate = this.filters.date;
                        const response = await fetch(`/api/races/all?from=${this.filters.from}&to=${this.filters.to}&date=${formattedDate}`, {
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
                            this.trips = Array.isArray(data.data) ? data.data : [];
                            // Сортируем по времени отправления
                            this.trips.sort((a, b) => {
                                const timeA = a.dt_depart ? new Date(a.dt_depart).getTime() : 0;
                                const timeB = b.dt_depart ? new Date(b.dt_depart).getTime() : 0;
                                return timeA - timeB;
                            });
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить рейсы');
                        }
                    } catch (error) {
                        console.error('Error loading trips:', error);
                        this.error = error.message || 'Ошибка загрузки рейсов';
                        this.trips = [];
                    } finally {
                        this.loading = false;
                    }
                },
                
                formatTime(dateTime) {
                    if (!dateTime) return '—';
                    const date = new Date(dateTime);
                    return date.toLocaleTimeString('ru-RU', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },
                
                getSystemName(trip) {
                    // Определяем систему из поля в данных рейса
                    // Приоритет: provider > system > data_source
                    if (trip.provider) {
                        const provider = String(trip.provider).trim();
                        if (provider === 'РФБАС' || provider.toUpperCase() === 'RFBAS') {
                            return 'РФБАС';
                        }
                        if (provider === 'АРТМАРК' || provider.toUpperCase() === 'ARTMARK') {
                            return 'АРТМАРК';
                        }
                        // Если уже в правильном формате, возвращаем как есть
                        return provider;
                    }
                    if (trip.system) {
                        const system = String(trip.system).toUpperCase();
                        if (system === 'RFBAS' || system === 'РФБАС') {
                            return 'РФБАС';
                        }
                        if (system === 'ARTMARK' || system === 'АРТМАРК') {
                            return 'АРТМАРК';
                        }
                        // Если уже в правильном формате, возвращаем как есть
                        if (system === 'РФБАС' || system === 'АРТМАРК') {
                            return trip.system;
                        }
                    }
                    if (trip.data_source) {
                        const source = String(trip.data_source).toUpperCase();
                        if (source === 'RFBAS' || source === 'РФБАС') {
                            return 'РФБАС';
                        }
                        if (source === 'ARTMARK' || source === 'АРТМАРК') {
                            return 'АРТМАРК';
                        }
                    }
                    return null;
                },
                
                getSystemClass(trip) {
                    const system = this.getSystemName(trip);
                    if (system === 'РФБАС') return 'system-rfbas';
                    if (system === 'АРТМАРК') return 'system-artmark';
                    return '';
                },
                
                goToTripDetails(trip) {
                    // Открываем модальное окно вместо перенаправления
                    this.openManifestModal(trip);
                },
                
                openManifestModal(trip) {
                    this.manifestModal.show = true;
                    this.manifestModal.trip = trip;
                    this.manifestModal.passengers = [];
                    this.manifestModal.manifest = null;
                    this.manifestModal.error = null;
                    this.loadManifest();
                },
                
                closeManifestModal() {
                    this.manifestModal.show = false;
                    this.manifestModal.trip = null;
                    this.manifestModal.passengers = [];
                    this.manifestModal.manifest = null;
                    this.manifestModal.error = null;
                },
                
                async loadManifest() {
                    if (!this.manifestModal.trip) return;
                    
                    this.manifestModal.loading = true;
                    this.manifestModal.error = null;
                    
                    try {
                        const trip = this.manifestModal.trip;
                        
                        // Формируем параметры для запроса ведомости
                        const params = new URLSearchParams();
                        if (trip.dt_depart) {
                            params.append('dt_depart', trip.dt_depart);
                        }
                        
                        const externalRouteId = trip.id_route || trip.id;
                        if (externalRouteId) {
                            params.append('external_trip_id', externalRouteId);
                        }
                        if (trip.provider) {
                            params.append('provider', trip.provider);
                        }
                        if (trip.dt_arrive) {
                            params.append('dt_arrive', trip.dt_arrive);
                        }
                        if (trip.from_id) {
                            params.append('from_id', trip.from_id);
                        }
                        if (trip.to_id) {
                            params.append('to_id', trip.to_id);
                        }
                        if (trip.from_name || trip.route_start) {
                            params.append('from_name', trip.from_name || trip.route_start);
                        }
                        if (trip.to_name || trip.route_end) {
                            params.append('to_name', trip.to_name || trip.route_end);
                        }
                        if (trip.route) {
                            params.append('route_number', trip.route);
                        }
                        if (trip.gn) {
                            params.append('bus_number', trip.gn);
                        }
                        if (trip.model) {
                            params.append('vehicle_model', trip.model);
                        }
                        if (trip.perevoz) {
                            params.append('carrier_name', trip.perevoz);
                        }

                        // Загружаем ведомость
                        const response = await fetch(`/api/manifests/${externalRouteId}?${params.toString()}`, {
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
                            this.manifestModal.manifest = data.data.manifest;
                            const manifestItems = data.data.items || [];
                            
                            // Формируем список пассажиров из manifestItems
                            this.manifestModal.passengers = manifestItems.map(item => ({
                                ...item.passenger,
                                manifest_item_id: item.id,
                                checked_in: item.checked_in,
                                checked_in_at: item.checked_in_at,
                                checked_in_by: item.checked_in_by,
                            }));

                            // Обновляем trip данными из manifest если нужно
                            if (this.manifestModal.manifest && !this.manifestModal.trip.dt_depart) {
                                this.manifestModal.trip = {
                                    ...this.manifestModal.trip,
                                    dt_depart: this.manifestModal.manifest.dt_depart,
                                    dt_arrive: this.manifestModal.manifest.dt_arrive,
                                    route: this.manifestModal.manifest.route_number,
                                    route_number: this.manifestModal.manifest.route_number,
                                    from_station_name: this.manifestModal.manifest.from_name,
                                    to_station_name: this.manifestModal.manifest.to_name,
                                    gn: this.manifestModal.manifest.bus_number,
                                    model: this.manifestModal.manifest.vehicle_model,
                                    perevoz: this.manifestModal.manifest.carrier_name,
                                };
                            }
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить ведомость');
                        }
                    } catch (error) {
                        console.error('Error loading manifest:', error);
                        this.manifestModal.error = error.message || 'Ошибка загрузки ведомости';
                        this.manifestModal.passengers = [];
                    } finally {
                        this.manifestModal.loading = false;
                    }
                },
                
                formatDateTime(dateTime) {
                    if (!dateTime) return '—';
                    const date = new Date(dateTime);
                    return date.toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },
                
                formatPassengerType(passenger) {
                    return passenger.passenger_type || 'взрослый';
                },
                
                formatPrice(price) {
                    if (!price) return '—';
                    return parseFloat(price).toFixed(2);
                },
                
                formatFullName(passenger) {
                    const parts = [];
                    if (passenger.last_name) parts.push(passenger.last_name);
                    if (passenger.first_name) parts.push(passenger.first_name);
                    if (passenger.middle_name) parts.push(passenger.middle_name);
                    return parts.length > 0 ? parts.join(' ') : '—';
                },
                
                formatPhone(phone) {
                    if (!phone) return '—';
                    return phone;
                },
                
                getSeatNumber(passenger) {
                    return passenger.seat_number || '—';
                },
                
                getTicketPrice(passenger) {
                    if (passenger.ticket_total_price) {
                        return this.formatPrice(passenger.ticket_total_price);
                    }
                    if (passenger.ticket_price) {
                        return this.formatPrice(passenger.ticket_price);
                    }
                    return '—';
                },
                
                async toggleCheckIn(passenger) {
                    if (!this.manifestModal.manifest || !passenger.manifest_item_id) {
                        console.error('Manifest or item ID not found');
                        return;
                    }

                    // Определяем новое состояние
                    let newCheckedIn;
                    if (passenger.checked_in === null || passenger.checked_in === undefined) {
                        newCheckedIn = true; // Первый клик - явка
                    } else if (passenger.checked_in === true) {
                        newCheckedIn = false; // Второй клик - неявка
                    } else {
                        newCheckedIn = null; // Третий клик - сброс
                    }

                    try {
                        // Отправляем запрос на сервер
                        const response = await fetch(`/api/manifests/${this.manifestModal.manifest.id}/check-in`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                item_id: passenger.manifest_item_id,
                                checked_in: newCheckedIn === null ? false : newCheckedIn, // API требует boolean
                            }),
                        });

                        if (!response.ok) {
                            const errorData = await response.json().catch(() => ({}));
                            throw new Error(errorData.message || `HTTP ${response.status}`);
                        }

                        const data = await response.json();
                        if (data.success) {
                            // Обновляем локальное состояние
                            passenger.checked_in = data.data.checked_in;
                            passenger.checked_in_at = data.data.checked_in_at;
                            passenger.checked_in_by = data.data.checked_in_by;
                        } else {
                            throw new Error(data.message || 'Ошибка сохранения');
                        }
                    } catch (error) {
                        console.error('Error toggling check-in:', error);
                        alert('Ошибка при сохранении отметки: ' + error.message);
                    }
                },
                
                isCheckedIn(passenger) {
                    return passenger.checked_in === true;
                },
                
                formatManifestDate() {
                    const now = new Date();
                    const days = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
                    const dayName = days[now.getDay()];
                    const day = String(now.getDate()).padStart(2, '0');
                    const months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 
                                   'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
                    const monthName = months[now.getMonth()];
                    const year = now.getFullYear();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    return `${dayName}, ${day} ${monthName} ${year} г., ${hours}:${minutes}`;
                },
                
                formatDepartureDateTime() {
                    if (!this.manifestModal.trip || !this.manifestModal.trip.dt_depart) return '—';
                    const date = new Date(this.manifestModal.trip.dt_depart);
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${day}.${month}.${year} ${hours}:${minutes}`;
                },
                
                formatDrivers() {
                    const trip = this.manifestModal.trip;
                    if (trip && trip.drivers && Array.isArray(trip.drivers) && trip.drivers.length > 0) {
                        return trip.drivers.map(driver => {
                            const parts = [];
                            if (driver.last_name) parts.push(driver.last_name);
                            if (driver.first_name) parts.push(driver.first_name);
                            if (driver.middle_name) parts.push(driver.middle_name);
                            return parts.join(' ');
                        }).join(', ') || '—';
                    }
                    if (trip && trip.driver_name) {
                        return trip.driver_name;
                    }
                    return '—';
                },
                
                formatVehicle() {
                    const trip = this.manifestModal.trip;
                    if (trip && trip.vehicle_number) {
                        const vehicle = trip.vehicle_number;
                        const model = trip.vehicle_model ? ` (${trip.vehicle_model})` : '';
                        return vehicle + model;
                    }
                    return '—';
                },
                
                formatRouteInfo() {
                    const trip = this.manifestModal.trip;
                    if (!trip) return '—';
                    
                    // Извлекаем номер рейса/маршрута
                    let routeNumber = '';
                    
                    // Если route - объект, извлекаем route_number или id
                    if (trip.route && typeof trip.route === 'object') {
                        routeNumber = trip.route.route_number || trip.route.id || trip.route.trip_number || '';
                    } else if (trip.route) {
                        // Если route - строка или число
                        routeNumber = String(trip.route);
                    }
                    
                    // Если route_number существует и это не объект
                    if (!routeNumber && trip.route_number) {
                        if (typeof trip.route_number === 'object') {
                            routeNumber = trip.route_number.route_number || trip.route_number.id || '';
                        } else {
                            routeNumber = String(trip.route_number);
                        }
                    }
                    
                    // Если trip_number существует
                    if (!routeNumber && trip.trip_number) {
                        routeNumber = String(trip.trip_number);
                    }
                    
                    // Если id существует и это не объект
                    if (!routeNumber && trip.id && typeof trip.id !== 'object') {
                        routeNumber = String(trip.id);
                    }
                    
                    const from = trip.from_station_name || trip.route_start || '';
                    const to = trip.to_station_name || trip.route_end || '';
                    
                    if (routeNumber && from && to) {
                        return `${routeNumber} ${from}, – ${to}, ЖД`;
                    }
                    if (from && to) {
                        return `${from} – ${to}`;
                    }
                    return routeNumber || '—';
                },
                
                showContextMenu(event, trip) {
                    this.contextMenu.show = true;
                    this.contextMenu.x = event.clientX;
                    this.contextMenu.y = event.clientY;
                    this.contextMenu.trip = trip;
                },
                
                hideContextMenu() {
                    this.contextMenu.show = false;
                    this.contextMenu.trip = null;
                },
                
                async downloadManifestPdf() {
                    const trip = this.contextMenu.trip;
                    if (!trip) return;
                    
                    try {
                        // Получаем id_route и dt_depart
                        const externalRouteId = trip.id_route || trip.id;
                        const dtDepart = trip.dt_depart;
                        
                        if (!externalRouteId || !dtDepart) {
                            alert('Не удалось определить ID маршрута или время отправления');
                            return;
                        }
                        
                        // Формируем параметры запроса
                        const params = new URLSearchParams({
                            dt_depart: dtDepart,
                            external_trip_id: trip.id,
                            provider: trip.provider || 'РФБАС'
                        });
                        
                        // Добавляем дополнительные параметры для создания ведомости если её нет
                        if (trip.dt_arrive) params.append('dt_arrive', trip.dt_arrive);
                        if (trip.from_id) params.append('from_id', trip.from_id);
                        if (trip.to_id) params.append('to_id', trip.to_id);
                        if (trip.from_name || trip.route_start) params.append('from_name', trip.from_name || trip.route_start);
                        if (trip.to_name || trip.route_end) params.append('to_name', trip.to_name || trip.route_end);
                        if (trip.route) params.append('route_number', trip.route);
                        if (trip.gn) params.append('bus_number', trip.gn);
                        if (trip.model) params.append('vehicle_model', trip.model);
                        if (trip.perevoz) params.append('carrier_name', trip.perevoz);
                        
                        // Сначала получаем/создаём ведомость
                        const manifestResponse = await fetch(`/api/manifests/${externalRouteId}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        });
                        
                        if (!manifestResponse.ok) {
                            const errorData = await manifestResponse.json().catch(() => ({}));
                            throw new Error(errorData.message || 'Ошибка при получении ведомости');
                        }
                        
                        const manifestData = await manifestResponse.json();
                        if (!manifestData.success || !manifestData.data || !manifestData.data.manifest) {
                            throw new Error('Не удалось получить данные ведомости');
                        }
                        
                        const manifestId = manifestData.data.manifest.id;
                        
                        // Скачиваем PDF
                        window.open(`/api/manifests/${manifestId}/pdf`, '_blank');
                        
                    } catch (error) {
                        console.error('Error downloading PDF:', error);
                        alert('Ошибка при скачивании PDF: ' + error.message);
                    } finally {
                        this.hideContextMenu();
                    }
                }
            },
            mounted() {
                this.loadStations();
                this.initDatePicker();
                
                // Скрываем контекстное меню при клике вне его
                document.addEventListener('click', (e) => {
                    if (this.contextMenu.show && !e.target.closest('.context-menu')) {
                        this.hideContextMenu();
                    }
                });
                
                // Закрываем модальное окно по ESC
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.manifestModal.show) {
                        this.closeManifestModal();
                    }
                });
            }
        }).mount('#app');
    </script>
</body>
</html>
