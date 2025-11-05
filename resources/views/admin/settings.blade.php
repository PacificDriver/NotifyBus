<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Настройки системы - Панель администратора</title>
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
            background: #764ba2;
            color: white;
            padding: 20px 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }

        .back-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: background 0.3s;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
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
            border-bottom: 2px solid #764ba2;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #764ba2;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.85rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #764ba2;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5f3a82;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #51cf66;
            color: white;
        }

        .btn-success:hover {
            background: #40c057;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #764ba2;
            border-bottom-color: #764ba2;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-ok {
            background: #51cf66;
        }
        
        .status-error {
            background: #ff6b6b;
        }

        .status-warning {
            background: #ffd43b;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #764ba2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Настройки системы</h1>
        <a href="/admin" class="back-link">← Назад к панели</a>
    </div>
    
    <div class="container">
        <div id="alert-container"></div>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('whatsapp')">📱 WhatsApp</button>
                <button class="tab" onclick="switchTab('email')">✉️ Email</button>
                <button class="tab" onclick="switchTab('carrier')">🚌 API Перевозчика</button>
                <button class="tab" onclick="switchTab('external_db')">🗄️ Внешняя БД</button>
                <button class="tab" onclick="switchTab('notification')">🔔 Уведомления</button>
            </div>

            <!-- WhatsApp Settings -->
            <div id="whatsapp-tab" class="tab-content active">
                <h2>Настройки WhatsApp (Wappi.pro)</h2>
                <form id="whatsapp-form">
                    <div class="form-group">
                        <label for="whatsapp_api_url">API URL</label>
                        <input type="text" id="whatsapp_api_url" name="api_url" value="https://api.wappi.pro" placeholder="https://api.wappi.pro">
                        <small>Базовый URL API Wappi.pro</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="whatsapp_api_token">API Token</label>
                            <input type="password" id="whatsapp_api_token" name="api_token" placeholder="Ваш API токен">
                            <small>Токен авторизации из личного кабинета Wappi.pro</small>
                        </div>

                        <div class="form-group">
                            <label for="whatsapp_profile_id">Profile ID</label>
                            <input type="text" id="whatsapp_profile_id" name="profile_id" placeholder="ID профиля WhatsApp">
                            <small>ID вашего профиля WhatsApp в Wappi.pro</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="whatsapp_webhook_secret">Webhook Secret</label>
                            <input type="password" id="whatsapp_webhook_secret" name="webhook_secret" placeholder="Секретный ключ для webhook">
                            <small>Секретный ключ для защиты webhook endpoint</small>
                        </div>

                        <div class="form-group">
                            <label for="whatsapp_daily_limit">Дневной лимит</label>
                            <input type="number" id="whatsapp_daily_limit" name="daily_limit" value="1000" min="1">
                            <small>Максимальное количество сообщений в день</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="whatsapp_use_async" name="use_async" checked>
                            Использовать асинхронную отправку
                        </label>
                        <small>Асинхронная отправка ставит сообщения в очередь и возвращает task_id</small>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                        <button type="button" class="btn btn-success" onclick="testWhatsApp()">🔍 Проверить подключение</button>
                    </div>
                </form>
            </div>

            <!-- Email Settings -->
            <div id="email-tab" class="tab-content">
                <h2>Настройки Email (SMTP)</h2>
                <form id="email-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_host">SMTP Host</label>
                            <input type="text" id="email_host" name="host" placeholder="smtp.gmail.com">
                        </div>
                        <div class="form-group">
                            <label for="email_port">Port</label>
                            <input type="number" id="email_port" name="port" value="587" placeholder="587">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_username">Username</label>
                            <input type="text" id="email_username" name="username" placeholder="your-email@gmail.com">
                        </div>
                        <div class="form-group">
                            <label for="email_password">Password</label>
                            <input type="password" id="email_password" name="password" placeholder="Пароль или App Password">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_encryption">Encryption</label>
                            <select id="email_encryption" name="encryption">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="email_from_address">From Address</label>
                            <input type="email" id="email_from_address" name="from_address" placeholder="noreply@example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email_test_address">Тестовый Email</label>
                        <input type="email" id="email_test_address" placeholder="test@example.com">
                        <small>Email для отправки тестового письма</small>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                        <button type="button" class="btn btn-success" onclick="testEmail()">📧 Отправить тестовое письмо</button>
                    </div>
                </form>
            </div>

            <!-- Carrier API Settings -->
            <div id="carrier-tab" class="tab-content">
                <h2>Настройки API Перевозчика</h2>
                <form id="carrier-form">
                    <div class="form-group">
                        <label for="carrier_api_url">API URL</label>
                        <input type="text" id="carrier_api_url" name="url" value="http://rc.rfbus.ru:8086" placeholder="http://rc.rfbus.ru:8086">
                        <small>Базовый URL API перевозчика</small>
                    </div>

                    <div class="form-group">
                        <label for="carrier_api_key">API Key (x-access-token)</label>
                        <input type="password" id="carrier_api_key" name="key" placeholder="Ваш API ключ">
                        <small>Токен доступа для API перевозчика</small>
                    </div>

                    <div class="form-group">
                        <label for="carrier_timeout">Timeout (секунды)</label>
                        <input type="number" id="carrier_timeout" name="timeout" value="30" min="1">
                        <small>Таймаут запросов к API в секундах</small>
                    </div>

                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <strong>💡 Совет:</strong> После сохранения настроек API, нажмите "Обновить станции" для синхронизации справочника станций.
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                        <button type="button" class="btn btn-success" onclick="testCarrierApi()">🔍 Проверить подключение</button>
                        <button type="button" class="btn btn-secondary" onclick="syncStations()" id="sync-stations-btn">🔄 Обновить станции</button>
                    </div>
                    
                    <div id="sync-stations-result" style="margin-top: 15px;"></div>
                </form>
            </div>

            <!-- External DB Settings -->
            <div id="external_db-tab" class="tab-content">
                <h2>Настройки внешней базы данных</h2>
                <p style="margin-bottom: 20px; color: #666;">
                    Настройки подключения к внешней базе данных PostgreSQL для загрузки пассажиров по ID рейсов.
                </p>
                <form id="external_db-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="external_db_host">Host</label>
                            <input type="text" id="external_db_host" name="host" placeholder="localhost">
                        </div>
                        <div class="form-group">
                            <label for="external_db_port">Port</label>
                            <input type="number" id="external_db_port" name="port" value="5432" placeholder="5432">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="external_db_database">Database</label>
                            <input type="text" id="external_db_database" name="database" placeholder="database_name">
                        </div>
                        <div class="form-group">
                            <label for="external_db_username">Username</label>
                            <input type="text" id="external_db_username" name="username" placeholder="postgres">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="external_db_password">Password</label>
                        <input type="password" id="external_db_password" name="password" placeholder="Пароль">
                        <small>Пароль будет сохранен в базе данных с шифрованием</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="external_db_tickets_table">Таблица билетов</label>
                            <input type="text" id="external_db_tickets_table" name="tickets_table" value="tickets" placeholder="tickets">
                            <small>Название таблицы с билетами/пассажирами</small>
                        </div>
                        <div class="form-group">
                            <label for="external_db_race_id_column">Колонка ID рейса</label>
                            <input type="text" id="external_db_race_id_column" name="race_id_column" value="race_id" placeholder="race_id">
                            <small>Название колонки с ID рейса (external_id)</small>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                    </div>
                </form>
            </div>

            <!-- Notification Settings -->
            <div id="notification-tab" class="tab-content">
                <h2>Настройки уведомлений</h2>
                <form id="notification-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="notification_batch_size">Размер пакета</label>
                            <input type="number" id="notification_batch_size" name="batch_size" value="10" min="1">
                            <small>Количество сообщений в одном пакете</small>
                        </div>
                        <div class="form-group">
                            <label for="notification_delay_seconds">Задержка между пакетами (сек)</label>
                            <input type="number" id="notification_delay_seconds" name="delay_seconds" value="2" min="0">
                            <small>Задержка между отправкой пакетов</small>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const token = document.querySelector('meta[name="csrf-token"]').content;

        // Загрузка настроек при открытии страницы
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
        });

        function switchTab(tabName) {
            // Скрыть все табы
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Показать выбранный таб
            document.getElementById(`${tabName}-tab`).classList.add('active');
            event.target.classList.add('active');
        }

        async function loadSettings() {
            try {
                const response = await fetch(`${API_BASE}/settings`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include', // Отправлять cookies для сессии
                });
                const data = await response.json();

                if (data.success) {
                    // Заполнить формы
                    fillForm('whatsapp', data.data.whatsapp || {});
                    fillForm('email', data.data.email || {});
                    fillForm('carrier_api', data.data.carrier_api || {});
                    fillForm('external_db', data.data.external_db || {});
                    fillForm('notification', data.data.notification || {});
                }
            } catch (error) {
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при загрузке настроек: ' + error.message + '\n\nОбратитесь к администратору.', 'error');
            }
        }

        function fillForm(group, settings) {
            Object.keys(settings).forEach(key => {
                const input = document.querySelector(`#${group}_${key}`);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = settings[key] === true || settings[key] === '1' || settings[key] === 1;
                    } else if (input.type === 'password') {
                        // Для паролей не заполняем автоматически, чтобы не показывать маску
                        // Пользователь может ввести новый пароль, если нужно его изменить
                        if (!input.value) {
                            input.placeholder = settings[key] ? 'Введите новый пароль для изменения' : 'Введите пароль';
                        }
                    } else {
                        input.value = settings[key] || '';
                    }
                }
            });
        }

        // Обработчики форм
        document.getElementById('whatsapp-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings('whatsapp', new FormData(e.target));
        });

        document.getElementById('email-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings('email', new FormData(e.target));
        });

        document.getElementById('carrier-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings('carrier_api', new FormData(e.target));
        });

        document.getElementById('notification-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings('notification', new FormData(e.target));
        });

        document.getElementById('external_db-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings('external_db', new FormData(e.target));
        });

        async function saveSettings(group, formData) {
            const settings = {};
            formData.forEach((value, key) => {
                // Пропускаем пустые значения для паролей (чтобы не затереть существующие)
                if (key === 'key' || key === 'password' || key === 'api_token' || key === 'webhook_secret') {
                    if (!value || value.trim() === '') {
                        return; // Пропускаем пустые пароли
                    }
                }
                settings[key] = value;
            });

            // Для чекбоксов
            if (group === 'whatsapp') {
                const checkbox = document.getElementById('whatsapp_use_async');
                settings.use_async = checkbox ? checkbox.checked : false;
            }

            try {
                const response = await fetch(`${API_BASE}/settings`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include', // Отправлять cookies для сессии
                    body: JSON.stringify({
                        group: group,
                        settings: settings,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ ' + (data.message || 'Настройки успешно сохранены в базу данных!'), 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка');
                }
            } catch (error) {
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при сохранении: ' + error.message + '\n\nОбратитесь к администратору.', 'error');
            }
        }

        async function testWhatsApp() {
            const form = document.getElementById('whatsapp-form');
            const formData = new FormData(form);
            const settings = {};
            formData.forEach((value, key) => {
                settings[key] = value;
            });

            const checkbox = document.getElementById('whatsapp_use_async');
            settings.use_async = checkbox ? checkbox.checked : false;

            showAlert('Проверка подключения...', 'info');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test/whatsapp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include', // Отправлять cookies для сессии
                    body: JSON.stringify({ settings }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ Подключение к WhatsApp API успешно!', 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка подключения');
                }
            } catch (error) {
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка подключения к WhatsApp API: ' + error.message + '\n\nОбратитесь к администратору.', 'error');
            }
        }

        async function testEmail() {
            const testEmail = document.getElementById('email_test_address').value;
            if (!testEmail) {
                showAlert('Укажите email для тестирования', 'error');
                return;
            }

            showAlert('Отправка тестового письма...', 'info');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test/email`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include', // Отправлять cookies для сессии
                    body: JSON.stringify({ test_email: testEmail }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ Тестовое письмо отправлено!', 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка отправки');
                }
            } catch (error) {
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка отправки тестового письма: ' + error.message + '\n\nОбратитесь к администратору для проверки настроек Email.', 'error');
            }
        }

        async function testCarrierApi() {
            const form = document.getElementById('carrier-form');
            const formData = new FormData(form);
            const settings = {};
            formData.forEach((value, key) => {
                settings[key] = value;
            });

            console.log('Testing Carrier API connection with settings:', settings);
            showAlert('Проверка подключения...', 'info');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test/carrier-api`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include', // Отправлять cookies для сессии
                    body: JSON.stringify({ settings }),
                });

                console.log('Response status:', response.status);
                
                const data = await response.json();
                console.log('Response data:', data);

                if (!response.ok) {
                    // Обрабатываем ошибки с сервера
                    if (response.status === 403) {
                        throw new Error('Доступ запрещён. Требуется роль администратора.');
                    } else if (response.status === 401) {
                        throw new Error('Не авторизован. Пожалуйста, войдите в систему.');
                    }
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                if (data.success) {
                    showAlert('✅ Подключение к API Перевозчика успешно!', 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка подключения');
                }
            } catch (error) {
                console.error('Error testing Carrier API:', error);
                showAlert('⚠️ Ошибка подключения к API Перевозчика:\n\n' + error.message + '\n\nПроверьте консоль браузера для деталей.', 'error');
            }
        }

        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        async function syncStations() {
            const button = document.getElementById('sync-stations-btn');
            const resultDiv = document.getElementById('sync-stations-result');
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳ Синхронизация...';
            resultDiv.innerHTML = '<div class="alert alert-info">⏳ Синхронизация станций с API перевозчика...<br><small>Это может занять до 3 минут</small></div>';
            
            console.log('Starting stations sync...');
            
            try {
                // Создаём AbortController для таймаута (180 секунд = 3 минуты)
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 180000);
                
                const response = await fetch(`${API_BASE}/stations/sync`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    signal: controller.signal,
                });
                
                clearTimeout(timeoutId);
                
                console.log('Sync response status:', response.status);
                
                const data = await response.json();
                console.log('Sync response data:', data);
                
                if (!response.ok) {
                    // Обрабатываем ошибки с сервера
                    if (response.status === 403) {
                        throw new Error('Доступ запрещён. Требуется роль администратора.');
                    } else if (response.status === 401) {
                        throw new Error('Не авторизован. Пожалуйста, войдите в систему.');
                    }
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success">✅ Синхронизация завершена успешно!<br>Синхронизировано станций: <strong>${data.synced_count || 0}</strong></div>`;
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка синхронизации');
                }
            } catch (error) {
                console.error('Error syncing stations:', error);
                let errorMessage = error.message;
                
                if (error.name === 'AbortError') {
                    errorMessage = 'Превышен лимит времени ожидания (3 минуты).<br><br>' +
                                   '<strong>Возможные причины:</strong><br>' +
                                   '• Неверный ключ API (проверьте настройки в разделе "API Перевозчика")<br>' +
                                   '• API перевозчика недоступен<br>' +
                                   '• Проблемы с сетевым подключением<br>' +
                                   '• Слишком много станций для загрузки<br><br>' +
                                   '<strong>Решение:</strong><br>' +
                                   '1. Проверьте логи Laravel: <code>tail -f storage/logs/laravel.log</code><br>' +
                                   '2. Проверьте настройки API в разделе "API Перевозчика" выше<br>' +
                                   '3. Убедитесь, что URL и ключ API настроены правильно<br>' +
                                   '4. Нажмите "Проверить подключение" для диагностики';
                } else if (error.message.includes('403') || error.message.includes('Доступ запрещён')) {
                    errorMessage = 'Доступ запрещён. Убедитесь, что вы вошли как администратор.';
                } else if (error.message.includes('401') || error.message.includes('Не авторизован')) {
                    errorMessage = 'Сессия истекла. Пожалуйста, войдите в систему заново.';
                } else if (error.message.includes('ключ') || error.message.includes('x-access-token') || error.message.includes('не настроен')) {
                    errorMessage += '<br><br><strong>💡 Совет:</strong> Проверьте настройки API в разделе "API Перевозчика" выше. Убедитесь, что URL и ключ доступа указаны правильно.';
                }
                
                resultDiv.innerHTML = `<div class="alert alert-error">⚠️ Ошибка синхронизации станций:<br><br>${errorMessage}</div>`;
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    </script>
</body>
</html>

