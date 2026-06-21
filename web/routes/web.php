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

Route::post('/api/payment/midtrans/callback', [\App\Http\Controllers\MidtransController::class, 'callback'])->name('payment.midtrans.callback');

Route::get('/admin/broadcast/run-bg/{jobId}', [\App\Http\Controllers\AdminController::class, 'runBroadcastBackground'])->name('admin.broadcast.run-bg');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/api/check-telegram-id', [\App\Http\Controllers\ProfileController::class, 'checkTelegramId'])->name('api.check.telegram');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware(\App\Http\Middleware\TrackVisitor::class);
Route::post('/login/password', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:3,1');
Route::get('/suspended', [AuthController::class, 'suspended'])->name('suspended');
Route::get('/auth/two-factor', [AuthController::class, 'showTwoFactor'])->name('auth.two-factor');
Route::post('/auth/two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->name('auth.two-factor.verify');

// Language Switcher
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['id', 'en'])) {
        session()->put('locale', $locale);
    }
    return redirect()->back();
})->name('lang.switch');

// Telegram Login Flow
Route::post('/auth/telegram/request', [TelegramAuthController::class, 'requestLogin'])->name('auth.telegram.request')->middleware('throttle:5,1');
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])->name('auth.telegram.callback');
Route::post('/auth/telegram/webapp', [TelegramAuthController::class, 'webAppLogin'])->name('auth.telegram.webapp');
Route::post('/logout', [TelegramAuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (require Telegram authentication)
|--------------------------------------------------------------------------
|
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

    // Shopping Cart
    Route::get('/cart', [\App\Http\Controllers\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add/{product}', [\App\Http\Controllers\CartController::class, 'add'])->name('cart.add');
    Route::put('/cart/update/{id}', [\App\Http\Controllers\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/remove/{id}', [\App\Http\Controllers\CartController::class, 'remove'])->name('cart.remove');
    Route::get('/cart/checkout', [\App\Http\Controllers\CartController::class, 'checkout'])->name('cart.checkout');
    Route::post('/cart/process', [\App\Http\Controllers\CartController::class, 'processCheckout'])->name('cart.process');

    // Reviews & Ratings
    Route::post('/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');

    // Chat System
    Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/messages/{contactId}', [\App\Http\Controllers\ChatController::class, 'fetchMessages'])->name('chat.fetch');
    Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.send');
    
    // Manage Orders
    Route::post('/orders/{id}/cancel', [\App\Http\Controllers\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{id}/complaint', [\App\Http\Controllers\OrderController::class, 'submitComplaint'])->name('orders.complaint');
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
    Route::post('/profile/2fa/toggle', [\App\Http\Controllers\Auth\AuthController::class, 'toggleTwoFactor'])->name('profile.2fa.toggle');

    Route::post('/admin/stop-impersonating', [\App\Http\Controllers\AdminController::class, 'stopImpersonating'])->name('admin.users.stop-impersonating');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    |
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminController::class, 'dashboard'])->name('dashboard');
        
        Route::get('/products', [\App\Http\Controllers\AdminController::class, 'products'])->name('products.index');
        Route::post('/products', [\App\Http\Controllers\AdminController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{id}', [\App\Http\Controllers\AdminController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{id}', [\App\Http\Controllers\AdminController::class, 'destroyProduct'])->name('products.destroy');
        Route::get('/products/{id}/manage', [\App\Http\Controllers\AdminController::class, 'manageProduct'])->name('products.manage');
        
        Route::get('/stock', [\App\Http\Controllers\AdminController::class, 'stock'])->name('stock.index');
        Route::match(['get', 'post'], '/stock/export', [\App\Http\Controllers\AdminController::class, 'exportStock'])->name('stock.export');
        Route::post('/stock', [\App\Http\Controllers\AdminController::class, 'storeStock'])->name('stock.store');
        Route::post('/stock/bulk-move', [\App\Http\Controllers\AdminController::class, 'bulkMoveStock'])->name('stock.bulkMove');
        Route::post('/stock/bulk-delete', [\App\Http\Controllers\AdminController::class, 'bulkDestroyStock'])->name('stock.bulkDestroy');
        Route::put('/stock/{id}/move', [\App\Http\Controllers\AdminController::class, 'moveStock'])->name('stock.move');
        Route::delete('/stock/{id}', [\App\Http\Controllers\AdminController::class, 'destroyStock'])->name('stock.destroy');
        
        Route::get('/orders', [\App\Http\Controllers\AdminController::class, 'orders'])->name('orders.index');
        Route::put('/orders/{id}', [\App\Http\Controllers\AdminController::class, 'updateOrder'])->name('orders.update');
        Route::post('/orders/{id}/accept', [\App\Http\Controllers\AdminController::class, 'acceptOrder'])->name('orders.accept');
        Route::post('/orders/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectOrder'])->name('orders.reject');
        Route::post('/orders/{id}/replace-stock/{stockUnitId}', [\App\Http\Controllers\AdminController::class, 'replaceStock'])->name('orders.replace-stock');
        Route::post('/orders/{id}/replace-stock-bulk', [\App\Http\Controllers\AdminController::class, 'replaceStockBulk'])->name('orders.replace-stock-bulk');
        Route::post('/orders/{id}/refund', [\App\Http\Controllers\AdminController::class, 'refundOrder'])->name('orders.refund');
        Route::post('/orders/{id}/refund-bulk', [\App\Http\Controllers\AdminController::class, 'refundBulk'])->name('orders.refund-bulk');
        
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users'])->name('users.index');
        Route::get('/sellers', [\App\Http\Controllers\AdminController::class, 'sellers'])->name('sellers.index');
        Route::match(['get', 'post'], '/users/export', [\App\Http\Controllers\AdminController::class, 'exportUsers'])->name('users.export');
        Route::put('/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}', [\App\Http\Controllers\AdminController::class, 'deleteUser'])->name('users.destroy');
        Route::post('/users/{id}/suspend', [\App\Http\Controllers\AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::post('/users/{id}/unsuspend', [\App\Http\Controllers\AdminController::class, 'unsuspendUser'])->name('users.unsuspend');
        Route::post('/users/{id}/impersonate', [\App\Http\Controllers\AdminController::class, 'impersonate'])->name('users.impersonate');

        // Logins & Notifications
        Route::get('/logins', [\App\Http\Controllers\AdminController::class, 'logins'])->name('logins.index');
        Route::get('/notifications', [\App\Http\Controllers\AdminController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/mark-read', [\App\Http\Controllers\AdminController::class, 'markNotificationsRead'])->name('notifications.markRead');
        
        // New features ported from Bot
        Route::get('/complaints', [\App\Http\Controllers\AdminController::class, 'complaints'])->name('complaints.index');
        Route::get('/complaints/{id}', [\App\Http\Controllers\AdminController::class, 'showComplaint'])->name('complaints.show');
        Route::post('/complaints/{id}/status', [\App\Http\Controllers\AdminController::class, 'updateComplaintStatus'])->name('complaints.updateStatus');
        
        Route::get('/broadcast', [\App\Http\Controllers\AdminController::class, 'broadcast'])->name('broadcast.index');
        Route::post('/broadcast/start', [\App\Http\Controllers\AdminController::class, 'startBroadcast'])->name('broadcast.start');
        Route::get('/broadcast/status/{jobId}', [\App\Http\Controllers\AdminController::class, 'getBroadcastStatus'])->name('broadcast.status');
        Route::get('/broadcast/active', [\App\Http\Controllers\AdminController::class, 'getActiveBroadcast'])->name('broadcast.active');
        Route::post('/broadcast/mark-read/{jobId}', [\App\Http\Controllers\AdminController::class, 'markBroadcastRead'])->name('broadcast.mark-read');
        Route::post('/broadcast/cancel/{jobId}', [\App\Http\Controllers\AdminController::class, 'cancelBroadcast'])->name('broadcast.cancel');
        
        Route::get('/settings', [\App\Http\Controllers\AdminController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/qris', [\App\Http\Controllers\AdminController::class, 'uploadQris'])->name('settings.qris.upload');
        Route::delete('/settings/qris', [\App\Http\Controllers\AdminController::class, 'deleteQris'])->name('settings.qris.delete');
        Route::get('/settings/qris/image', [\App\Http\Controllers\AdminController::class, 'showQrisImage'])->name('settings.qris.image');
        Route::post('/settings/run-held-funds', [\App\Http\Controllers\AdminController::class, 'runHeldFunds'])->name('settings.run-held-funds');
        Route::post('/settings/run-release-expired', [\App\Http\Controllers\AdminController::class, 'runReleaseExpired'])->name('settings.run-release-expired');
        Route::get('/audit-logs', [\App\Http\Controllers\AdminController::class, 'auditLogs'])->name('audit-logs.index');
        
        // Coupons Management
        Route::get('/coupons', [\App\Http\Controllers\Admin\CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [\App\Http\Controllers\Admin\CouponController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{id}', [\App\Http\Controllers\Admin\CouponController::class, 'update'])->name('coupons.update');
        Route::delete('/coupons/{id}', [\App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('coupons.destroy');
        
        // Backup & Restore
        Route::get('/backup', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/restore', [\App\Http\Controllers\Admin\BackupController::class, 'showRestore'])->name('backup.restore.show');
        Route::post('/backup/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backup.restore');
        Route::get('/backup/restore/progress', [\App\Http\Controllers\Admin\BackupController::class, 'restoreProgress'])->name('backup.restore.progress');
        Route::get('/backup/restore/run', [\App\Http\Controllers\Admin\BackupController::class, 'runRestore'])->name('backup.restore.run');
        Route::get('/backup/restore/status', [\App\Http\Controllers\Admin\BackupController::class, 'restoreStatus'])->name('backup.restore.status');
        Route::get('/backup/settings', [\App\Http\Controllers\Admin\BackupController::class, 'showSettings'])->name('backup.settings.show');
        Route::post('/backup/settings', [\App\Http\Controllers\Admin\BackupController::class, 'updateSettings'])->name('backup.settings.update');
        Route::get('/backup/history', [\App\Http\Controllers\Admin\BackupController::class, 'history'])->name('backup.history');
        Route::get('/backup/download/{type}', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backup.download');
        Route::delete('/backup/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('backup.destroy');
        
        // Wipe Database Action
        Route::get('/backup/wipe/progress', [\App\Http\Controllers\Admin\BackupController::class, 'wipeProgress'])->name('backup.wipe.progress');
        Route::get('/backup/wipe/run', [\App\Http\Controllers\Admin\BackupController::class, 'runWipe'])->name('backup.wipe.run');
        Route::get('/backup/wipe/status', [\App\Http\Controllers\Admin\BackupController::class, 'wipeStatus'])->name('backup.wipe.status');
        
        // System Actions
        Route::get('/website/settings', [\App\Http\Controllers\AdminController::class, 'websiteSettings'])->name('website.settings');

        // Admin Payouts / Withdrawals
        Route::get('/withdrawals', [\App\Http\Controllers\AdminController::class, 'withdrawals'])->name('withdrawals.index');
        Route::post('/withdrawals/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveWithdrawal'])->name('withdrawals.approve');
        Route::post('/withdrawals/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectWithdrawal'])->name('withdrawals.reject');

        // Admin Product Workers management
        Route::post('/products/{id}/workers', [\App\Http\Controllers\AdminController::class, 'addWorker'])->name('products.workers.store');
        Route::delete('/products/{id}/workers/{userId}', [\App\Http\Controllers\AdminController::class, 'removeWorker'])->name('products.workers.destroy');

    });

    // Tools (accessible by admin and authorized sellers)
    Route::prefix('admin/tools')->name('admin.tools.')->group(function () {
        Route::middleware('tool.access:github_checker')->group(function () {
            Route::get('/github-checker', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'index'])->name('github-checker');
            Route::get('/github-checker/batch/{batchId}', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'showBatch'])->name('github-checker.batch');
            Route::post('/github-checker/set-cookie', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'setCookie'])->name('github-checker.set-cookie');
            Route::post('/github-checker/clear-cookie', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'clearCookie'])->name('github-checker.clear-cookie');
            Route::post('/github-checker/start', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'start'])->name('github-checker.start');
            Route::post('/github-checker/check-next/{batchId}', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'checkNext'])->name('github-checker.check-next');
            Route::post('/github-checker/stop/{batchId}', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'stopBatch'])->name('github-checker.stop');
            Route::get('/github-checker/progress/{batchId}', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'progress'])->name('github-checker.progress');
            Route::get('/github-checker/export/{batchId}', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'export'])->name('github-checker.export');
            Route::post('/github-checker/load-stock', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'loadStockUsernames'])->name('github-checker.load-stock');
            Route::post('/github-checker/bulk-delete-stock', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'bulkDeleteStock'])->name('github-checker.bulk-delete-stock');
            Route::post('/github-checker/bulk-update-stock', [\App\Http\Controllers\Admin\GithubCheckerController::class, 'bulkUpdateStockStatus'])->name('github-checker.bulk-update-stock');
        });

        Route::middleware('tool.access:gmail_checker')->group(function () {
            Route::get('/gmail-checker', [\App\Http\Controllers\Admin\GmailCheckerController::class, 'index'])->name('gmail-checker');
            Route::post('/gmail-checker/load-stock', [\App\Http\Controllers\Admin\GmailCheckerController::class, 'loadStock'])->name('gmail-checker.load-stock');
            Route::post('/gmail-checker/bulk-action', [\App\Http\Controllers\Admin\GmailCheckerController::class, 'bulkAction'])->name('gmail-checker.bulk-action');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('seller')->prefix('seller')->name('seller.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SellerController::class, 'dashboard'])->name('dashboard');
        
        Route::get('/stock', [\App\Http\Controllers\SellerController::class, 'stock'])->name('stock.index');
        Route::post('/stock', [\App\Http\Controllers\SellerController::class, 'storeStock'])->name('stock.store');
        Route::post('/stock/bulk-move', [\App\Http\Controllers\SellerController::class, 'bulkMoveStock'])->name('stock.bulkMove');
        Route::post('/stock/bulk-delete', [\App\Http\Controllers\SellerController::class, 'bulkDestroyStock'])->name('stock.bulkDestroy');
        Route::put('/stock/{id}/move', [\App\Http\Controllers\SellerController::class, 'moveStock'])->name('stock.move');
        Route::delete('/stock/{id}', [\App\Http\Controllers\SellerController::class, 'destroyStock'])->name('stock.destroy');

        Route::get('/products', [\App\Http\Controllers\SellerController::class, 'products'])->name('products.index');
        Route::post('/products', [\App\Http\Controllers\SellerController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{id}', [\App\Http\Controllers\SellerController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{id}', [\App\Http\Controllers\SellerController::class, 'destroyProduct'])->name('products.destroy');
        Route::post('/products/{id}/workers', [\App\Http\Controllers\SellerController::class, 'addWorker'])->name('products.workers.store');
        Route::delete('/products/{id}/workers/{userId}', [\App\Http\Controllers\SellerController::class, 'removeWorker'])->name('products.workers.destroy');
        
        Route::get('/finance', [\App\Http\Controllers\SellerController::class, 'finance'])->name('finance.index');
        Route::post('/finance/withdraw', [\App\Http\Controllers\SellerController::class, 'requestWithdrawal'])->name('finance.withdraw');
        
        Route::get('/bank-accounts', [\App\Http\Controllers\SellerController::class, 'bankAccounts'])->name('bank-accounts.index');
        Route::post('/bank-accounts', [\App\Http\Controllers\SellerController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::delete('/bank-accounts/{id}', [\App\Http\Controllers\SellerController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');

        Route::get('/orders', [\App\Http\Controllers\SellerController::class, 'orders'])->name('orders.index');
        Route::post('/orders/{id}/cancel', [\App\Http\Controllers\SellerController::class, 'cancelOrder'])->name('orders.cancel');

        Route::get('/complaints', [\App\Http\Controllers\SellerController::class, 'complaints'])->name('complaints.index');
        Route::get('/complaints/{id}', [\App\Http\Controllers\SellerController::class, 'showComplaint'])->name('complaints.show');
        Route::post('/complaints/{id}/status', [\App\Http\Controllers\SellerController::class, 'updateComplaintStatus'])->name('complaints.updateStatus');

        Route::get('/settings', [\App\Http\Controllers\SellerController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SellerController::class, 'updateSettings'])->name('settings.update');
    });
});

