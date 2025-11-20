#!/bin/bash

# Команды для создания пользователя с root доступом и очистки истории

echo "=== Создание пользователя с sudo правами ==="
echo ""

# 1. Создать нового пользователя
echo "1. Создание пользователя (замените 'username' на нужное имя):"
echo "sudo adduser username"
echo ""

# 2. Добавить пользователя в группу sudo
echo "2. Добавление пользователя в группу sudo:"
echo "sudo usermod -aG sudo username"
echo ""

# 3. Или использовать более простой способ (если пользователь уже существует)
echo "3. Альтернативный способ (если пользователь уже существует):"
echo "sudo usermod -aG sudo username"
echo ""

# 4. Проверить права
echo "4. Проверка прав пользователя:"
echo "groups username"
echo ""

echo "=== Очистка истории команд ==="
echo ""

# Очистка истории текущего пользователя
echo "1. Очистить историю текущего пользователя:"
echo "history -c"
echo "history -w"
echo ""

# Очистка файла истории
echo "2. Очистить файл истории:"
echo "> ~/.bash_history"
echo ""

# Очистка истории для root
echo "3. Очистить историю root (если нужно):"
echo "sudo history -c"
echo "sudo history -w"
echo "sudo > /root/.bash_history"
echo ""

# Полная очистка всех логов истории
echo "4. Полная очистка (все пользователи):"
echo "sudo find /home -name '.bash_history' -exec truncate -s 0 {} \;"
echo "sudo truncate -s 0 /root/.bash_history"
echo ""

echo "=== Готовые команды (скопируйте и выполните) ==="
echo ""
echo "# Создать пользователя с sudo правами:"
echo "sudo adduser newuser"
echo "sudo usermod -aG sudo newuser"
echo ""
echo "# Очистить историю:"
echo "history -c && history -w && > ~/.bash_history"
echo ""


