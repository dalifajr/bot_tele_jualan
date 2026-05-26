<?php

use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\EnsureTelegramAuthenticated;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Auth Routes (Telegram Login Flow)
|--------------------------------------------------------------------------
*/
Route::get('/login', [TelegramAuthController::class, 'showLogin'])->name('login');
Route::post('/auth/telegram/request', [TelegramAuthController::class, 'requestLogin'])->name('auth.telegram.request');
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])->name('auth.telegram.callback');
Route::post('/logout', [TelegramAuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (require Telegram authentication)
|--------------------------------------------------------------------------
*/
Route::middleware(EnsureTelegramAuthenticated::class)->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Catalog
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{id}', [CatalogController::class, 'show'])->name('catalog.show');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');

    // Profile
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminController::class, 'dashboard'])->name('dashboard');
        
        Route::get('/products', [\App\Http\Controllers\AdminController::class, 'products'])->name('products.index');
        Route::post('/products', [\App\Http\Controllers\AdminController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{id}', [\App\Http\Controllers\AdminController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{id}', [\App\Http\Controllers\AdminController::class, 'destroyProduct'])->name('products.destroy');
        Route::get('/products/{id}/manage', [\App\Http\Controllers\AdminController::class, 'manageProduct'])->name('products.manage');
        
        Route::get('/stock', [\App\Http\Controllers\AdminController::class, 'stock'])->name('stock.index');
        Route::post('/stock', [\App\Http\Controllers\AdminController::class, 'storeStock'])->name('stock.store');
        Route::put('/stock/{id}/move', [\App\Http\Controllers\AdminController::class, 'moveStock'])->name('stock.move');
        Route::delete('/stock/{id}', [\App\Http\Controllers\AdminController::class, 'destroyStock'])->name('stock.destroy');
        
        Route::get('/orders', [\App\Http\Controllers\AdminController::class, 'orders'])->name('orders.index');
        Route::put('/orders/{id}', [\App\Http\Controllers\AdminController::class, 'updateOrder'])->name('orders.update');
        
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users'])->name('users.index');
        Route::put('/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUserRole'])->name('users.update');

        // Logins Notification Page
        Route::get('/logins', [\App\Http\Controllers\AdminController::class, 'logins'])->name('logins.index');
        Route::post('/notifications/mark-read', [\App\Http\Controllers\AdminController::class, 'markNotificationsRead'])->name('notifications.markRead');
        
        // New features ported from Bot
        Route::get('/complaints', [\App\Http\Controllers\AdminController::class, 'complaints'])->name('complaints.index');
        
        Route::get('/broadcast', [\App\Http\Controllers\AdminController::class, 'broadcast'])->name('broadcast.index');
        Route::post('/broadcast/prepare', [\App\Http\Controllers\AdminController::class, 'prepareBroadcast'])->name('broadcast.prepare');
        Route::post('/broadcast/send', [\App\Http\Controllers\AdminController::class, 'sendBroadcast'])->name('broadcast.send');
        
        Route::get('/settings', [\App\Http\Controllers\AdminController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\AdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('/reports', [\App\Http\Controllers\AdminController::class, 'reports'])->name('reports.index');
        
        // System Actions
        Route::get('/website/settings', [\App\Http\Controllers\AdminController::class, 'websiteSettings'])->name('website.settings');
    });
});
