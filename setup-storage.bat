@echo off
REM Скрипт для создания необходимых директорий Laravel (Windows)
REM Использование: setup-storage.bat

echo Создание директорий для Laravel...

REM Создаем bootstrap/cache
if not exist "bootstrap\cache" mkdir bootstrap\cache
echo [OK] bootstrap/cache создана

REM Создаем storage директории
if not exist "storage\framework\cache\data" mkdir storage\framework\cache\data
if not exist "storage\framework\sessions" mkdir storage\framework\sessions
if not exist "storage\framework\views" mkdir storage\framework\views
if not exist "storage\app\public" mkdir storage\app\public
echo [OK] storage директории созданы

REM Создаем .gitkeep файлы
type nul > bootstrap\cache\.gitkeep
type nul > storage\framework\cache\data\.gitkeep
type nul > storage\framework\sessions\.gitkeep
type nul > storage\framework\views\.gitkeep
type nul > storage\app\public\.gitkeep

echo.
echo [OK] Все директории созданы!
echo.
pause

