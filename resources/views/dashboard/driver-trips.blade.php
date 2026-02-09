<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Рейсы водителя {{ $driver->last_name }} {{ $driver->first_name }}</title>
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.6rem;
            color: #667eea;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-name {
            color: #495057;
            font-weight: 600;
        }

        .logout-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: #edf2ff;
            color: #3b5bdb;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #dbe4ff;
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
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e7edff;
        }
        
        .card h2 {
            color: #333;
            font-size: 1.3rem;
        }
        
        .btn {
            padding: 11px 22px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #edf2ff;
            color: #4453c8;
            border: 1px solid #dbe4ff;
        }
        
        .btn-secondary:hover {
            background: #dbe4ff;
        }
        
        .btn-danger {
            background: #ff8787;
            color: white;
        }
        
        .btn-danger:hover {
            background: #fa5252;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box input,
        .search-box select {
            flex: 1;
            min-width: 200px;
            padding: 10px 16px;
            border: 1px solid #dbe4ff;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f8faff;
            color: #495057;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #e7edff;
        }

        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f3ff;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .badge-success {
            background: #d3f9d8;
            color: #2f9e44;
        }

        .badge-danger {
            background: #ffe3e3;
            color: #fa5252;
        }

        .badge-warning {
            background: #fff3bf;
            color: #f59f00;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .driver-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .driver-info h2 {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .driver-info p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .section-divider {
            border: none;
            border-top: 2px solid #e7edff;
            margin: 40px 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }

        .info-box {
            background: #e7f5ff;
            border-left: 4px solid #1c7ed6;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box strong {
            color: #1c7ed6;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚌 Управление рейсами</h1>
        <div class="user-info">
            <span class="user-name">{{ auth()->user()->name ?? 'Пользователь' }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        </div>
    </div>
    
    <div class="container">
        <a href="/dashboard/drivers" class="back-link">← Вернуться к списку водителей</a>

        <div class="driver-info">
            <h2>{{ $driver->last_name }} {{ $driver->first_name }} {{ $driver->middle_name }}</h2>
            <p>Username: <strong>{{ $driver->username }}</strong> | Телефон: <strong>{{ $driver->phone ?? '—' }}</strong> | Email: <strong>{{ $driver->email ?? '—' }}</strong></p>
        </div>

        <!-- Секция назначенных рейсов -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Назначенные рейсы</h2>
            </div>

            <div id="assignedTripsContent" class="loading">
                ⏳ Загрузка назначенных рейсов...
            </div>
        </div>

        <hr class="section-divider">

        <!-- Секция назначения новых рейсов -->
        <div class="card">
            <div class="card-header">
                <h2>🚌 Назначить новый рейс</h2>
            </div>

            <div class="info-box">
                <strong>ℹ️ Инструкция:</strong> Выберите станцию отправления, станцию прибытия и дату, затем нажмите "Найти рейсы". Система покажет все доступные рейсы от провайдера.
            </div>

            <div class="search-box">
                <select id="fromStation">
                    <option value="">Станция отправления...</option>
                </select>
                <select id="toStation">
                    <option value="">Станция прибытия...</option>
                </select>
                <input type="date" id="searchDate">
                <button class="btn btn-primary" onclick="searchRaces()">🔍 Найти рейсы</button>
            </div>

            <div id="racesResults"></div>
        </div>
    </div>

    <script>
        const driverId = {{ $driver->id }};
        const driverName = '{{ $driver->last_name }} {{ $driver->first_name }}';
        let stations = [];

        // Загрузка назначенных рейсов
        async function loadAssignedTrips() {
            try {
                const response = await fetch(`/api/drivers/${driverId}/trips`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });

                const result = await response.json();
                
                if (result.success && result.data) {
                    displayAssignedTrips(result.data.data);
                } else {
                    document.getElementById('assignedTripsContent').innerHTML = 
                        '<p style="color: #fa5252; text-align: center; padding: 40px;">Ошибка загрузки рейсов</p>';
                }
            } catch (error) {
                console.error('Error loading assigned trips:', error);
                document.getElementById('assignedTripsContent').innerHTML = 
                    '<p style="color: #fa5252; text-align: center; padding: 40px;">Ошибка загрузки рейсов</p>';
            }
        }

        function displayAssignedTrips(trips) {
            if (!trips || trips.length === 0) {
                document.getElementById('assignedTripsContent').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🚌</div>
                        <h3 style="color: #495057; margin-bottom: 10px;">У водителя пока нет назначенных рейсов</h3>
                        <p>Используйте форму ниже для назначения рейсов</p>
                    </div>
                `;
                return;
            }

            const html = `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Номер рейса</th>
                                <th>Маршрут</th>
                                <th>Дата и время отправления</th>
                                <th>Дата и время прибытия</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${trips.map(trip => `
                                <tr>
                                    <td>${trip.id}</td>
                                    <td style="font-weight: 600; color: #667eea;">${escapeHtml(trip.trip_number)}</td>
                                    <td>
                                        <strong>${escapeHtml(trip.route?.departure_station?.name || '—')}${trip.route?.departure_station?.external_id ? ` (${trip.route?.departure_station?.external_id})` : ''}</strong>
                                        <span style="color: #667eea;"> → </span>
                                        <strong>${escapeHtml(trip.route?.arrival_station?.name || '—')}${trip.route?.arrival_station?.external_id ? ` (${trip.route?.arrival_station?.external_id})` : ''}</strong>
                                    </td>
                                    <td>${formatDateTime(trip.departure_time)}</td>
                                    <td>${formatDateTime(trip.arrival_time)}</td>
                                    <td>
                                        <button class="btn btn-danger btn-small" onclick="unassignTrip(${trip.id})">
                                            🗑️ Снять
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('assignedTripsContent').innerHTML = html;
        }

        // Загрузка станций
        async function loadStations() {
            try {
                const response = await fetch('/api/stations', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });
                
                const result = await response.json();
                if (result.success && result.data) {
                    stations = result.data;
                    populateStationSelects();
                }
            } catch (error) {
                console.error('Error loading stations:', error);
            }
        }

        function populateStationSelects() {
            const fromSelect = document.getElementById('fromStation');
            const toSelect = document.getElementById('toStation');
            
            const options = stations.map(station => 
                `<option value="${station.id}">${escapeHtml(station.name)}</option>`
            ).join('');
            
            fromSelect.innerHTML = '<option value="">Станция отправления...</option>' + options;
            toSelect.innerHTML = '<option value="">Станция прибытия...</option>' + options;
        }

        // Поиск рейсов
        async function searchRaces() {
            const from = document.getElementById('fromStation').value;
            const to = document.getElementById('toStation').value;
            const date = document.getElementById('searchDate').value;
            
            if (!from || !to || !date) {
                alert('⚠️ Выберите станцию отправления, станцию прибытия и дату');
                return;
            }
            
            document.getElementById('racesResults').innerHTML = 
                '<p class="loading">⏳ Загрузка рейсов от провайдера...</p>';
            
            try {
                const url = `/api/races/all?from=${from}&to=${to}&date=${date}`;
                
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    displayRaces(result.data, result.from_station, result.to_station);
                } else {
                    document.getElementById('racesResults').innerHTML = 
                        `<p style="text-align: center; color: #fa5252; padding: 40px;">${escapeHtml(result.message || 'Ошибка загрузки рейсов')}</p>`;
                }
            } catch (error) {
                console.error('Error searching races:', error);
                document.getElementById('racesResults').innerHTML = 
                    '<p style="text-align: center; color: #fa5252; padding: 40px;">Ошибка загрузки рейсов</p>';
            }
        }

        function displayRaces(races, fromStation, toStation) {
            if (!races || races.length === 0) {
                document.getElementById('racesResults').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <h3 style="color: #495057; margin-bottom: 10px;">Рейсы не найдены</h3>
                        <p>Попробуйте изменить параметры поиска</p>
                    </div>
                `;
                return;
            }
            
            // Удаляем дубликаты по id (если рейс встречается несколько раз)
            const uniqueRaces = [];
            const seenIds = new Set();
            
            for (const race of races) {
                const raceId = race.id || `${race.id_route}_${race.dt_depart}`;
                if (!seenIds.has(raceId)) {
                    seenIds.add(raceId);
                    uniqueRaces.push(race);
                }
            }
            
            const html = `
                <div style="margin-top: 20px;">
                    <div style="background: #e7f5ff; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px;">
                        <strong style="color: #1c7ed6;">✓ Найдено рейсов: ${uniqueRaces.length}${uniqueRaces.length !== races.length ? ` (из ${races.length}, дубликаты удалены)` : ''}</strong>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Маршрут</th>
                                    <th>Номер рейса</th>
                                    <th>Отправление</th>
                                    <th>Прибытие</th>
                                    <th>Перевозчик</th>
                                    <th>Статус</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${uniqueRaces.map(race => {
                                    const isActive = race.active !== false;
                                    const statusClass = isActive ? 'badge-success' : 'badge-danger';
                                    const statusText = isActive ? 'Активен' : 'Отменен';
                                    const departureDate = race.dt_depart ? new Date(race.dt_depart).toLocaleDateString('ru-RU') : '—';
                                    const departureTime = race.dt_depart ? new Date(race.dt_depart).toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'}) : '—';
                                    const arrivalTime = race.dt_arrive ? new Date(race.dt_arrive).toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'}) : '—';
                                    
                                    return `
                                        <tr>
                                            <td>${departureDate}</td>
                                            <td style="font-size: 0.9rem;">
                                                <div style="font-weight: 600;">${escapeHtml(race.route_start || fromStation.name)}</div>
                                                <div style="color: #667eea; margin: 4px 0;">↓</div>
                                                <div style="font-weight: 600;">${escapeHtml(race.route_end || toStation.name)}</div>
                                            </td>
                                            <td style="font-weight: 700; color: #667eea; font-size: 1.1rem;">${escapeHtml(race.route || race.id || '—')}</td>
                                            <td style="font-weight: 600;">${departureTime}</td>
                                            <td style="font-weight: 600;">${arrivalTime}</td>
                                            <td style="font-size: 0.85rem;">${escapeHtml(race.perevoz || '—')}</td>
                                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                                            <td>
                                                <button 
                                                    class="btn btn-primary btn-small" 
                                                    onclick="assignRace('${escapeHtml(race.id)}', '${escapeHtml(race.route || race.id)}', ${race.from_station_id}, ${race.to_station_id}, '${race.dt_depart}', '${race.dt_arrive || ''}', '${escapeHtml(race.route_start || '')}', '${escapeHtml(race.route_end || '')}')"
                                                    ${!isActive ? 'disabled' : ''}
                                                >
                                                    ${isActive ? '✓ Назначить' : 'Отменен'}
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('racesResults').innerHTML = html;
        }

        // Назначить рейс
        async function assignRace(raceId, routeNumber, fromStationId, toStationId, departureTime, arrivalTime, fromStationName, toStationName) {
            try {
                const response = await fetch(`/api/drivers/${driverId}/assign-race`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        race_id: raceId,
                        from_station_id: fromStationId,
                        to_station_id: toStationId,
                        departure_time: departureTime,
                        arrival_time: arrivalTime || null,
                        route_number: routeNumber,
                        from_station_name: fromStationName,
                        to_station_name: toStationName,
                    }),
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Рейс успешно назначен водителю!');
                    loadAssignedTrips();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert('❌ ' + (result.message || 'Ошибка при назначении рейса'));
                }
            } catch (error) {
                console.error('Error assigning race:', error);
                alert('❌ Ошибка при назначении рейса');
            }
        }

        // Снять рейс
        async function unassignTrip(tripId) {
            try {
                const response = await fetch(`/api/drivers/${driverId}/trips/${tripId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Рейс снят с водителя');
                    loadAssignedTrips();
                } else {
                    alert('❌ ' + (result.message || 'Ошибка при снятии рейса'));
                }
            } catch (error) {
                alert('❌ Ошибка при снятии рейса');
            }
        }

        // Утилиты
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '—';
            const date = new Date(dateStr);
            return date.toLocaleString('ru-RU');
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            loadAssignedTrips();
            loadStations();
            
            // Устанавливаем сегодняшнюю дату
            const today = new Date();
            document.getElementById('searchDate').valueAsDate = today;
        });
    </script>
</body>
</html>
