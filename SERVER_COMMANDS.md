# Команды для сервера

## 🔐 Создание пользователя с root доступом (sudo правами)

### Вариант 1: Создать нового пользователя
```bash
# Создать пользователя
sudo adduser username

# Добавить в группу sudo (дает root доступ)
sudo usermod -aG sudo username

# Проверить права
groups username
```

### Вариант 2: Если пользователь уже существует
```bash
# Просто добавить в группу sudo
sudo usermod -aG sudo username

# Проверить
groups username
```

### Вариант 3: Создать пользователя с домашней директорией и shell
```bash
# Создать пользователя с bash shell
sudo useradd -m -s /bin/bash username

# Установить пароль
sudo passwd username

# Добавить в sudo группу
sudo usermod -aG sudo username
```

### Проверка sudo прав:
```bash
# Переключиться на пользователя
su - username

# Проверить sudo доступ
sudo whoami
# Должно вывести: root
```

---

## 🗑️ Очистка истории команд

### Очистить историю текущего пользователя:
```bash
# Очистить текущую сессию
history -c

# Сохранить (очистить файл)
history -w

# Очистить файл истории
> ~/.bash_history
```

### Очистить историю root:
```bash
sudo history -c
sudo history -w
sudo > /root/.bash_history
```

### Полная очистка (все пользователи):
```bash
# Очистить историю всех пользователей
sudo find /home -name '.bash_history' -exec truncate -s 0 {} \;

# Очистить историю root
sudo truncate -s 0 /root/.bash_history

# Очистить текущую сессию
history -c && history -w
```

### Очистить историю и выйти из сессии:
```bash
history -c && history -w && > ~/.bash_history && exit
```

---

## 📝 Быстрые команды (скопируйте и выполните)

### Создать пользователя с sudo:
```bash
sudo adduser newuser && sudo usermod -aG sudo newuser
```

### Очистить историю:
```bash
history -c && history -w && > ~/.bash_history
```

### Очистить историю root:
```bash
sudo history -c && sudo history -w && sudo > /root/.bash_history
```

---

## ⚠️ Важно

- Замените `username` и `newuser` на реальное имя пользователя
- После добавления в группу sudo пользователю нужно выйти и войти заново, чтобы права применились
- Или выполните: `newgrp sudo` для применения прав без перелогина


