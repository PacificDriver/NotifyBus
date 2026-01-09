<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Управление водителями</title>
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
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        }

        .search-box input {
            flex: 1;
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

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 31, 54, 0.55);
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 18px;
            width: min(600px, 92vw);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(20, 31, 54, 0.25);
        }

        .modal-header {
            padding: 24px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 18px 18px 0 0;
        }

        .modal-header h3 {
            font-size: 1.4rem;
            margin: 0;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #dbe4ff;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-group .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group input[type="checkbox"] {
            width: auto;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .error-message {
            color: #fa5252;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .info-box {
            padding: 16px;
            background: #e7f5ff;
            border-radius: 8px;
            border-left: 4px solid #1c7ed6;
            margin-bottom: 20px;
        }

        .info-box strong {
            color: #1c7ed6;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 14px;
            border: 1px solid #dbe4ff;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #495057;
        }

        .pagination button:hover:not(:disabled) {
            background: #edf2ff;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
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

        /* Стили для модального окна с паролем */
        .password-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 31, 54, 0.65);
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .password-modal.active {
            display: flex;
        }

        .password-modal-content {
            background: white;
            border-radius: 18px;
            width: min(500px, 92vw);
            box-shadow: 0 25px 60px rgba(20, 31, 54, 0.35);
            overflow: hidden;
        }

        .password-modal-header {
            padding: 24px 30px;
            background: linear-gradient(135deg, #51cf66, #37b24d);
            color: white;
        }

        .password-modal-header h3 {
            font-size: 1.4rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-modal-body {
            padding: 30px;
        }

        .password-display {
            background: #f8f9fa;
            border: 2px solid #37b24d;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .password-display label {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .password-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2b8a3e;
            font-family: 'Courier New', monospace;
            user-select: all;
            word-break: break-all;
            margin: 10px 0;
        }

        .password-warning {
            background: #fff3bf;
            border-left: 4px solid #ffd43b;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #5c5f66;
        }

        .password-warning strong {
            color: #d9480f;
        }

        .copy-btn {
            background: #37b24d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .copy-btn:hover {
            background: #2f9e44;
            transform: translateY(-1px);
        }

        .copy-btn:active {
            transform: translateY(0);
        }

        .copy-btn.copied {
            background: #51cf66;
        }

        .password-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚗 Управление водителями</h1>
        <div class="user-info">
            <span class="user-name">{{ auth()->user()->name ?? 'Пользователь' }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        </div>
    </div>
    
    <div class="container">
        <a href="/dashboard" class="back-link">← Вернуться на главную</a>

        <div class="card">
            <div class="card-header">
                <h2>Список водителей</h2>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Добавить водителя</button>
            </div>

            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Поиск по имени или username..." onkeyup="searchDrivers()">
                <select id="filterActive" onchange="searchDrivers()">
                    <option value="">Все</option>
                    <option value="1" selected>Активные</option>
                    <option value="0">Неактивные</option>
                </select>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Username</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="driversTable">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">Загрузка...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <!-- Модальное окно создания/редактирования -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Добавить водителя</h3>
            </div>
            <div class="modal-body">
                <form id="driverForm">
                    <input type="hidden" id="driverId">
                    
                    <div class="form-group">
                        <label>Фамилия *</label>
                        <input type="text" id="lastName" required>
                        <div class="error-message" id="error-last_name"></div>
                    </div>

                    <div class="form-group">
                        <label>Имя *</label>
                        <input type="text" id="firstName" required>
                        <div class="error-message" id="error-first_name"></div>
                    </div>

                    <div class="form-group">
                        <label>Отчество</label>
                        <input type="text" id="middleName">
                    </div>

                    <div class="form-group">
                        <label>Username (логин) *</label>
                        <input type="text" id="username" required>
                        <div class="error-message" id="error-username"></div>
                    </div>

                    <div class="form-group">
                        <label>Пароль <span id="passwordHint">(оставьте пустым для автогенерации)</span></label>
                        <input type="password" id="password">
                        <div class="error-message" id="error-password"></div>
                    </div>

                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="tel" id="phone" placeholder="+79001234567">
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="isActive" checked>
                            <label for="isActive" style="margin: 0;">Активен</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Модальное окно с паролем -->
    <div id="passwordModal" class="password-modal">
        <div class="password-modal-content">
            <div class="password-modal-header">
                <h3>✅ Водитель успешно создан</h3>
            </div>
            <div class="password-modal-body">
                <div class="password-display">
                    <label>Сгенерированный пароль:</label>
                    <div class="password-value" id="generatedPassword"></div>
                </div>
                
                <div class="password-warning">
                    <strong>⚠️ Важно!</strong> Сохраните этот пароль в надежном месте. 
                    Он больше не будет показан в системе.
                </div>

                <div class="password-modal-actions">
                    <button class="copy-btn" onclick="copyPassword()">
                        📋 Скопировать пароль
                    </button>
                    <button class="btn btn-secondary" onclick="closePasswordModal()">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let currentDriver = null;

        async function loadDrivers(page = 1) {
            const search = document.getElementById('searchInput').value;
            const isActive = document.getElementById('filterActive').value;
            
            let url = `/api/drivers?page=${page}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (isActive !== '') url += `&is_active=${isActive}`;

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                
                if (data.success && data.data) {
                    displayDrivers(data.data.data);
                    displayPagination(data.data);
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка');
                }
            } catch (error) {
                console.error('Error loading drivers:', error);
                document.getElementById('driversTable').innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">
                            <div style="color: #fa5252; margin-bottom: 10px;">❌ Ошибка загрузки данных</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">${escapeHtml(error.message)}</div>
                            <button class="btn btn-secondary" style="margin-top: 15px;" onclick="loadDrivers()">Повторить попытку</button>
                        </td>
                    </tr>
                `;
                document.getElementById('pagination').innerHTML = '';
            }
        }

        function displayDrivers(drivers) {
            const tbody = document.getElementById('driversTable');
            
            if (!drivers || drivers.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <div style="font-size: 3rem; margin-bottom: 15px;">🚗</div>
                            <div style="font-size: 1.1rem; color: #495057; font-weight: 600; margin-bottom: 8px;">Водители не найдены</div>
                            <div style="color: #6c757d; font-size: 0.9rem;">Нажмите кнопку "Добавить водителя" чтобы создать первого водителя</div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = drivers.map(driver => `
                <tr>
                    <td>${driver.id}</td>
                    <td>${escapeHtml(driver.last_name)} ${escapeHtml(driver.first_name)} ${escapeHtml(driver.middle_name || '')}</td>
                    <td>${escapeHtml(driver.username)}</td>
                    <td>${escapeHtml(driver.phone || '—')}</td>
                    <td>${escapeHtml(driver.email || '—')}</td>
                    <td>
                        <span class="badge ${driver.is_active ? 'badge-success' : 'badge-danger'}">
                            ${driver.is_active ? 'Активен' : 'Неактивен'}
                        </span>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <button class="btn btn-secondary btn-small" onclick="openEditModal(${driver.id})">Редактировать</button>
                            <a href="/dashboard/drivers/${driver.id}/trips" class="btn btn-secondary btn-small">Рейсы</a>
                            <button class="btn btn-danger btn-small" onclick="deleteDriver(${driver.id}, '${escapeHtml(driver.username)}')">Удалить</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function displayPagination(data) {
            const container = document.getElementById('pagination');
            if (!data || data.last_page <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            
            if (data.current_page > 1) {
                html += `<button onclick="loadDrivers(${data.current_page - 1})">‹ Пред</button>`;
            }

            for (let i = 1; i <= data.last_page; i++) {
                if (i === data.current_page) {
                    html += `<button class="active" disabled>${i}</button>`;
                } else if (Math.abs(i - data.current_page) < 3 || i === 1 || i === data.last_page) {
                    html += `<button onclick="loadDrivers(${i})">${i}</button>`;
                } else if (Math.abs(i - data.current_page) === 3) {
                    html += '<span style="padding: 8px;">...</span>';
                }
            }

            if (data.current_page < data.last_page) {
                html += `<button onclick="loadDrivers(${data.current_page + 1})">След ›</button>`;
            }

            container.innerHTML = html;
            currentPage = data.current_page;
        }

        function searchDrivers() {
            loadDrivers(1);
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Добавить водителя';
            document.getElementById('driverForm').reset();
            document.getElementById('driverId').value = '';
            document.getElementById('passwordHint').textContent = '(оставьте пустым для автогенерации)';
            document.getElementById('isActive').checked = true;
            clearErrors();
            document.getElementById('driverModal').classList.add('active');
        }

        async function openEditModal(id) {
            try {
                const response = await fetch(`/api/drivers/${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });

                const result = await response.json();
                
                if (result.success && result.data) {
                    const driver = result.data;
                    document.getElementById('modalTitle').textContent = 'Редактировать водителя';
                    document.getElementById('driverId').value = driver.id;
                    document.getElementById('firstName').value = driver.first_name;
                    document.getElementById('lastName').value = driver.last_name;
                    document.getElementById('middleName').value = driver.middle_name || '';
                    document.getElementById('username').value = driver.username;
                    document.getElementById('phone').value = driver.phone || '';
                    document.getElementById('email').value = driver.email || '';
                    document.getElementById('password').value = '';
                    document.getElementById('passwordHint').textContent = '(оставьте пустым, чтобы не менять)';
                    document.getElementById('isActive').checked = driver.is_active;
                    clearErrors();
                    document.getElementById('driverModal').classList.add('active');
                }
            } catch (error) {
                alert('Ошибка загрузки данных водителя');
            }
        }

        function closeModal() {
            document.getElementById('driverModal').classList.remove('active');
        }

        function showPasswordModal(password) {
            document.getElementById('generatedPassword').textContent = password;
            document.getElementById('passwordModal').classList.add('active');
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('active');
        }

        function copyPassword() {
            const password = document.getElementById('generatedPassword').textContent;
            const button = event.target.closest('.copy-btn');
            
            navigator.clipboard.writeText(password).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '✅ Скопировано!';
                button.classList.add('copied');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = password;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    const originalText = button.innerHTML;
                    button.innerHTML = '✅ Скопировано!';
                    button.classList.add('copied');
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    alert('Не удалось скопировать. Пожалуйста, скопируйте пароль вручную.');
                }
                document.body.removeChild(textArea);
            });
        }


        document.getElementById('driverForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const driverId = document.getElementById('driverId').value;
            const isEdit = !!driverId;

            const data = {
                first_name: document.getElementById('firstName').value,
                last_name: document.getElementById('lastName').value,
                middle_name: document.getElementById('middleName').value || null,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value || null,
                phone: document.getElementById('phone').value || null,
                email: document.getElementById('email').value || null,
                is_active: document.getElementById('isActive').checked,
            };

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Сохранение...';
            clearErrors();

            try {
                const url = isEdit ? `/api/drivers/${driverId}` : '/api/drivers';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    closeModal();
                    
                    if (result.generated_password) {
                        // Показываем модальное окно с паролем
                        showPasswordModal(result.generated_password);
                    } else {
                        alert(result.message || 'Водитель сохранен');
                    }
                    
                    loadDrivers(currentPage);
                } else {
                    if (result.errors) {
                        displayErrors(result.errors);
                    } else {
                        alert(result.message || 'Ошибка при сохранении');
                    }
                }
            } catch (error) {
                alert('Ошибка при сохранении водителя');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Сохранить';
            }
        });

        function displayErrors(errors) {
            for (const [field, messages] of Object.entries(errors)) {
                const errorEl = document.getElementById(`error-${field}`);
                if (errorEl && Array.isArray(messages)) {
                    errorEl.textContent = messages[0];
                }
            }
        }

        function clearErrors() {
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
        }

        async function deleteDriver(id, username) {
            if (!confirm(`Удалить водителя ${username}? Это действие нельзя отменить.`)) return;

            try {
                const response = await fetch(`/api/drivers/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'include',
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('Водитель удален');
                    loadDrivers(currentPage);
                } else {
                    alert(result.message || 'Ошибка при удалении');
                }
            } catch (error) {
                alert('Ошибка при удалении водителя');
            }
        }

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

        // Закрытие модального окна по клику вне его
        document.getElementById('driverModal').addEventListener('click', (e) => {
            if (e.target.id === 'driverModal') closeModal();
        });

        document.getElementById('passwordModal').addEventListener('click', (e) => {
            if (e.target.id === 'passwordModal') closePasswordModal();
        });

        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            loadDrivers();
        });
    </script>
</body>
</html>
