<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ведомость рейса</title>
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
            max-width: 1600px;
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
        
        .trip-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .trip-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .trip-info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .trip-info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }
        
        th {
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
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
        
        .control-check {
            color: #2b8a3e;
            font-weight: bold;
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
        
        .boarding-manifest-header {
            margin-bottom: 0;
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
        
        @media (max-width: 768px) {
            .manifest-info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="header">
            <h1>📋 Ведомость рейса</h1>
            <div class="header-links">
                <a href="/dashboard/trips-list">← Назад к списку рейсов</a>
                <a href="/dashboard">← Назад к панели</a>
            </div>
        </div>
        
        <div class="container">
            <div class="card" v-if="trip">
                <div class="boarding-manifest-header">
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
                            <span class="manifest-value">@{{ trip.perevoz || trip.carrier_name || '—' }}</span>
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
                            <span class="manifest-value">@{{ trip.waybill_number || trip.waybill || '—' }}</span>
                        </div>
                        <div class="manifest-info-row">
                            <span class="manifest-label">Станция отправления:</span>
                            <span class="manifest-value">@{{ trip.from_station_name || trip.route_start || '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Пассажиры (@{{ passengers.length }})</h2>
                
                <div v-if="loading" class="loading">
                    ⏳ Загрузка пассажиров...
                </div>
                
                <div v-else-if="error" class="error-state">
                    <strong>⚠️ Ошибка:</strong> @{{ error }}
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
                            <tr v-if="passengers.length === 0">
                                <td colspan="12" style="text-align: center; padding: 40px; color: #666;">
                                    Пассажиры не найдены для данного рейса
                                </td>
                            </tr>
                            <tr v-for="(passenger, index) in passengers" :key="passenger.id || index">
                                <td>@{{ getSeatNumber(passenger) }}</td>
                                <td>@{{ formatPassengerType(passenger) }}</td>
                                <td>@{{ passenger.document_series || '—' }}</td>
                                <td>@{{ passenger.document_number || '—' }}</td>
                                <td>@{{ trip?.from_station_name || trip?.route_start || '—' }}</td>
                                <td>@{{ trip?.to_station_name || trip?.route_end || '—' }}</td>
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
    
    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    externalId: '{{ $externalId }}',
                    trip: null,
                    manifest: null,
                    manifestItems: [],
                    passengers: [],
                    loading: true,
                    error: null,
                };
            },
            mounted() {
                this.loadManifest();
            },
            methods: {
                async loadManifest() {
                    this.loading = true;
                    this.error = null;
                    
                    try {
                        // Загружаем данные рейса из sessionStorage (если есть)
                        const tripData = sessionStorage.getItem('trip_data_' + this.externalId);
                        if (tripData) {
                            this.trip = JSON.parse(tripData);
                        }

                        // Формируем параметры для запроса ведомости
                        const params = new URLSearchParams();
                        if (this.trip?.dt_depart) {
                            params.append('dt_depart', this.trip.dt_depart);
                        }
                        if (this.trip?.id_route) {
                            // Используем id_route как external_route_id
                            var externalRouteId = this.trip.id_route;
                        } else {
                            // Fallback: используем externalId
                            var externalRouteId = this.externalId;
                        }
                        if (this.externalId) {
                            params.append('external_trip_id', this.externalId);
                        }
                        if (this.trip?.provider) {
                            params.append('provider', this.trip.provider);
                        }
                        if (this.trip?.dt_arrive) {
                            params.append('dt_arrive', this.trip.dt_arrive);
                        }
                        if (this.trip?.from_id) {
                            params.append('from_id', this.trip.from_id);
                        }
                        if (this.trip?.to_id) {
                            params.append('to_id', this.trip.to_id);
                        }
                        if (this.trip?.from_name || this.trip?.route_start) {
                            params.append('from_name', this.trip.from_name || this.trip.route_start);
                        }
                        if (this.trip?.to_name || this.trip?.route_end) {
                            params.append('to_name', this.trip.to_name || this.trip.route_end);
                        }
                        if (this.trip?.route) {
                            params.append('route_number', this.trip.route);
                        }
                        if (this.trip?.gn) {
                            params.append('bus_number', this.trip.gn);
                        }
                        if (this.trip?.model) {
                            params.append('vehicle_model', this.trip.model);
                        }
                        if (this.trip?.perevoz) {
                            params.append('carrier_name', this.trip.perevoz);
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
                            this.manifest = data.data.manifest;
                            this.manifestItems = data.data.items || [];
                            
                            // Формируем список пассажиров из manifestItems
                            this.passengers = this.manifestItems.map(item => ({
                                ...item.passenger,
                                manifest_item_id: item.id,
                                checked_in: item.checked_in,
                                checked_in_at: item.checked_in_at,
                                checked_in_by: item.checked_in_by,
                            }));

                            // Обновляем trip данными из manifest если нужно
                            if (this.manifest && !this.trip) {
                                this.trip = {
                                    dt_depart: this.manifest.dt_depart,
                                    dt_arrive: this.manifest.dt_arrive,
                                    route: this.manifest.route_number,
                                    route_number: this.manifest.route_number,
                                    from_station_name: this.manifest.from_name,
                                    to_station_name: this.manifest.to_name,
                                    gn: this.manifest.bus_number,
                                    model: this.manifest.vehicle_model,
                                    perevoz: this.manifest.carrier_name,
                                };
                            }
                        } else {
                            throw new Error(data.message || 'Не удалось загрузить ведомость');
                        }
                    } catch (error) {
                        console.error('Error loading manifest:', error);
                        this.error = error.message || 'Ошибка загрузки ведомости';
                        this.passengers = [];
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
                
                hasEmailOrPhone(passenger) {
                    return !!(passenger.email || passenger.phone);
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
                    if (!this.manifest || !passenger.manifest_item_id) {
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
                        newCheckedIn = null; // Третий клик - сброс (можно убрать если не нужно)
                        // Или просто вернуться к true:
                        // newCheckedIn = true;
                    }

                    try {
                        // Отправляем запрос на сервер
                        const response = await fetch(`/api/manifests/${this.manifest.id}/check-in`, {
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
                    if (!this.trip || !this.trip.dt_depart) return '—';
                    const date = new Date(this.trip.dt_depart);
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${day}.${month}.${year} ${hours}:${minutes}`;
                },
                
                formatDrivers() {
                    if (this.trip && this.trip.drivers && Array.isArray(this.trip.drivers) && this.trip.drivers.length > 0) {
                        return this.trip.drivers.map(driver => {
                            const parts = [];
                            if (driver.last_name) parts.push(driver.last_name);
                            if (driver.first_name) parts.push(driver.first_name);
                            if (driver.middle_name) parts.push(driver.middle_name);
                            return parts.join(' ');
                        }).join(', ') || '—';
                    }
                    if (this.trip && this.trip.driver_name) {
                        return this.trip.driver_name;
                    }
                    return '—';
                },
                
                formatVehicle() {
                    if (this.trip && this.trip.vehicle_number) {
                        const vehicle = this.trip.vehicle_number;
                        const model = this.trip.vehicle_model ? ` (${this.trip.vehicle_model})` : '';
                        return vehicle + model;
                    }
                    return '—';
                },
                
                formatRouteInfo() {
                    if (!this.trip) return '—';
                    
                    // Извлекаем номер рейса/маршрута
                    let routeNumber = '';
                    
                    // Если route - объект, извлекаем route_number или id
                    if (this.trip.route && typeof this.trip.route === 'object') {
                        routeNumber = this.trip.route.route_number || this.trip.route.id || this.trip.route.trip_number || '';
                    } else if (this.trip.route) {
                        // Если route - строка или число
                        routeNumber = String(this.trip.route);
                    }
                    
                    // Если route_number существует и это не объект
                    if (!routeNumber && this.trip.route_number) {
                        if (typeof this.trip.route_number === 'object') {
                            routeNumber = this.trip.route_number.route_number || this.trip.route_number.id || '';
                        } else {
                            routeNumber = String(this.trip.route_number);
                        }
                    }
                    
                    // Если trip_number существует
                    if (!routeNumber && this.trip.trip_number) {
                        routeNumber = String(this.trip.trip_number);
                    }
                    
                    // Если id существует и это не объект
                    if (!routeNumber && this.trip.id && typeof this.trip.id !== 'object') {
                        routeNumber = String(this.trip.id);
                    }
                    
                    const from = this.trip.from_station_name || this.trip.route_start || '';
                    const to = this.trip.to_station_name || this.trip.route_end || '';
                    
                    if (routeNumber && from && to) {
                        return `${routeNumber} ${from}, – ${to}, ЖД`;
                    }
                    if (from && to) {
                        return `${from} – ${to}`;
                    }
                    return routeNumber || '—';
                }
            }
        }).mount('#app');
    </script>
</body>
</html>
