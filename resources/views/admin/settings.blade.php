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
            background: white;
            color: #3b5bdb;
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

        .back-link {
            color: #5c7cfa;
            text-decoration: none;
            padding: 10px 18px;
            background: #edf2ff;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 600;
            border: 1px solid #dbe4ff;
        }

        .back-link:hover {
            background: #dbe4ff;
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
            color: #2d334a;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #e4e7ff;
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333d52;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e7ff;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #697089;
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

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 31, 54, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.25s ease;
        }

        .modal-overlay.visible {
            display: flex;
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

        .template-manager {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .template-manager-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .template-create-form {
            padding: 20px;
            border: 1px solid #e1e7ff;
            border-radius: 12px;
            background: #f8faff;
            box-shadow: inset 0 1px 3px rgba(102, 126, 234, 0.07);
        }

        .template-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .template-form-grid label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #3f475e;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .template-form-grid input,
        .template-form-grid select,
        .template-form-grid textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #dbe4ff;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .template-form-grid .full-width {
            grid-column: 1 / -1;
        }

        .checkbox-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .template-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .template-card {
            border: 1px solid #e1e7ff;
            border-radius: 12px;
            padding: 20px;
            background: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.08);
        }

        .template-card-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
        }

        .template-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d3f9d8;
            color: #2f9e44;
        }

        .badge-warning {
            background: #fff3bf;
            color: #f08c00;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Настройки системы</h1>
        <a href="/admin" class="back-link">← Назад к панели</a>
    </div>

    <div id="modal-root" class="modal-overlay">
        <div class="modal-content">
            <div id="modal-header" class="modal-header info">
                <div id="modal-icon" class="modal-icon">ℹ️</div>
                <div id="modal-title" class="modal-title">Сообщение</div>
            </div>
            <div id="modal-body" class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-primary" id="modal-close">Понятно</button>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div id="alert-container"></div>

        <div class="card">
                <div class="tabs">
                    <button class="tab active" data-tab-target="whatsapp" onclick="switchTab('whatsapp', this)">📱 WhatsApp</button>
                    <button class="tab" data-tab-target="email" onclick="switchTab('email', this)">✉️ Email</button>
                    <button class="tab" data-tab-target="carrier" onclick="switchTab('carrier', this)">🚌 API Перевозчика</button>
                    <button class="tab" data-tab-target="notification" onclick="switchTab('notification', this)">🔔 Уведомления</button>
                    <button class="tab" data-tab-target="importer" onclick="switchTab('importer', this)">🚍 Импорт</button>
                    <button class="tab" data-tab-target="templates" onclick="switchTab('templates', this)">📝 Шаблоны</button>
                    <button class="tab" data-tab-target="search_history" onclick="switchTab('search_history', this)">🕘 История поиска</button>
                    <button class="tab" data-tab-target="operators" onclick="switchTab('operators', this)">👥 Операторы</button>
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
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #ddd;">
                        <h4 style="margin-bottom: 15px;">📤 Тестовая отправка сообщения</h4>
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label for="whatsapp_test_phone">Номер телефона получателя</label>
                                <input type="text" id="whatsapp_test_phone" placeholder="79959640099" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small>Введите номер телефона в формате 79959640099 (без +)</small>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="whatsapp_test_message">Текст сообщения</label>
                                <input type="text" id="whatsapp_test_message" value="Тестовое сообщение от системы уведомлений" placeholder="Текст сообщения" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="testSendWhatsAppMessage()" style="margin-top: 10px;">📨 Отправить тестовое сообщение</button>
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
                        <button type="button" class="btn btn-secondary" onclick="syncStations(this)" id="sync-stations-btn">🔄 Обновить станции</button>
                    </div>
                    
                    <div id="sync-stations-result" style="margin-top: 15px;"></div>
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

            <div id="importer-tab" class="tab-content">
                <h2>Импорт пассажиров</h2>
                <p style="color:#666; margin-bottom:15px;">Настройте интервал цикличного импорта pb_order_item → локальные пассажиры. Значение применяется при следующем запуске процесса «Импорт пассажиров».</p>
                <form id="importer-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="importer_interval_minutes">Интервал запуска (минуты)</label>
                            <input type="number" id="importer_interval_minutes" name="interval_minutes" min="1" max="120" value="7" required>
                            <small>Каждые <span id="importer_interval_minutes_display">7</span> мин (<span id="importer_interval_seconds_display">420</span> сек) запускается очередной цикл импорта.</small>
                        </div>
                        <div class="form-group">
                            <label for="importer_source_table">Таблица-источник</label>
                            <input type="text" id="importer_source_table" name="source_table" placeholder="pb_order_item" required>
                            <small>Название таблицы в реплике MySQL (нужно поле ID и RACE_ID).</small>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Сохранить интервал</button>
                    </div>
                </form>
            </div>

            <div id="templates-tab" class="tab-content">
                <h2>Шаблоны сообщений</h2>
                <p style="color:#666; margin-bottom:15px;">Шаблоны используются при рассылке писем и WhatsApp-сообщений. Используйте переменные вида @{{passenger_full_name}} или @{{trip_number}} — список доступен под формой.</p>
                <div id="template-manager-settings" class="template-manager">
                    <div class="template-manager-header">
                        <div>
                            <p class="muted-text">Все изменения сохраняются сразу и доступны операторам.</p>
                        </div>
                        <button type="button" class="btn btn-secondary template-toggle-create">➕ Новый шаблон</button>
                    </div>
                    <form class="template-create-form hidden" autocomplete="off">
                        <h3 style="margin-bottom: 10px;">Новый шаблон</h3>
                        <div class="template-form-grid">
                            <label>
                                Название
                                <input type="text" name="name" required data-template-field="name" placeholder="Отмена рейса (Email)">
                            </label>
                            <label>
                                Слаг
                                <input type="text" name="slug" required data-template-field="slug" placeholder="cancel-email">
                            </label>
                            <label>
                                Тип
                                <select name="type" required>
                                    <option value="cancellation">Отмена рейса</option>
                                    <option value="delay">Задержка рейса</option>
                                    <option value="general">Общий шаблон</option>
                                </select>
                            </label>
                            <label>
                                Тема письма (Email)
                                <input type="text" name="subject" placeholder="Рейс отменён">
                            </label>
                            <label class="full-width">
                                Текст сообщения
                                <textarea name="body" rows="4" required placeholder="Здравствуйте, @{{passenger_full_name}}..."></textarea>
                            </label>
                            <label class="full-width">
                                Доступные переменные (через запятую)
                                <input type="text" name="available_variables" placeholder="@{{passenger_full_name}}, @{{trip_number}}, @{{departure_time}}">
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="is_active" checked>
                                Шаблон активен
                            </label>
                        </div>
                        <div class="template-actions">
                            <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                            <button type="button" class="btn btn-text template-toggle-create">Отмена</button>
                        </div>
                    </form>
                    <div class="template-list"></div>
                    <div style="background:#f8faff; border:1px dashed #cdd5ff; border-radius:12px; padding:16px;">
                        <strong>Популярные переменные:</strong>
                        <ul style="margin-top:8px; padding-left:18px; color:#555; line-height:1.5;">
                            <li>@{{passenger_full_name}}, @{{passenger_first_name}}</li>
                            <li>@{{trip_number}}, @{{departure_station}}, @{{arrival_station}}</li>
                            <li>@{{departure_time}}, @{{departure_date}}, @{{departure_time_only}}</li>
                            <li>@{{seat_number}}, @{{cancellation_reason}}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div id="search_history-tab" class="tab-content">
                <h2>История поиска операторов</h2>
                <p style="margin-bottom: 20px; color: #666;">
                    Журнал всех запросов отмененных рейсов. Используйте фильтры, чтобы просматривать только запросы с найденными отмененными рейсами или выбрать конкретного оператора.
                </p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="history_filter">Фильтр</label>
                        <select id="history_filter">
                            <option value="all">Все запросы</option>
                            <option value="cancelled">Только с отмененными рейсами</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="history_operator">Оператор</label>
                        <select id="history_operator">
                            <option value="">Все операторы</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="loadAdminSearchHistory()">🔄 Обновить историю</button>
                </div>

                <div id="history_result" style="margin-top: 20px;"></div>
            </div>

            <div id="operators-tab" class="tab-content">
                <h2>Управление операторами</h2>
                <p style="margin-bottom: 20px; color: #666;">
                    Создавайте новых операторов, изменяйте данные существующих и деактивируйте аккаунты. Пароль при редактировании можно оставить пустым, если менять его не требуется.
                </p>

                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 15px;">Добавить нового оператора</h3>
                    <form id="operator-create-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="operator_name">Имя</label>
                                <input type="text" id="operator_name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="operator_email">Email</label>
                                <input type="email" id="operator_email" name="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="operator_password">Пароль</label>
                                <input type="password" id="operator_password" name="password" required minlength="8">
                                <small>Минимум 8 символов</small>
                            </div>
                            <div class="form-group">
                                <label for="operator_active">Активен</label>
                                <select id="operator_active" name="is_active">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">➕ Создать оператора</button>
                    </form>
                </div>

                <div id="operators_list" style="margin-top: 20px;"></div>
            </div>
        </div>
    </div>

    <script src="/js/template-manager.js"></script>
    <script>
        const API_BASE = '/api';
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const historyState = {
            initialized: false,
            filter: 'all',
            operatorId: '',
            currentPage: 1,
            loading: false,
        };
        const operatorsState = {
            initialized: false,
            list: [],
        };
        const AVAILABLE_TABS = ['whatsapp', 'email', 'carrier', 'notification', 'importer', 'templates', 'search_history', 'operators'];
        const DEFAULT_TAB = 'whatsapp';

        const modalElements = {
            root: null,
            header: null,
            icon: null,
            title: null,
            body: null,
            closeBtn: null,
            initialized: false
        };

        const modalIcons = {
            success: '✅',
            error: '⛔',
            warning: '⚠️',
            info: 'ℹ️'
        };

        function ensureModalElements() {
            if (!modalElements.root) {
                modalElements.root = document.getElementById('modal-root');
                modalElements.header = document.getElementById('modal-header');
                modalElements.icon = document.getElementById('modal-icon');
                modalElements.title = document.getElementById('modal-title');
                modalElements.body = document.getElementById('modal-body');
                modalElements.closeBtn = document.getElementById('modal-close');
            }
            return modalElements.root && modalElements.header && modalElements.icon && modalElements.title && modalElements.body && modalElements.closeBtn;
        }

        function closeModal() {
            if (ensureModalElements()) {
                modalElements.root.classList.remove('visible');
            }
        }

        function handleModalKeydown(event) {
            if (event.key === 'Escape' && modalElements.root?.classList.contains('visible')) {
                closeModal();
            }
        }

        function showModal({ title = 'Сообщение', message = '', type = 'info' } = {}) {
            if (!ensureModalElements()) {
                console.warn('Modal container not ready', { title, message, type });
                return;
            }

            const safeType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
            modalElements.header.className = `modal-header ${safeType}`;
            modalElements.icon.textContent = modalIcons[safeType] || modalIcons.info;
            modalElements.title.textContent = title;
            modalElements.body.innerHTML = (message ?? '').toString().replace(/\n/g, '<br>');
            modalElements.root.classList.add('visible');
        }

        function initializeModal() {
            if (!ensureModalElements() || modalElements.initialized) {
                return;
            }
            modalElements.closeBtn.addEventListener('click', closeModal);
            modalElements.root.addEventListener('click', (event) => {
                if (event.target === modalElements.root) {
                    closeModal();
                }
            });
            window.addEventListener('keydown', handleModalKeydown);
            modalElements.initialized = true;
        }

        // Загрузка настроек при открытии страницы
        document.addEventListener('DOMContentLoaded', function() {
            initializeModal();
            initImporterControls();
            applyInitialTabFromHash();
            loadSettings();
            initTemplateManagerSettings();
            window.addEventListener('hashchange', applyInitialTabFromHash);
        });

        function switchTab(tabName, triggerElement = null) {
            activateTab(tabName, triggerElement);
            if (window.location.hash !== `#${tabName}` && history.replaceState) {
                history.replaceState(null, '', `#${tabName}`);
            } else if (window.location.hash !== `#${tabName}`) {
                window.location.hash = tabName;
            }
        }

        function activateTab(tabName, triggerElement = null) {
            const targetTab = AVAILABLE_TABS.includes(tabName) ? tabName : DEFAULT_TAB;

            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));

            const content = document.getElementById(`${targetTab}-tab`);
            if (content) {
                content.classList.add('active');
            }

            const button = triggerElement || document.querySelector(`.tab[data-tab-target="${targetTab}"]`);
            if (button) {
                button.classList.add('active');
            }

            if (targetTab === 'search_history' && !historyState.initialized) {
                initializeHistoryTab();
            }

            if (targetTab === 'operators' && !operatorsState.initialized) {
                initializeOperatorsTab();
            }
        }

        function applyInitialTabFromHash() {
            let hash = (window.location.hash || '').replace('#', '');
            if (!AVAILABLE_TABS.includes(hash)) {
                hash = DEFAULT_TAB;
            }
            activateTab(hash);
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
                    fillForm('notification', data.data.notification || {});
                        const importerSettings = data.data.importer || {};
                        if (!('interval_seconds' in importerSettings)) {
                            importerSettings.interval_seconds = 420;
                        }
                        if (!('source_table' in importerSettings)) {
                            importerSettings.source_table = 'pb_order_item';
                        }
                        fillForm('importer', importerSettings);
                }
            } catch (error) {
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка при загрузке настроек: ' + error.message + '\n\nОбратитесь к администратору.', 'error');
            }
        }

        function fillForm(group, settings) {
            if (group === 'importer') {
                const seconds = parseInt(settings.interval_seconds ?? 420, 10);
                updateImporterInputs(seconds);
                const tableInput = document.getElementById('importer_source_table');
                if (tableInput) {
                    tableInput.value = settings.source_table || 'pb_order_item';
                }
                return;
            }
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

        function updateImporterInputs(seconds) {
            const minutesInput = document.getElementById('importer_interval_minutes');
            const minutesDisplay = document.getElementById('importer_interval_minutes_display');
            const secondsDisplay = document.getElementById('importer_interval_seconds_display');

            if (!minutesInput) {
                return;
            }

            const minutes = Math.max(1, Math.round((seconds || 420) / 60));
            minutesInput.value = minutes;
            if (minutesDisplay) {
                minutesDisplay.textContent = minutes;
            }
            if (secondsDisplay) {
                secondsDisplay.textContent = minutes * 60;
            }
        }

        function initImporterControls() {
            const minutesInput = document.getElementById('importer_interval_minutes');
            if (!minutesInput) {
                return;
            }

            minutesInput.addEventListener('input', (event) => {
                const minutes = Math.max(1, Math.round(parseFloat(event.target.value || '1')));
                updateImporterInputs(minutes * 60);
            });

            const initialMinutes = Math.max(1, Math.round(parseFloat(minutesInput.value || '7')));
            updateImporterInputs(initialMinutes * 60);
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

        const importerForm = document.getElementById('importer-form');
        if (importerForm) {
            importerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await saveSettings('importer', new FormData(importerForm));
            });
        }

        function buildSettingsPayload(group, formData) {
            if (group === 'importer') {
                const minutesValue = parseFloat(formData.get('interval_minutes'));
                const tableValue = (formData.get('source_table') || '').trim();

                if (!Number.isFinite(minutesValue) || minutesValue < 1) {
                    throw new Error('Укажите интервал (минимум 1 минута).');
                }

                if (!tableValue) {
                    throw new Error('Укажите таблицу-источник.');
                }

                return {
                    interval_seconds: Math.max(60, Math.round(minutesValue * 60)),
                    source_table: tableValue,
                };
            }

            const settings = {};
            formData.forEach((value, key) => {
                if (typeof value === 'string') {
                    value = value.trim();
                }

                if ((key === 'key' || key === 'password' || key === 'api_token' || key === 'webhook_secret') && !value) {
                    return;
                }

                settings[key] = value;
            });

            if (group === 'whatsapp') {
                const checkbox = document.getElementById('whatsapp_use_async');
                settings.use_async = checkbox ? checkbox.checked : false;
            }

            return settings;
        }

        async function saveSettings(group, formData) {
            let settings;
            try {
                settings = buildSettingsPayload(group, formData);
            } catch (error) {
                showAlert(error.message || 'Проверьте введенные данные', 'error');
                return;
            }

            if (!settings || Object.keys(settings).length === 0) {
                showAlert('Заполните параметры перед сохранением.', 'warning');
                return;
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
            
            // Собираем только непустые значения и не замаскированные
            formData.forEach((value, key) => {
                // Пропускаем пустые значения
                if (value && value.trim() !== '') {
                    // Пропускаем замаскированные значения (токены и пароли)
                    if (!value.startsWith('***') && !value.startsWith('tok***')) {
                        settings[key] = value;
                    }
                    // Если значение замаскировано, не отправляем его - сервер возьмет из БД
                }
            });

            const checkbox = document.getElementById('whatsapp_use_async');
            if (checkbox) {
                settings.use_async = checkbox.checked;
            }

            console.log('Testing WhatsApp API connection with settings:', settings);

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

                console.log('Response status:', response.status);

                const data = await response.json();
                console.log('Response data:', data);

                if (!response.ok) {
                    // Если это не успешный ответ, но есть сообщение об ошибке
                    if (data.message) {
                        throw new Error(data.message);
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                if (data.success) {
                    showAlert('✅ Подключение к WhatsApp API успешно!', 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка подключения');
                }
            } catch (error) {
                console.error('WhatsApp API test error:', error);
                showAlert('⚠️ В текущий момент сервис недоступен.\n\nОшибка подключения к WhatsApp API: ' + error.message + '\n\nОбратитесь к администратору.', 'error');
            }
        }

        async function testSendWhatsAppMessage() {
            const testPhone = document.getElementById('whatsapp_test_phone').value.trim();
            const testMessage = document.getElementById('whatsapp_test_message').value.trim();
            
            if (!testPhone) {
                showAlert('Укажите номер телефона для тестовой отправки', 'error');
                return;
            }
            
            if (!testMessage) {
                showAlert('Укажите текст сообщения', 'error');
                return;
            }
            
            // Получаем настройки из формы
            const form = document.getElementById('whatsapp-form');
            const formData = new FormData(form);
            const settings = {};
            
            formData.forEach((value, key) => {
                if (value && value.trim() !== '') {
                    if (!value.startsWith('***') && !value.startsWith('tok***')) {
                        settings[key] = value;
                    }
                }
            });
            
            showAlert('Отправка тестового сообщения...', 'info');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test/whatsapp/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        phone: testPhone,
                        message: testMessage,
                        settings: settings,
                    }),
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }
                
                if (data.success) {
                    showAlert('✅ Тестовое сообщение успешно отправлено на номер ' + testPhone + '!', 'success');
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка отправки');
                }
            } catch (error) {
                console.error('WhatsApp test send error:', error);
                showAlert('⚠️ Ошибка отправки тестового сообщения:\n\n' + error.message + '\n\nОбратитесь к администратору.', 'error');
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

        function showAlert(message, type = 'info', title = '') {
            const titles = {
                success: 'Успешно',
                error: 'Ошибка',
                warning: 'Предупреждение',
                info: 'Информация'
            };
            showModal({
                type,
                title: title || titles[type] || titles.info,
                message
            });
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

        function initializeHistoryTab() {
            historyState.initialized = true;
            const filterSelect = document.getElementById('history_filter');
            const operatorSelect = document.getElementById('history_operator');

            filterSelect.addEventListener('change', () => {
                historyState.filter = filterSelect.value;
                loadAdminSearchHistory();
            });

            operatorSelect.addEventListener('change', () => {
                historyState.operatorId = operatorSelect.value;
                loadAdminSearchHistory();
            });

            loadOperatorsData({ populateHistorySelect: true }).then(() => {
                loadAdminSearchHistory();
            });
        }

        async function loadAdminSearchHistory(page = 1) {
            const container = document.getElementById('history_result');
            if (!container) return;

            historyState.loading = true;
            historyState.currentPage = page;

            container.innerHTML = '<div class="alert alert-info">⏳ Загрузка истории...</div>';

            const params = new URLSearchParams({
                filter: historyState.filter,
                page: page.toString(),
                per_page: '25',
            });

            if (historyState.operatorId) {
                params.append('user_id', historyState.operatorId);
            }

            try {
                const response = await fetch(`${API_BASE}/search-history?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Не удалось загрузить историю поиска');
                }

                const items = data.data?.data || data.data || [];

                if (items.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">История поиска пуста.</div>';
                    return;
                }

                const rows = items.map(item => {
                    const createdAt = formatDateTimeLocal(item.created_at);
                    const route = `${item.from_station_name || '—'} → ${item.to_station_name || '—'}`;
                    const tripDate = item.trip_date ? formatDateLocal(item.trip_date) : '—';
                    const cancelled = item.cancelled_count ?? 0;
                    const operator = item.user?.name ? `${item.user.name} (${item.user.email})` : '—';

                    const badgeClass = cancelled > 0 ? 'badge badge-danger' : 'badge badge-warning';

                    return `
                        <tr>
                            <td>${createdAt}</td>
                            <td>${route}</td>
                            <td>${tripDate}</td>
                            <td style="text-align:center;"><span class="${badgeClass}">${cancelled}</span></td>
                            <td>${operator}</td>
                        </tr>
                    `;
                }).join('');

                const table = `
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background:#f0f0f0;">
                                    <th style="padding:10px; border-bottom:1px solid #ddd;">Когда</th>
                                    <th style="padding:10px; border-bottom:1px solid #ddd;">Маршрут</th>
                                    <th style="padding:10px; border-bottom:1px солид #ddd;">Дата рейса</th>
                                    <th style="padding:10px; border-bottom:1px solid #ddd;">Отмененные</th>
                                    <th style="padding:10px; border-bottom:1px solid #ddd;">Оператор</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                `;

                container.innerHTML = table;
            } catch (error) {
                container.innerHTML = `<div class="alert alert-error">⚠️ Ошибка загрузки истории поиска:<br>${error.message}</div>`;
            } finally {
                historyState.loading = false;
            }
        }

        function formatDateTimeLocal(value) {
            if (!value) return '—';
            try {
                const date = new Date(value);
                if (isNaN(date.getTime())) return value;
                return date.toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
            } catch (e) {
                return value;
            }
        }

        function formatDateLocal(value) {
            if (!value) return '—';
            try {
                const date = new Date(value);
                if (isNaN(date.getTime())) return value;
                return date.toLocaleDateString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                });
            } catch (e) {
                return value;
            }
        }

        function initializeOperatorsTab() {
            operatorsState.initialized = true;

            const createForm = document.getElementById('operator-create-form');
            if (createForm) {
                createForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    await createOperator(new FormData(createForm));
                });
            }

            loadOperatorsData({ populateHistorySelect: !historyState.initialized }).then(() => {
                renderOperatorsList();
            });
        }

        async function loadOperatorsData(options = { populateHistorySelect: false }) {
            try {
                const response = await fetch(`${API_BASE}/operators`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Не удалось загрузить список операторов');
                }

                operatorsState.list = data.data || [];

                if (options.populateHistorySelect && historyState.initialized) {
                    populateHistoryOperatorSelect();
                }

                if (operatorsState.initialized) {
                    renderOperatorsList();
                }
            } catch (error) {
                console.error('Error loading operators:', error);
                if (options.populateHistorySelect && historyState.initialized) {
                    populateHistoryOperatorSelect();
                }
                const listContainer = document.getElementById('operators_list');
                if (listContainer) {
                    listContainer.innerHTML = `<div class="alert alert-error">⚠️ Не удалось загрузить операторов: ${error.message}</div>`;
                }
            }
        }

        function populateHistoryOperatorSelect() {
            const operatorSelect = document.getElementById('history_operator');
            if (!operatorSelect) return;

            operatorSelect.innerHTML = '<option value="">Все операторы</option>';
            operatorsState.list.forEach(operator => {
                const option = document.createElement('option');
                option.value = operator.id;
                option.textContent = `${operator.name} (${operator.email})`;
                operatorSelect.appendChild(option);
            });
        }

        async function createOperator(formData) {
            const payload = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                is_active: formData.get('is_active') === '1',
            };

            try {
                const response = await fetch(`${API_BASE}/operators`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                showAlert(data.message || 'Оператор создан', 'success');
                document.getElementById('operator-create-form').reset();
                loadOperatorsData({ populateHistorySelect: historyState.initialized });
            } catch (error) {
                showAlert('⚠️ Ошибка создания оператора: ' + error.message, 'error');
            }
        }

        function renderOperatorsList() {
            const container = document.getElementById('operators_list');
            if (!container) return;

            if (!operatorsState.list || operatorsState.list.length === 0) {
                container.innerHTML = '<div class="alert alert-info">Пока нет созданных операторов.</div>';
                return;
            }

            const cards = operatorsState.list.map(operator => {
                const activeValue = operator.is_active ? '1' : '0';
                return `
                    <div class="card" style="padding: 20px; margin-bottom: 15px;">
                        <form class="operator-edit-form" data-operator-id="${operator.id}">
                            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 10px;">
                                <h3 style="margin: 0;">${operator.name}</h3>
                                <span style="color: ${operator.is_active ? '#2f9e44' : '#c92a2a'};">
                                    ${operator.is_active ? 'Активен' : 'Неактивен'}
                                </span>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Имя</label>
                                    <input type="text" name="name" value="${operator.name}" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="${operator.email}" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Новый пароль</label>
                                    <input type="password" name="password" placeholder="Оставьте пустым, если без изменений" minlength="8">
                                </div>
                                <div class="form-group">
                                    <label>Статус</label>
                                    <select name="is_active">
                                        <option value="1" ${activeValue === '1' ? 'selected' : ''}>Активен</option>
                                        <option value="0" ${activeValue === '0' ? 'selected' : ''}>Неактивен</option>
                                    </select>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">💾 Сохранить</button>
                                <button type="button" class="btn btn-secondary" onclick="deleteOperator(${operator.id})">🗑️ Удалить</button>
                            </div>
                        </form>
                    </div>
                `;
            }).join('');

            container.innerHTML = cards;

            container.querySelectorAll('.operator-edit-form').forEach(form => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const operatorId = form.getAttribute('data-operator-id');
                    const formData = new FormData(form);
                    await updateOperator(operatorId, formData);
                });
            });
        }

        async function updateOperator(operatorId, formData) {
            const payload = {
                name: formData.get('name'),
                email: formData.get('email'),
                is_active: formData.get('is_active') === '1',
            };

            const password = formData.get('password');
            if (password) {
                payload.password = password;
            }

            try {
                const response = await fetch(`${API_BASE}/operators/${operatorId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                showAlert(data.message || 'Оператор обновлен', 'success');
                loadOperatorsData({ populateHistorySelect: historyState.initialized });
            } catch (error) {
                showAlert('⚠️ Ошибка обновления оператора: ' + error.message, 'error');
            }
        }

        async function deleteOperator(operatorId) {
            if (!confirm('Удалить оператора? Вы всегда сможете создать его заново.')) {
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/operators/${operatorId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
                }

                showAlert(data.message || 'Оператор удален', 'success');
                loadOperatorsData({ populateHistorySelect: historyState.initialized });
            } catch (error) {
                showAlert('⚠️ Ошибка удаления оператора: ' + error.message, 'error');
            }
        }

        function initTemplateManagerSettings() {
            const root = document.getElementById('template-manager-settings');
            if (!root || typeof TemplateManager === 'undefined') {
                return;
            }

            if (root.__templateManager) {
                return;
            }

            const manager = new TemplateManager({
                root,
                listSelector: '.template-list',
                createFormSelector: '.template-create-form',
                toggleSelector: '.template-toggle-create',
                emptyText: 'Список шаблонов пуст. Создайте первый шаблон, чтобы операторы могли пользоваться готовыми текстами.',
            });

            manager.init();
            root.__templateManager = manager;
        }
    </script>
</body>
</html>

