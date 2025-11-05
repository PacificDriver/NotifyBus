<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhook для Wappi.pro (с проверкой токена через middleware)
Route::post('/webhooks/wappi', 'App\Http\Controllers\Api\WebhookController@handle')
    ->middleware('verify.wappi');

Route::middleware(['auth:web'])->group(function () {
    
    // Получение информации о текущем пользователе
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Станции
    Route::prefix('stations')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\StationController@index');
        Route::post('/sync', 'App\Http\Controllers\Api\StationController@sync')->middleware('role:admin');
    });

    // Рейсы
    Route::prefix('trips')->group(function () {
        Route::get('/cancelled', 'App\Http\Controllers\Api\TripController@getCancelled'); // GET /api/trips/cancelled?from={id}&to={id}&date={Y-m-d}
        Route::get('/{id}', 'App\Http\Controllers\Api\TripController@show');
    });
    
    // Рейсы из API перевозчика (альтернативный endpoint)
    Route::prefix('races')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\TripController@getCancelled'); // GET /api/races?from={id}&to={id}&date={Y-m-d}
    });

    // Пассажиры
    Route::prefix('passengers')->group(function () {
        Route::get('/by-trip/{tripId}', 'App\Http\Controllers\Api\PassengerController@getByTrip');
        Route::post('/load-by-races', 'App\Http\Controllers\Api\PassengerController@loadByRaces');
    });

    // Уведомления
    Route::prefix('notifications')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\NotificationController@index');
        Route::post('/', 'App\Http\Controllers\Api\NotificationController@store');
        Route::get('/{id}', 'App\Http\Controllers\Api\NotificationController@show');
        Route::get('/{id}/status', 'App\Http\Controllers\Api\NotificationController@getStatus');
    });

    // Задачи на рассылку
    Route::prefix('notification-tasks')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\NotificationTaskController@index');
        Route::post('/', 'App\Http\Controllers\Api\NotificationTaskController@store');
        Route::get('/{id}', 'App\Http\Controllers\Api\NotificationTaskController@show');
        Route::put('/{id}/add-races', 'App\Http\Controllers\Api\NotificationTaskController@addRaces');
        Route::post('/{id}/load-passengers', 'App\Http\Controllers\Api\NotificationTaskController@loadPassengers');
        Route::get('/{id}/passengers', 'App\Http\Controllers\Api\NotificationTaskController@getPassengers');
        Route::post('/{id}/send', 'App\Http\Controllers\Api\NotificationTaskController@send');
        Route::get('/{id}/status', 'App\Http\Controllers\Api\NotificationTaskController@getStatus');
    });

    // Шаблоны сообщений
    Route::prefix('templates')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\TemplateController@index');
        Route::post('/', 'App\Http\Controllers\Api\TemplateController@store');
        Route::put('/{id}', 'App\Http\Controllers\Api\TemplateController@update');
        Route::delete('/{id}', 'App\Http\Controllers\Api\TemplateController@destroy');
    });

    // Настройки (только для администратора)
    Route::prefix('settings')->middleware('role:admin')->group(function () {
        Route::get('/', 'App\Http\Controllers\Api\SettingsController@index');
        Route::get('/status', 'App\Http\Controllers\Api\SettingsController@status');
        Route::post('/', 'App\Http\Controllers\Api\SettingsController@store');
        Route::post('/test/whatsapp', 'App\Http\Controllers\Api\SettingsController@testWhatsApp');
        Route::post('/test/email', 'App\Http\Controllers\Api\SettingsController@testEmail');
        Route::post('/test/carrier-api', 'App\Http\Controllers\Api\SettingsController@testCarrierApi');
    });
});

