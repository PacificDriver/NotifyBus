# 🔐 Работа с Git репозиторием

## Репозиторий: https://github.com/PacificDriver/NotifyBus

## 📥 Клонирование репозитория (если проект еще не клонирован)

### Публичный репозиторий (без авторизации)

```bash
cd /var/www/html
git clone https://github.com/PacificDriver/NotifyBus.git
cd NotifyBus
```

### Приватный репозиторий (требует авторизации)

#### Вариант 1: HTTPS (рекомендуется)

```bash
# Клонирование с запросом логина/пароля
cd /var/www/html
git clone https://github.com/PacificDriver/NotifyBus.git
# Введите ваш GitHub username и Personal Access Token (не пароль!)

# Или используйте SSH
git clone git@github.com:PacificDriver/NotifyBus.git
```

#### Вариант 2: SSH (если настроен SSH ключ)

```bash
# Сначала добавьте SSH ключ в GitHub:
# 1. Сгенерируйте ключ (если нет):
ssh-keygen -t ed25519 -C "your_email@example.com"

# 2. Скопируйте публичный ключ:
cat ~/.ssh/id_ed25519.pub

# 3. Добавьте в GitHub: Settings → SSH and GPG keys → New SSH key

# Затем клонируйте:
cd /var/www/html
git clone git@github.com:PacificDriver/NotifyBus.git
```

## 🔑 Получение Personal Access Token (для HTTPS)

Если репозиторий приватный, нужен токен:

1. Зайдите в GitHub: https://github.com/settings/tokens
2. Нажмите "Generate new token (classic)"
3. Выберите права:
   - `repo` (полный доступ к репозиториям)
   - `workflow` (если используете GitHub Actions)
4. Скопируйте токен (показывается только один раз!)
5. Используйте токен вместо пароля при клонировании/пуше

## 📤 Обновление кода на сервере

### Если код уже клонирован

```bash
cd /var/www/html/NotifyBus

# Получить последние изменения
git pull origin main

# Или для конкретной ветки
git pull origin master
```

### Если нужно обновить удаленный URL

```bash
cd /var/www/html/NotifyBus

# Проверить текущий URL
git remote -v

# Изменить URL (если нужно)
git remote set-url origin https://github.com/PacificDriver/NotifyBus.git

# Или для SSH
git remote set-url origin git@github.com:PacificDriver/NotifyBus.git
```

## 🔄 Рабочий процесс обновления

### 1. Получить изменения с GitHub

```bash
cd /var/www/html/NotifyBus
git fetch origin
git pull origin main
```

### 2. Применить изменения

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
```

### 3. Перезапустить сервисы

```bash
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

## 🔐 Настройка авторизации для Git

### Если репозиторий приватный

#### Способ 1: Использовать Personal Access Token

```bash
# При клонировании или push/pull будет запрошен токен
# Используйте токен вместо пароля

# Или сохраните в Git credential helper:
git config --global credential.helper store
# При следующем вводе токен сохранится
```

#### Способ 2: Настроить SSH ключ

```bash
# 1. Сгенерировать ключ (если нет)
ssh-keygen -t ed25519 -C "your_email@example.com"

# 2. Показать публичный ключ
cat ~/.ssh/id_ed25519.pub

# 3. Добавить в GitHub: Settings → SSH and GPG keys

# 4. Изменить URL репозитория на SSH
git remote set-url origin git@github.com:PacificDriver/NotifyBus.git

# 5. Проверить подключение
ssh -T git@github.com
```

## 📝 Проверка статуса репозитория

```bash
cd /var/www/html/NotifyBus

# Проверить статус
git status

# Проверить удаленный репозиторий
git remote -v

# Посмотреть последние коммиты
git log --oneline -10

# Проверить, есть ли новые изменения
git fetch origin
git status
```

## ⚠️ Решение проблем

### Ошибка: "Permission denied (publickey)"

Репозиторий приватный, нужен SSH ключ или токен:

```bash
# Вариант 1: Использовать HTTPS с токеном
git remote set-url origin https://github.com/PacificDriver/NotifyBus.git

# Вариант 2: Настроить SSH ключ (см. выше)
```

### Ошибка: "Authentication failed"

```bash
# Очистите сохраненные учетные данные
git config --global --unset credential.helper
git credential-cache exit

# Или удалите сохраненные данные
rm ~/.git-credentials
```

### Ошибка: "fatal: not a git repository"

```bash
# Инициализируйте репозиторий
git init
git remote add origin https://github.com/PacificDriver/NotifyBus.git
git fetch origin
git checkout main  # или master
```

### Конфликты при pull

```bash
# Если есть локальные изменения
git stash
git pull origin main
git stash pop

# Или отменить локальные изменения (ОСТОРОЖНО!)
git reset --hard origin/main
```

## 🚀 Быстрая команда для обновления

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

## 📋 Проверочный список

- [ ] Репозиторий склонирован или настроен
- [ ] Удаленный URL правильный: `git remote -v`
- [ ] Есть доступ к репозиторию (публичный или настроена авторизация)
- [ ] Можете выполнить `git pull` без ошибок
- [ ] После обновления сайт работает корректно








