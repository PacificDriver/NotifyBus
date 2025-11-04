#!/bin/bash

# Скрипт для создания необходимых директорий Laravel
# Использование: bash setup-storage.sh

echo "Создание директорий для Laravel..."

# Создаем bootstrap/cache
mkdir -p bootstrap/cache
echo "✓ bootstrap/cache создана"

# Создаем storage директории
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public
echo "✓ storage директории созданы"

# Создаем .gitkeep файлы для пустых директорий (если нужно)
touch bootstrap/cache/.gitkeep
touch storage/framework/cache/data/.gitkeep
touch storage/framework/sessions/.gitkeep
touch storage/framework/views/.gitkeep
touch storage/app/public/.gitkeep

echo ""
echo "✅ Все директории созданы!"
echo ""
echo "Теперь установите права доступа:"
echo "  chmod -R 775 storage bootstrap/cache"
echo "  chown -R www-data:www-data storage bootstrap/cache"

