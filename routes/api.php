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

Route::middleware(['auth:sanctum'])->group(function () {
    
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
        Route::get('/cancelled', 'App\Http\Controllers\Api\TripController@getCancelled');
        Route::get('/{id}', 'App\Http\Controllers\Api\TripController@show');
    });

    // Пассажиры
    Route::prefix('passengers')->group(function () {
        Route::get('/by-trip/{tripId}', 'App\Http\Controllers\Api\PassengerController@getByTrip');
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
        Route::post('/{id}/send', 'App\Http\Controllers\Api\NotificationTaskController@send');
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
        Route::post('/', 'App\Http\Controllers\Api\SettingsController@store');
        Route::post('/test/whatsapp', 'App\Http\Controllers\Api\SettingsController@testWhatsApp');
        Route::post('/test/email', 'App\Http\Controllers\Api\SettingsController@testEmail');
        Route::post('/test/carrier-api', 'App\Http\Controllers\Api\SettingsController@testCarrierApi');
    });
});

