<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система уведомлений пассажиров</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 1000px;
            width: 100%;
            padding: 50px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin: 40px 0;
        }
        
        .feature {
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .feature h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .feature p {
            color: #666;
            line-height: 1.6;
        }
        
        .buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }
        
        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #764ba2;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #2196F3;
        }
        
        .info strong {
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Система уведомлений пассажиров</h1>
        <p class="subtitle">Автоматическая рассылка уведомлений об изменениях в расписании рейсов</p>
        
        <div class="features">
            <div class="feature">
                <h3>🚌 Управление рейсами</h3>
                <p>Поиск и выбор отмененных или задержанных рейсов для уведомления пассажиров</p>
            </div>
            
            <div class="feature">
                <h3>👥 База пассажиров</h3>
                <p>Автоматическая загрузка контактов пассажиров из системы перевозчика</p>
            </div>
            
            <div class="feature">
                <h3>✉️ Мультиканальность</h3>
                <p>Отправка уведомлений через Email и WhatsApp с умной очередью</p>
            </div>
            
            <div class="feature">
                <h3>📝 Шаблоны сообщений</h3>
                <p>Готовые шаблоны с автоподстановкой данных пассажира и рейса</p>
            </div>
            
            <div class="feature">
                <h3>📊 Статистика</h3>
                <p>Отслеживание статуса доставки каждого уведомления в реальном времени</p>
            </div>
            
            <div class="feature">
                <h3>🔒 Безопасность</h3>
                <p>Разделение прав доступа для администраторов и операторов</p>
            </div>
        </div>
        
        <div class="buttons">
            <a href="/dashboard" class="btn btn-primary">Панель оператора</a>
            <a href="/admin" class="btn btn-secondary">Панель администратора</a>
            <a href="/api/user" class="btn btn-secondary">API Документация</a>
        </div>
        
        <div class="info">
            <strong>Для начала работы:</strong> Войдите в систему под учетной записью оператора или администратора. 
            Если база данных пуста, выполните команду <code>php artisan db:seed</code> для создания тестовых данных.
        </div>
    </div>
</body>
</html>


