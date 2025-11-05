# 🔧 Решение проблем при установке

## Проблема 1: Permission denied для логов

**Ошибка:**
```
The stream or file "/var/www/html/NotifyBus/storage/logs/laravel.log" 
could not be opened in append mode: Permission denied
```

### Быстрое решение:

```bash
cd /var/www/html/NotifyBus
sudo bash fix-storage-permissions.sh
```

Или используйте старый скрипт:

```bash
cd /var/www/html/NotifyBus
sudo bash fix-logs-permissions.sh
```

### Или вручную:

```bash
cd /var/www/html/NotifyBus

# Создать директорию и файл
sudo mkdir -p storage/logs
sudo touch storage/logs/laravel.log

# Установить права
sudo chown -R www-data:www-data storage/logs
sudo chmod -R 775 storage/logs
sudo chmod 664 storage/logs/laravel.log
```

### Полное исправление всех прав:

```bash
cd /var/www/html/NotifyBus
sudo bash fix-permissions.sh
```

---

## Проблема 2: Пользователь уже существует

**Ошибка:**
```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates 
unique constraint "users_email_unique"
DETAIL: Key (email)=(admin@busnotifications.ru) already exists.
```

### Причина:
Вы пытаетесь запустить `php artisan db:seed`, но пользователь с таким email уже существует в базе данных.

### Решение 1: Использовать обновленный seeder (рекомендуется)

Seeder уже обновлен и использует `updateOrCreate`, поэтому можно просто запустить:

```bash
php artisan db:seed
```

Он обновит существующих пользователей или создаст новых, если их нет.

### Решение 2: Очистить базу и пересоздать

**⚠️ ВНИМАНИЕ: Это удалит все данные!**

```bash
# Очистить базу данных
php artisan migrate:fresh

# Заполнить тестовыми данными
php artisan db:seed
```

### Решение 3: Удалить конкретного пользователя

```bash
php artisan tinker
```

Затем в консоли:

```php
// Удалить пользователя
$user = \App\Models\User::where('email', 'admin@busnotifications.ru')->first();
if ($user) {
    $user->delete();
    echo "Пользователь удален\n";
} else {
    echo "Пользователь не найден\n";
}

// Выйти
exit
```

Затем запустите seeder снова:

```bash
php artisan db:seed
```

### Решение 4: Создать пользователя вручную

```bash
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Проверить, существует ли пользователь
$user = User::where('email', 'admin@busnotifications.ru')->first();

if ($user) {
    echo "Пользователь уже существует:\n";
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Role: {$user->role}\n";
} else {
    // Создать нового
    $admin = User::create([
        'name' => 'Администратор',
        'email' => 'admin@busnotifications.ru',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
    echo "Пользователь создан\n";
}

exit
```

---

## Проблема 3: Комбинация обеих проблем

Если у вас обе проблемы одновременно:

```bash
cd /var/www/html/NotifyBus

# 1. Исправить права доступа
sudo bash fix-logs-permissions.sh

# 2. Проверить существующих пользователей
php artisan tinker
```

В консоли tinker:

```php
\App\Models\User::all(['id', 'name', 'email', 'role']);
exit
```

Если пользователи уже есть, просто используйте их. Если нужно обновить пароли:

```bash
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'admin@busnotifications.ru')->first();
if ($admin) {
    $admin->password = Hash::make('password');
    $admin->save();
    echo "Пароль обновлен\n";
}

$operator = User::where('email', 'operator@busnotifications.ru')->first();
if ($operator) {
    $operator->password = Hash::make('password');
    $operator->save();
    echo "Пароль обновлен\n";
}

exit
```

---

## Проверка после исправления

```bash
# Проверить права
ls -la storage/logs/laravel.log

# Проверить пользователей
php artisan tinker
```

```php
\App\Models\User::count();
\App\Models\User::all(['name', 'email', 'role']);
exit
```

---

## Быстрая команда для исправления всего

```bash
cd /var/www/html/NotifyBus && \
sudo bash fix-permissions.sh && \
php artisan config:clear && \
php artisan cache:clear
```

---

## 📋 Чеклист

- [ ] Права доступа к `storage/logs` исправлены
- [ ] Файл `laravel.log` создан и доступен для записи
- [ ] Пользователи в базе данных (проверено через tinker)
- [ ] Seeder использует `updateOrCreate` (уже обновлен)
- [ ] Можно запускать команды Laravel без ошибок

