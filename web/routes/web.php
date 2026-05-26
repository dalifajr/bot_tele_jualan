<?php

use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
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
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/api/check-telegram-id', [\App\Http\Controllers\ProfileController::class, 'checkTelegramId'])->name('api.check.telegram');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/password', [AuthController::class, 'login'])->name('login.post');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');

// Telegram Login Flow
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
    
    // Checkout
    Route::post('/checkout/{product}', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success/{order_ref}', [CheckoutController::class, 'success'])->name('checkout.success');
    
    // Manage Orders
    Route::post('/orders/{id}/cancel', [\App\Http\Controllers\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{id}/status', function ($id) {
        $order = \App\Models\Order::where('customer_id', \Illuminate\Support\Facades\Auth::id())->findOrFail($id);
        return response()->json(['status' => $order->status]);
    })->name('orders.status');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/edit', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [\App\Http\Controllers\Auth\AuthController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/telegram-link', [\App\Http\Controllers\ProfileController::class, 'generateTelegramLink'])->name('profile.telegram.link');
    Route::post('/profile/telegram-unlink', [\App\Http\Controllers\ProfileController::class, 'unlinkTelegram'])->name('profile.telegram.unlink');

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
        Route::post('/orders/{id}/accept', [\App\Http\Controllers\AdminController::class, 'acceptOrder'])->name('orders.accept');
        Route::post('/orders/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectOrder'])->name('orders.reject');
        
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
