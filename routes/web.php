<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Главная страница - страница входа
Route::get('/', [AuthController::class, 'showLoginForm']);
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Панель оператора (требуется аутентификация)
Route::middleware(['auth'])->prefix('dashboard')->group(function () {
    Route::get('/', function () {
        return view('dashboard.index');
    })->name('dashboard');
    
    // Управление водителями для операторов
    Route::get('/drivers', function () {
        return view('dashboard.drivers');
    })->name('dashboard.drivers');
    
    Route::get('/drivers/{id}/trips', function ($id) {
        $driver = \App\Models\Driver::findOrFail($id);
        return view('dashboard.driver-trips', ['driver' => $driver]);
    })->name('dashboard.driver.trips');
    
    // Список всех рейсов
    Route::get('/trips-list', function () {
        return view('dashboard.trips-list');
    })->name('dashboard.trips-list');
    
    // Детали рейса (ведомость)
    Route::get('/trips/{externalId}', function ($externalId) {
        return view('dashboard.trip-details', ['externalId' => $externalId]);
    })->name('dashboard.trip-details');
});

// Панель администратора
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('admin.index');
    })->name('admin.dashboard');
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');
    
    Route::get('/drivers', function () {
        return view('admin.drivers');
    })->name('admin.drivers');
    
    Route::get('/drivers/{id}/trips', function ($id) {
        $driver = \App\Models\Driver::findOrFail($id);
        return view('admin.driver-trips', ['driver' => $driver]);
    })->name('admin.driver.trips');
});


