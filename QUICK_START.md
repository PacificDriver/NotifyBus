# ⚡ Быстрый старт после публикации репозитория

## 📤 Шаг 1: Отправить изменения в репозиторий (локально)

Если у вас есть незакоммиченные изменения:

```bash
# Добавить все новые файлы
git add .

# Закоммитить
git commit -m "Add deployment and git setup documentation"

# Отправить в GitHub
git push origin main
```

## 📥 Шаг 2: Обновить код на сервере

### 2.1. Подключиться к серверу

```bash
ssh user@your-server
cd /var/www/html/NotifyBus
```

### 2.2. Проверить и настроить репозиторий

**Проверить текущий репозиторий:**

```bash
# Проверить, какой репозиторий настроен
git remote -v
```

**Если репозиторий не настроен или неправильный:**

```bash
# Удалить старый (если есть)
git remote remove origin

# Добавить правильный репозиторий
git remote add origin https://github.com/PacificDriver/NotifyBus.git

# Проверить снова
git remote -v
```

**Если проект еще не клонирован:**

```bash
cd /var/www/html
sudo git clone https://github.com/PacificDriver/NotifyBus.git NotifyBus
cd NotifyBus
```

### 2.3. Обновить код из репозитория

```bash
# Проверить текущую ветку
git branch

# Получить последние изменения из репозитория
git pull origin main

# Если возникли конфликты, см. раздел "Решение проблем" ниже

### 2.4. Установить зависимости и применить изменения

```bash
# Установить зависимости
composer install --no-dev --optimize-autoloader

# Применить миграции
php artisan migrate

# Очистить кэш
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Перезапустить сервисы
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

## ✅ Шаг 3: Проверить работу

1. Откройте сайт в браузере
2. Проверьте, что главная страница показывает форму входа
3. Войдите как администратор или оператор
4. Проверьте, что всё работает

## 🎯 Полная команда для обновления (после проверки репозитория)

**ВАЖНО:** Сначала выполните шаг 2.2 (проверка репозитория), затем:

```bash
cd /var/www/html/NotifyBus && \
git pull origin main && \
composer install --no-dev --optimize-autoloader && \
php artisan migrate && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
php artisan view:clear && \
sudo systemctl reload php8.2-fpm && \
sudo systemctl reload nginx
```

## 🔧 Решение проблем

### Ошибка: "fatal: not a git repository"

Репозиторий не инициализирован. Выполните:

```bash
cd /var/www/html/NotifyBus
git init
git remote add origin https://github.com/PacificDriver/NotifyBus.git
git fetch origin
git checkout -b main origin/main
```

### Ошибка: "remote origin already exists"

```bash
# Проверить текущий URL
git remote get-url origin

# Если URL неправильный, изменить:
git remote set-url origin https://github.com/PacificDriver/NotifyBus.git
```

### Ошибка: "Your branch and 'origin/main' have diverged"

Есть локальные изменения. Выполните:

```bash
# Сохранить локальные изменения
git stash

# Получить изменения из репозитория
git pull origin main

# Вернуть локальные изменения
git stash pop
```

### Ошибка: "Permission denied"

```bash
# Проверить права доступа
ls -la /var/www/html/NotifyBus

# Если нужно, исправить права
sudo chown -R www-data:www-data /var/www/html/NotifyBus
sudo chmod -R 755 /var/www/html/NotifyBus
```

## 📋 Чеклист перед обновлением

- [ ] Проверить репозиторий: `git remote -v`
- [ ] Убедиться, что репозиторий публичный или настроена авторизация
- [ ] Проверить текущую ветку: `git branch`
- [ ] Убедиться, что нет незакоммиченных критических изменений
- [ ] Сделать бэкап базы данных (опционально, но рекомендуется)

