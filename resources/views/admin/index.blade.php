<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель администратора</title>
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
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .setting-item {
            padding: 24px;
            background: #f8faff;
            border-radius: 12px;
            border: 1px solid #e0e7ff;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.08);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        
        .setting-item h3 {
            color: #273043;
            margin-bottom: 6px;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .setting-item small {
            color: #748ffc;
            font-weight: 600;
        }
        
        .setting-item p {
            color: #5f6573;
            line-height: 1.6;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 20px;
        }

        .process-item {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e1e7ff;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.06);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .process-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-running {
            background: #d3f9d8;
            color: #2f9e44;
        }

        .badge-stopped, .badge-exited, .badge-completed {
            background: #ffe3e3;
            color: #fa5252;
        }

        .badge-starting {
            background: #fff3bf;
            color: #f59f00;
        }

        .badge-error, .badge-backoff {
            background: #ffe3e3;
            color: #c92a2a;
        }

        .badge-unknown, .badge-idle {
            background: #e7f5ff;
            color: #1c7ed6;
        }

        .process-description {
            color: #5f6573;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .process-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }

        .process-meta div {
            background: #f8faff;
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #e7edff;
        }

        .process-meta dt {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #748ffc;
            margin-bottom: 4px;
        }

        .process-meta dd {
            margin: 0;
            font-size: 0.92rem;
            color: #374151;
        }

        .process-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .log-output {
            background: #0d1b2a;
            color: #dcecfb;
            padding: 16px;
            border-radius: 10px;
            max-height: 420px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-family: "Fira Code", "SFMono-Regular", ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.85rem;
            border: 1px solid #1f3a5f;
        }

        .log-output::-webkit-scrollbar {
            width: 8px;
        }

        .log-output::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
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

        .muted-text {
            color: #6c757d;
        }

        .error-text {
            color: #c92a2a;
            font-weight: 600;
        }

        .setting-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: auto;
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

        .btn-text {
            background: none;
            color: #5c7cfa;
            padding: 0;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-text:hover {
            text-decoration: underline;
        }
        
        .btn-danger {
            background: #ff8787;
            color: white;
        }
        
        .btn-danger:hover {
            background: #fa5252;
        }

        .hidden {
            display: none !important;
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

        /* Модальные окна */
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
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Панель администратора</h1>
        <div class="user-info">
            <span class="user-name">Администратор: <strong>{{ auth()->user()->name ?? 'Администратор' }}</strong></span>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        </div>
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
        <div class="card">
            <h2>Системные настройки</h2>
            <div class="settings-grid">
                <div class="setting-item">
                    <small>Синхронизация</small>
                    <h3>📡 API Перевозчика</h3>
                    <p>Получение отмененных рейсов, обновление станций и маршрутов из внешнего API перевозчика.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#carrier" class="btn btn-primary" style="text-decoration: none;">Настроить API</a>
                        <button class="btn btn-secondary" onclick="syncStations(this)">Синхронизировать станции</button>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Коммуникации</small>
                    <h3>✉️ Email (SMTP)</h3>
                    <p>Параметры SMTP сервера для отправки писем об отмене рейсов и служебных уведомлений.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#email" class="btn btn-primary" style="text-decoration: none;">Настроить Email</a>
                        <button class="btn btn-text" onclick="openSettingsSection('email')">Подробнее</button>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Коммуникации</small>
                    <h3>📱 WhatsApp API</h3>
                    <p>Интеграция с Wappi.pro для мгновенной отправки уведомлений пассажирам в WhatsApp.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#whatsapp" class="btn btn-primary" style="text-decoration: none;">Настроить WhatsApp</a>
                        <button class="btn btn-text" onclick="openSettingsSection('whatsapp')">Как подключить?</button>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Уведомления</small>
                    <h3>🔔 Шаблоны и рассылка</h3>
                    <p>Шаблоны сообщений, параметры рассылки и лимиты для email и WhatsApp уведомлений.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#notification" class="btn btn-primary" style="text-decoration: none;">Настроить уведомления</a>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Мониторинг</small>
                    <h3>🕒 История поисков</h3>
                    <p>Последние запросы операторов по отмененным рейсам. Помогает отслеживать активность и диагностику.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#search_history" class="btn btn-primary" style="text-decoration: none;">Просмотреть историю</a>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Импорт данных</small>
                    <h3>⬇️ Импорт пассажиров</h3>
                    <p>Управление частотой импорта, выбор таблицы источника и просмотр инструкции по запуску процесса.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#importer" class="btn btn-primary" style="text-decoration: none;">Настроить импорт</a>
                        <button class="btn btn-secondary" onclick="showModal({ type: 'info', title: 'Импорт пассажиров', message: 'Перейдите в настройки → вкладка «Импорт», чтобы изменить таблицу источника и интервал запуска. Процесс запускается из блока «Управление процессами».' })">
                            Памятка
                        </button>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Команда</small>
                    <h3>👥 Управление операторами</h3>
                    <p>Добавление, блокировка и сброс паролей операторов, работающих с панелью уведомлений.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#operators" class="btn btn-primary" style="text-decoration: none;">Управлять операторами</a>
                    </div>
                </div>

              
        </div>

        <div class="card">
            <h2>Управление процессами</h2>
            <p class="muted-text" style="margin-bottom: 18px;">Контролируйте процессы импорта и очередей рассылки. Можно запустить, остановить, перезапустить и посмотреть свежие логи.</p>
            <div id="processes-list" class="process-grid">
                <div class="process-item muted-text">Загрузка данных о процессах...</div>
            </div>
        </div>
        
        <div class="card">
            <h2>Шаблоны сообщений</h2>
            <div id="template-manager-dashboard" class="template-manager">
                <div class="template-manager-header">
                    <div>
                        <p class="muted-text">Создавайте и редактируйте тексты WhatsApp и Email перед массовой рассылкой.</p>
                        <div style="margin-top:10px; padding:12px; border-radius:10px; border:1px dashed #cdd5ff; background:#f8faff;">
                            <strong style="display:block; margin-bottom:6px;">Доступные переменные:</strong>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <span class="badge badge-warning">@{{passenger_full_name}}</span>
                                <span class="badge badge-warning">@{{passenger_first_name}}</span>
                                <span class="badge badge-warning">@{{trip_number}}</span>
                                <span class="badge badge-warning">@{{departure_station}}</span>
                                <span class="badge badge-warning">@{{arrival_station}}</span>
                                <span class="badge badge-warning">@{{departure_time}}</span>
                                <span class="badge badge-warning">@{{departure_date}}</span>
                                <span class="badge badge-warning">@{{seat_number}}</span>
                                <span class="badge badge-warning">@{{cancellation_reason}}</span>
                            </div>
                            <small style="display:block; margin-top:8px; color:#6c6f85;">Используйте @{{variable_name}} внутри темы и текста. Полный список доступен на вкладке «Шаблоны» в настройках.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary template-toggle-create">➕ Новый шаблон</button>
                </div>
                <form class="template-create-form hidden" autocomplete="off">
                    <h3 style="margin-bottom: 10px;">Новый шаблон</h3>
                    <div class="template-form-grid">
                        <label>
                            Название
                            <input type="text" name="name" required data-template-field="name" placeholder="Отмена рейса (WhatsApp)">
                        </label>
                        <label>
                            Слаг
                            <input type="text" name="slug" required data-template-field="slug" placeholder="cancel-whatsapp">
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
                            <textarea name="body" rows="4" required placeholder="Уважаемый @{{passenger_full_name}}, сообщаем об отмене рейса @{{trip_number}}..."></textarea>
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
            </div>
        </div>

        <div class="card">
            <h2>Статус сервисов</h2>
            <div style="padding: 20px;" id="services-status">
                <div style="margin-bottom: 15px;">
                    <span class="status-indicator status-ok"></span>
                    <strong>База данных:</strong> Подключена
                </div>
                <div style="margin-bottom: 15px;">
                    <span class="status-indicator status-ok"></span>
                    <strong>Redis:</strong> Активен
                </div>
                <div style="margin-bottom: 15px;" id="whatsapp-status">
                    <span class="status-indicator status-error"></span>
                    <strong>WhatsApp API:</strong> Проверка...
                </div>
                <div style="margin-bottom: 15px;" id="carrier-status">
                    <span class="status-indicator status-error"></span>
                    <strong>API Перевозчика:</strong> Проверка...
                </div>
            </div>
        </div>
        
        <script src="/js/template-manager.js"></script>
        <script>
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
                    console.warn('Modal container is not ready. Message:', message);
                    return;
                }

                const safeType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
                modalElements.header.className = `modal-header ${safeType}`;
                modalElements.icon.textContent = modalIcons[safeType] || modalIcons.info;
                modalElements.title.textContent = title;
                modalElements.body.innerHTML = (message ?? '').toString().replace(/\n/g, '<br>');
                modalElements.root.classList.add('visible');
            }

            function openSettingsSection(tabName = '') {
                if (!tabName) {
                    window.location.href = '/admin/settings';
                    return;
                }
                const normalized = tabName.replace(/^#/, '');
                window.location.href = `/admin/settings#${normalized}`;
            }

            async function checkServicesStatus() {
                try {
                    const response = await fetch('/api/settings/status', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        
                        const whatsappStatus = document.getElementById('whatsapp-status');
                        if (whatsappStatus) {
                            if (data.data && data.data.whatsapp && data.data.whatsapp.configured) {
                                whatsappStatus.innerHTML = '<span class="status-indicator status-ok"></span><strong>WhatsApp API:</strong> Настроен';
                            } else {
                                whatsappStatus.innerHTML = '<span class="status-indicator status-error"></span><strong>WhatsApp API:</strong> Не настроен (требуется конфигурация)';
                            }
                        }
                        
                        const carrierStatus = document.getElementById('carrier-status');
                        if (carrierStatus) {
                            if (data.data && data.data.carrier_api && data.data.carrier_api.configured) {
                                carrierStatus.innerHTML = '<span class="status-indicator status-ok"></span><strong>API Перевозчика:</strong> Настроен';
                            } else {
                                carrierStatus.innerHTML = '<span class="status-indicator status-error"></span><strong>API Перевозчика:</strong> Не настроен (требуется конфигурация)';
                            }
                        }
                    } else {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error checking services status:', error);
                    const whatsappStatus = document.getElementById('whatsapp-status');
                    const carrierStatus = document.getElementById('carrier-status');
                    if (whatsappStatus) {
                        whatsappStatus.innerHTML = '<span class="status-indicator status-error"></span><strong>WhatsApp API:</strong> Сервис недоступен. Обратитесь к администратору.';
                    }
                    if (carrierStatus) {
                        carrierStatus.innerHTML = '<span class="status-indicator status-error"></span><strong>API Перевозчика:</strong> Сервис недоступен. Обратитесь к администратору.';
                    }
                }
            }

            async function syncStations(buttonEl) {
                if (!buttonEl) return;
                const originalText = buttonEl.textContent;
                buttonEl.disabled = true;
                buttonEl.textContent = 'Синхронизация...';
                
                try {
                    const response = await fetch('/api/stations/sync', {
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
                        const count = data.synced_count ?? 0;
                        showModal({
                            type: 'success',
                            title: 'Синхронизация завершена',
                            message: `Станций обновлено: ${count}\n\nЛоги операции доступны в storage/logs/laravel.log.`
                        });
                    } else {
                        throw new Error(data.message || 'Неизвестная ошибка синхронизации');
                    }
                } catch (error) {
                    console.error('Error syncing stations:', error);
                    showModal({
                        type: 'error',
                        title: 'Ошибка синхронизации станций',
                        message: '⚠️ ' + (error.message || 'Неизвестная ошибка') + '\n\nПроверьте настройки API перевозчика в разделе "Системные настройки".'
                    });
                } finally {
                    buttonEl.disabled = false;
                    buttonEl.textContent = originalText;
                }
            }

            function showQueueInfo() {
                showModal({
                    type: 'info',
                    title: 'Очереди уведомлений',
                    message: 'Убедитесь, что Supervisor управляет worker-процессами.\n\n' +
                        '1. Статус: sudo supervisorctl status notifybus-worker:*\n' +
                        '2. Перезапуск: sudo supervisorctl restart notifybus-worker:*\n' +
                        '3. Логи: tail -f storage/logs/worker.log\n\n' +
                        'При изменении кода запускайте: php artisan queue:restart.'
                });
            }

            document.addEventListener('DOMContentLoaded', () => {
                initProcessControls();
                if (ensureModalElements() && !modalElements.initialized) {
                    modalElements.closeBtn.addEventListener('click', closeModal);
                    modalElements.root.addEventListener('click', (event) => {
                        if (event.target === modalElements.root) {
                            closeModal();
                        }
                    });
                    window.addEventListener('keydown', handleModalKeydown);
                    modalElements.initialized = true;
                }

                checkServicesStatus();
                initTemplateManagerDashboard();
            });

            const processState = {
                autoRefreshTimer: null,
                refreshIntervalMs: 45000,
            };

            function initProcessControls() {
                loadProcesses();

                if (processState.autoRefreshTimer) {
                    clearInterval(processState.autoRefreshTimer);
                }
                processState.autoRefreshTimer = setInterval(loadProcesses, processState.refreshIntervalMs);

                document.addEventListener('click', (event) => {
                    const actionButton = event.target.closest('[data-process-action]');
                    if (!actionButton) {
                        return;
                    }

                    const container = actionButton.closest('[data-process-name]');
                    if (!container) {
                        return;
                    }

                    const action = actionButton.dataset.processAction;
                    const name = container.dataset.processName;
                    const label = container.dataset.processLabel || name;

                    handleProcessAction(action, name, label, actionButton);
                }, { passive: false });
            }

            function escapeHtml(value) {
                if (value === undefined || value === null) {
                    return '';
                }

                return value
                    .toString()
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatStatusBadge(process) {
                const status = (process.status || 'unknown').toLowerCase();

                const statusLabels = {
                    running: 'Работает',
                    starting: 'Запуск',
                    stopped: 'Остановлен',
                    exited: 'Завершен',
                    completed: 'Завершен',
                    backoff: 'Ошибка запуска',
                    error: 'Ошибка',
                    idle: 'Ожидание',
                    pending: 'В ожидании',
                };

                const badgeClass = `badge-${status}`;
                const label = statusLabels[status] || status;

                return `<span class="status-badge ${badgeClass}">${escapeHtml(label)}</span>`;
            }

            function formatDateTime(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    return escapeHtml(value);
                }

                return date.toLocaleString('ru-RU');
            }

            function renderProcessCard(process) {
                const details = process.details || {};
                const statusBadge = formatStatusBadge(process);
                const command = details.command ? escapeHtml(details.command) : '—';
                const pid = details.pid ? escapeHtml(details.pid) : '—';
                const startedAt = formatDateTime(details.started_at);
                const finishedAt = formatDateTime(details.finished_at);
                const description = escapeHtml(process.description || '');
                const intervalSeconds = details.interval_seconds ? parseInt(details.interval_seconds, 10) : null;
                const intervalBlock = intervalSeconds
                    ? `<div>
                            <dt>Интервал</dt>
                            <dd>${Math.round(intervalSeconds / 60)} мин (${intervalSeconds} сек)</dd>
                       </div>`
                    : '';

                return `
                    <div class="process-item" data-process-name="${escapeHtml(process.name)}" data-process-label="${escapeHtml(process.label)}">
                        <div class="process-header">
                            <div>
                                <h3 style="margin-bottom: 6px;">${escapeHtml(process.label)}</h3>
                                <p class="process-description">${description || '—'}</p>
                            </div>
                            ${statusBadge}
                        </div>
                        <div class="process-meta">
                            <div>
                                <dt>Команда</dt>
                                <dd>${command}</dd>
                            </div>
                            <div>
                                <dt>PID</dt>
                                <dd>${pid}</dd>
                            </div>
                            <div>
                                <dt>Начало</dt>
                                <dd>${startedAt}</dd>
                            </div>
                            <div>
                                <dt>Завершение</dt>
                                <dd>${finishedAt}</dd>
                            </div>
                            ${intervalBlock}
                        </div>
                        <div class="process-actions">
                            <button class="btn btn-primary" data-process-action="start">Запустить</button>
                            <button class="btn btn-secondary" data-process-action="restart">Перезапустить</button>
                            <button class="btn btn-danger" data-process-action="stop">Остановить</button>
                            <button class="btn btn-text" data-process-action="logs">Показать логи</button>
                        </div>
                    </div>
                `;
            }

            async function loadProcesses() {
                const container = document.getElementById('processes-list');
                if (!container) {
                    return;
                }

                container.dataset.loading = 'true';

                try {
                    const response = await fetch('/api/processes', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    const processes = Array.isArray(payload.data) ? payload.data : [];

                    if (!processes.length) {
                        container.innerHTML = '<div class="muted-text">Нет зарегистрированных процессов. Добавьте конфигурацию в config/processes.php.</div>';
                        return;
                    }

                    container.innerHTML = processes.map(renderProcessCard).join('');
                } catch (error) {
                    console.error('Failed to load processes:', error);
                    container.innerHTML = `<div class="process-item error-text">Не удалось загрузить процессы: ${escapeHtml(error.message || error)}</div>`;
                } finally {
                    delete container.dataset.loading;
                }
            }

            async function handleProcessAction(action, name, label, buttonEl) {
                if (action === 'logs') {
                    await showProcessLogs(name, label);
                    return;
                }

                const meta = {
                    start: { endpoint: 'start', loadingText: 'Запуск...', successTitle: 'Процесс запущен', successType: 'success' },
                    stop: { endpoint: 'stop', loadingText: 'Остановка...', successTitle: 'Процесс остановлен', successType: 'info' },
                    restart: { endpoint: 'restart', loadingText: 'Перезапуск...', successTitle: 'Процесс перезапущен', successType: 'success' },
                }[action];

                if (!meta) {
                    console.warn('Unknown process action:', action);
                    return;
                }

                const originalText = buttonEl ? buttonEl.textContent : null;

                if (buttonEl) {
                    buttonEl.disabled = true;
                    buttonEl.textContent = meta.loadingText;
                }

                try {
                    const response = await fetch(`/api/processes/${encodeURIComponent(name)}/${meta.endpoint}`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        credentials: 'include',
                    });

                    const result = await response.json();

                    const success = !!result.success;
                    const message = result.message || (success ? meta.successTitle : 'Запрос завершился с ошибкой');

                    showModal({
                        type: success ? meta.successType : 'error',
                        title: `${meta.successTitle} — ${label}`,
                        message,
                    });
                } catch (error) {
                    console.error('Failed to execute action:', error);
                    showModal({
                        type: 'error',
                        title: `Ошибка — ${label}`,
                        message: error.message || 'Не удалось выполнить действие.',
                    });
                } finally {
                    if (buttonEl) {
                        buttonEl.disabled = false;
                        buttonEl.textContent = originalText;
                    }
                    loadProcesses();
                }
            }

            async function showProcessLogs(name, label) {
                try {
                    const response = await fetch(`/api/processes/${encodeURIComponent(name)}/logs`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.message || 'Не удалось получить логи');
                    }

                    const logLines = (result.data?.log || []).join('\n');
                    const logPath = result.data?.path ? `<div class="muted-text" style="margin-bottom: 12px;">Источник: ${escapeHtml(result.data.path)}</div>` : '';

                    showModal({
                        type: 'info',
                        title: `Логи процесса — ${label}`,
                        message: `${logPath}<pre class="log-output">${escapeHtml(logLines || 'Лог пуст')}</pre>`,
                    });
                } catch (error) {
                    console.error('Failed to load process logs:', error);
                    showModal({
                        type: 'error',
                        title: `Ошибка получения логов — ${label}`,
                        message: error.message || 'Не удалось получить логи процесса.',
                    });
                }
            }

            function initTemplateManagerDashboard() {
                const root = document.getElementById('template-manager-dashboard');
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
                    emptyText: 'Пока нет шаблонов. Создайте первый, чтобы ускорить рассылку.',
                });

                manager.init();
                root.__templateManager = manager;
            }
        </script>
    </div>
</body>
</html>


