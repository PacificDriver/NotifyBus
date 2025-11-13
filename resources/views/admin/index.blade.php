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
                    <small>Команда</small>
                    <h3>👥 Управление операторами</h3>
                    <p>Добавление, блокировка и сброс паролей операторов, работающих с панелью уведомлений.</p>
                    <div class="setting-actions">
                        <a href="/admin/settings#operators" class="btn btn-primary" style="text-decoration: none;">Управлять операторами</a>
                    </div>
                </div>

                <div class="setting-item">
                    <small>Инфраструктура</small>
                    <h3>⚙️ Очереди и фоновые задачи</h3>
                    <p>Мониторинг worker-процессов, которые отправляют уведомления. Убедитесь, что Supervisor запущен.</p>
                    <div class="setting-actions">
                        <button class="btn btn-primary" onclick="showQueueInfo()">Памятка по очередям</button>
                    </div>
                </div>
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
            });
        </script>
    </div>
</body>
</html>


