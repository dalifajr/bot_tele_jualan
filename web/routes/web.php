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
    return redirect()->route('dashboard');
});

// Public Showcase & Catalog Routes (Accessible to Guest & Authenticated Users)
Route::middleware(\App\Http\Middleware\TrackVisitor::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{id}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::get('/sellers/{user}', [\App\Http\Controllers\SellerProfileController::class, 'show'])->name('sellers.show');
});
Route::get('/catalog/{id}/vcc-info', [CatalogController::class, 'vccInfo'])->name('catalog.vcc-info');
Route::get('/catalog/{id}/check-vpn-username', [CatalogController::class, 'checkVpnUsername'])->name('catalog.check-vpn-username')->middleware('throttle:30,1');

Route::post('/api/payment/midtrans/callback', [\App\Http\Controllers\MidtransController::class, 'callback'])->name('payment.midtrans.callback');

// Android Listener Endpoints (Deteksi Uang Masuk Mutasi Otomatis via QRIS / Bank)
Route::post('/listener/payment', [\App\Http\Controllers\PaymentListenerController::class, 'handlePayment'])->name('listener.payment');
Route::post('/listener/test-connection', [\App\Http\Controllers\PaymentListenerController::class, 'testConnection'])->name('listener.test-connection');

Route::get('/admin/broadcast/run-bg/{jobId}', [\App\Http\Controllers\AdminController::class, 'runBroadcastBackground'])->name('admin.broadcast.run-bg');
Route::get('/bc/t/{jobId}/{userId}/{sig}', [\App\Http\Controllers\AdminController::class, 'trackBroadcastClick'])->name('broadcast.track-click');

// Public Downloads untuk Remote Control Binaries (Failsafe Handler)
Route::get('/downloads/{filename}', function ($filename) {
    $allowed = ['remote_agent.jar', 'remote_controller.apk', 'run_agent.sh', 'jmuxer.min.js'];
    if (!in_array($filename, $allowed)) {
        abort(404);
    }

    $candidates = [
        public_path("downloads/{$filename}"),
        base_path("../remote_control/build_artifacts/{$filename}"),
        base_path("public/downloads/{$filename}"),
        base_path("storage/app/public/downloads/{$filename}")
    ];

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $mime = 'application/octet-stream';
            if (str_ends_with($filename, '.apk')) $mime = 'application/vnd.android.package-archive';
            elseif (str_ends_with($filename, '.jar')) $mime = 'application/java-archive';
            elseif (str_ends_with($filename, '.sh')) $mime = 'text/x-shellscript';
            elseif (str_ends_with($filename, '.js')) $mime = 'application/javascript';

            return response()->file($path, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate'
            ]);
        }
    }

    abort(404, "File {$filename} belum tersedia di server.");
})->name('public.downloads');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/api/check-telegram-id', [\App\Http\Controllers\ProfileController::class, 'checkTelegramId'])->name('api.check.telegram')->middleware('throttle:10,1');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware(\App\Http\Middleware\TrackVisitor::class);
Route::get('/register', [AuthController::class, 'showLogin'])->name('register')->middleware(\App\Http\Middleware\TrackVisitor::class);
Route::post('/login/password', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:3,1');
Route::get('/suspended', [AuthController::class, 'suspended'])->name('suspended');
Route::get('/auth/two-factor', [AuthController::class, 'showTwoFactor'])->name('auth.two-factor');
Route::post('/auth/two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->name('auth.two-factor.verify')->middleware('throttle:10,1');

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

// Public Escrow Room (Accessible by external seller/buyer via token link)
Route::get('/rekber/room/{token}', [\App\Http\Controllers\EscrowController::class, 'showPublic'])->name('escrow.public.show');
Route::get('/rekber/room/{token}/messages', [\App\Http\Controllers\EscrowController::class, 'getMessagesPublic'])->name('escrow.public.messages');
Route::post('/rekber/room/{token}/deposit', [\App\Http\Controllers\EscrowController::class, 'uploadDepositPublic'])->name('escrow.public.deposit');
Route::post('/rekber/room/{token}/deliver', [\App\Http\Controllers\EscrowController::class, 'uploadDeliveryPublic'])->name('escrow.public.deliver');
Route::post('/rekber/room/{token}/confirm', [\App\Http\Controllers\EscrowController::class, 'confirmCompletePublic'])->name('escrow.public.confirm');
Route::post('/rekber/room/{token}/dispute', [\App\Http\Controllers\EscrowController::class, 'openDisputePublic'])->name('escrow.public.dispute');
Route::post('/rekber/room/{token}/message', [\App\Http\Controllers\EscrowController::class, 'sendMessagePublic'])->name('escrow.public.message');

/*
|--------------------------------------------------------------------------
| Protected Routes (require Telegram authentication)
|--------------------------------------------------------------------------
|
*/
Route::middleware(EnsureTelegramAuthenticated::class)->group(function () {
    // Escrow / Rekening Bersama (Accessible by users allowed to use rekber)
    Route::middleware('rekber.access')->prefix('rekber')->name('escrow.')->group(function () {
        Route::get('/', [\App\Http\Controllers\EscrowController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\EscrowController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\EscrowController::class, 'store'])->name('store');
        Route::get('/{code}', [\App\Http\Controllers\EscrowController::class, 'show'])->name('show');
        Route::get('/{code}/messages', [\App\Http\Controllers\EscrowController::class, 'getMessages'])->name('messages');
        Route::post('/{code}/deposit', [\App\Http\Controllers\EscrowController::class, 'uploadDeposit'])->name('upload-deposit');
        Route::post('/{code}/deliver', [\App\Http\Controllers\EscrowController::class, 'uploadDelivery'])->name('upload-delivery');
        Route::post('/{code}/confirm', [\App\Http\Controllers\EscrowController::class, 'confirmComplete'])->name('confirm-complete');
        Route::post('/{code}/dispute', [\App\Http\Controllers\EscrowController::class, 'openDispute'])->name('open-dispute');
        Route::post('/{code}/message', [\App\Http\Controllers\EscrowController::class, 'sendMessage'])->name('send-message');
        Route::post('/{code}/cancel', [\App\Http\Controllers\EscrowController::class, 'cancel'])->name('cancel');
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    
    // Checkout
    Route::get('/checkout/review/{product}', [CheckoutController::class, 'review'])->name('checkout.review');
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
    Route::get('/chat/search-users', [\App\Http\Controllers\ChatController::class, 'searchUsers'])->name('chat.searchUsers');
    Route::get('/chat/messages/{contactId}', [\App\Http\Controllers\ChatController::class, 'fetchMessages'])->name('chat.fetch');
    Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.send');
    
    // Manage Orders
    Route::post('/orders/{id}/cancel', [\App\Http\Controllers\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{id}/complaint', [\App\Http\Controllers\OrderController::class, 'submitComplaint'])->name('orders.complaint');
    Route::get('/orders/{id}/status', function ($id) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $order = \App\Models\Order::with('customer')->where(function ($q) use ($id) {
            $q->where('id', $id)->orWhere('order_ref', $id);
        })->firstOrFail();

        $isOwner = $user && (int) $order->customer_id === (int) $user->id;
        $isAdmin = $user && $user->role === 'admin';
        $isSameTelegram = $user && !empty($user->telegram_id) && (
            (string) $user->telegram_id === (string) $order->checkout_chat_id ||
            (string) $user->telegram_id === (string) optional($order->customer)->telegram_id
        );

        if (!$isOwner && !$isAdmin && !$isSameTelegram) {
            abort(404);
        }
        
        if ($order->status === 'pending_payment' && $order->expires_at && $order->expires_at->isPast()) {
            try {
                $orderService = app(\App\Services\OrderService::class);
                $orderService->cancelOrder($order, 'payment_timeout', \Illuminate\Support\Facades\Auth::id());
                $order = $order->fresh();
            } catch (\Exception $e) {
                \Log::error("Auto-cancel on polling failed: " . $e->getMessage());
            }
        }
        
        return response()->json(['status' => $order->status]);
    })->name('orders.status');

    // 2FA Code Generator Tool
    Route::middleware('tool.access:2fa_generator')->group(function () {
        Route::get('/tools/2fa-generator', [\App\Http\Controllers\TwoFactorController::class, 'index'])->name('tools.2fa-generator');
        Route::post('/tools/2fa-generator/generate', [\App\Http\Controllers\TwoFactorController::class, 'generateAjax'])->name('tools.2fa-generator.generate');
        Route::post('/tools/2fa-generator/batch-generate', [\App\Http\Controllers\TwoFactorController::class, 'batchGenerateAjax'])->name('tools.2fa-generator.batch-generate');
        Route::post('/tools/2fa-generator/accounts', [\App\Http\Controllers\TwoFactorController::class, 'storeAccount'])->name('tools.2fa-generator.accounts.store');
        Route::put('/tools/2fa-generator/accounts/{id}', [\App\Http\Controllers\TwoFactorController::class, 'updateAccount'])->name('tools.2fa-generator.accounts.update');
        Route::delete('/tools/2fa-generator/accounts/{id}', [\App\Http\Controllers\TwoFactorController::class, 'destroyAccount'])->name('tools.2fa-generator.accounts.destroy');
    });

    // Customer Wallet, Wishlist & Warranty Center
    Route::get('/wallet', [\App\Http\Controllers\WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/topup', [\App\Http\Controllers\WalletController::class, 'createTopup'])->name('wallet.topup.store')->middleware('throttle:10,1');
    Route::get('/wallet/topup/{id}/status', [\App\Http\Controllers\WalletController::class, 'topupStatus'])->name('wallet.topup.status');
    Route::post('/wallet/topup/{id}/cancel', [\App\Http\Controllers\WalletController::class, 'cancelTopup'])->name('wallet.topup.cancel');

    // Customer My VPN Accounts (Self-Service CRUD; Admin-Suspended accounts locked -> Chat Admin)
    Route::prefix('my-vpn')->name('vpn.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CustomerVpnController::class, 'index'])->name('my-accounts');
        Route::post('/', [\App\Http\Controllers\CustomerVpnController::class, 'store'])->name('store')->middleware('throttle:15,1');
        Route::post('/{id}/renew', [\App\Http\Controllers\CustomerVpnController::class, 'renew'])->whereNumber('id')->name('renew')->middleware('throttle:15,1');
        Route::post('/{id}/suspend', [\App\Http\Controllers\CustomerVpnController::class, 'suspend'])->whereNumber('id')->name('suspend');
        Route::post('/{id}/unsuspend', [\App\Http\Controllers\CustomerVpnController::class, 'unsuspend'])->whereNumber('id')->name('unsuspend');
        Route::post('/{id}/sync', [\App\Http\Controllers\CustomerVpnController::class, 'sync'])->whereNumber('id')->name('sync');
        Route::get('/{id}/config', [\App\Http\Controllers\CustomerVpnController::class, 'fetchConfig'])->whereNumber('id')->name('config');
        Route::delete('/{id}', [\App\Http\Controllers\CustomerVpnController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // Reseller H2H Developer API Portal
    Route::prefix('developer/api')->name('developer.api.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ResellerApiPortalController::class, 'index'])->name('index');
        Route::post('/keys', [\App\Http\Controllers\ResellerApiPortalController::class, 'storeKey'])->name('keys.store');
        Route::put('/keys/{id}', [\App\Http\Controllers\ResellerApiPortalController::class, 'updateKey'])->whereNumber('id')->name('keys.update');
        Route::post('/keys/{id}/toggle', [\App\Http\Controllers\ResellerApiPortalController::class, 'toggleKey'])->whereNumber('id')->name('keys.toggle');
        Route::post('/keys/{id}/rotate', [\App\Http\Controllers\ResellerApiPortalController::class, 'rotateKey'])->whereNumber('id')->name('keys.rotate');
        Route::delete('/keys/{id}', [\App\Http\Controllers\ResellerApiPortalController::class, 'destroyKey'])->whereNumber('id')->name('keys.destroy');
        Route::post('/admin/access-mode', [\App\Http\Controllers\ResellerApiPortalController::class, 'adminUpdateAccessMode'])->name('admin.access-mode');
        Route::post('/admin/keys/{id}/revoke', [\App\Http\Controllers\ResellerApiPortalController::class, 'adminRevokeKey'])->whereNumber('id')->name('admin.keys.revoke');
    });

    Route::get('/wishlist', [\App\Http\Controllers\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle/{productId}', [\App\Http\Controllers\WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/wishlist/restock-alert/{productId}', [\App\Http\Controllers\WishlistController::class, 'toggleRestockAlert'])->name('wishlist.restock-alert');

    Route::get('/warranties', [\App\Http\Controllers\WarrantyController::class, 'index'])->name('warranties.index');

    // Customer Complaints
    Route::get('/customer/complaints', [\App\Http\Controllers\ComplaintController::class, 'index'])->name('customer.complaints.index');
    Route::get('/customer/complaints/{id}', [\App\Http\Controllers\ComplaintController::class, 'show'])->name('customer.complaints.show');
    Route::post('/customer/complaints/{id}/reopen', [\App\Http\Controllers\ComplaintController::class, 'reopen'])->name('customer.complaints.reopen');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/edit', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/theme-color', [\App\Http\Controllers\ProfileController::class, 'updateThemeColor'])->name('profile.theme-color');
    Route::post('/profile/password', [\App\Http\Controllers\Auth\AuthController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/dismiss-password-prompt', [\App\Http\Controllers\ProfileController::class, 'dismissPasswordPrompt'])->name('profile.password.dismiss-prompt');
    Route::post('/profile/telegram-link', [\App\Http\Controllers\ProfileController::class, 'generateTelegramLink'])->name('profile.telegram.link');
    Route::post('/profile/telegram-unlink', [\App\Http\Controllers\ProfileController::class, 'unlinkTelegram'])->name('profile.telegram.unlink');
    Route::post('/profile/2fa/toggle', [\App\Http\Controllers\Auth\AuthController::class, 'toggleTwoFactor'])->name('profile.2fa.toggle');
    Route::post('/profile/totp/init', [\App\Http\Controllers\ProfileController::class, 'initTotpSetup'])->name('profile.totp.init');
    Route::post('/profile/totp/cancel', [\App\Http\Controllers\ProfileController::class, 'cancelTotpSetup'])->name('profile.totp.cancel');
    Route::post('/profile/totp/confirm', [\App\Http\Controllers\ProfileController::class, 'confirmTotpSetup'])->name('profile.totp.confirm')->middleware('throttle:10,1');
    Route::post('/profile/totp/recovery-codes', [\App\Http\Controllers\ProfileController::class, 'regenerateTotpRecoveryCodes'])->name('profile.totp.recovery-codes')->middleware('throttle:5,1');
    Route::post('/profile/totp/disable', [\App\Http\Controllers\ProfileController::class, 'disableTotp'])->name('profile.totp.disable')->middleware('throttle:5,1');
    Route::delete('/profile/sessions/{id}', [\App\Http\Controllers\ProfileController::class, 'revokeSession'])->name('profile.sessions.revoke');
    Route::post('/profile/sessions/revoke-others', [\App\Http\Controllers\ProfileController::class, 'revokeOtherSessions'])->name('profile.sessions.revoke-others');
    Route::get('/api/quick-search', [\App\Http\Controllers\QuickSearchController::class, 'search'])->name('api.quick-search')->middleware('throttle:60,1');
    Route::get('/profile/logins', [\App\Http\Controllers\ProfileController::class, 'loginHistory'])->name('profile.logins');
    Route::post('/profile/logins/block-ip', [\App\Http\Controllers\ProfileController::class, 'blockIp'])->name('profile.logins.block-ip');
    Route::post('/profile/logins/block-device', [\App\Http\Controllers\ProfileController::class, 'blockDevice'])->name('profile.logins.block-device');
    Route::post('/profile/logins/request-unblock', [\App\Http\Controllers\ProfileController::class, 'requestUnblock'])->name('profile.logins.request-unblock');

    Route::post('/admin/stop-impersonating', [\App\Http\Controllers\AdminController::class, 'stopImpersonating'])->name('admin.users.stop-impersonating');

    // Notifications (All Authenticated Users)
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/notifications/mark-read/{id}', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.markRead');

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
        Route::post('/products/{id}/takeover', [\App\Http\Controllers\AdminController::class, 'takeoverProduct'])->name('products.takeover');
        Route::post('/products/{id}/reassign', [\App\Http\Controllers\AdminController::class, 'reassignProduct'])->name('products.reassign');
        Route::get('/products/{id}/export-unsold', [\App\Http\Controllers\AdminController::class, 'exportUnsoldStock'])->name('products.export-unsold');
        Route::get('/products/{id}/report-pdf', [\App\Http\Controllers\AdminController::class, 'exportProductReportPdf'])->name('products.report-pdf');
        
        Route::get('/stock', [\App\Http\Controllers\AdminController::class, 'stock'])->name('stock.index');
        Route::get('/stock/search-customers', [\App\Http\Controllers\AdminController::class, 'searchCustomers'])->name('stock.search-customers');
        Route::post('/stock/export', [\App\Http\Controllers\AdminController::class, 'exportStock'])->name('stock.export');
        Route::post('/stock', [\App\Http\Controllers\AdminController::class, 'storeStock'])->name('stock.store');
        Route::post('/stock/checkout', [\App\Http\Controllers\AdminController::class, 'checkoutStocks'])->name('stock.checkout');
        Route::post('/stock/bulk-move', [\App\Http\Controllers\AdminController::class, 'bulkMoveStock'])->name('stock.bulkMove');
        Route::post('/stock/bulk-delete', [\App\Http\Controllers\AdminController::class, 'bulkDestroyStock'])->name('stock.bulkDestroy');
        Route::put('/stock/{id}/move', [\App\Http\Controllers\AdminController::class, 'moveStock'])->name('stock.move');
        Route::put('/stock/{id}', [\App\Http\Controllers\AdminController::class, 'updateStock'])->name('stock.update');
        Route::delete('/stock/{id}', [\App\Http\Controllers\AdminController::class, 'destroyStock'])->name('stock.destroy');
        
        Route::get('/orders', [\App\Http\Controllers\AdminController::class, 'orders'])->name('orders.index');
        Route::get('/orders/{id}', [\App\Http\Controllers\AdminController::class, 'showOrder'])->name('orders.show');
        Route::put('/orders/{id}', [\App\Http\Controllers\AdminController::class, 'updateOrder'])->name('orders.update');
        Route::post('/orders/{id}/accept', [\App\Http\Controllers\AdminController::class, 'acceptOrder'])->name('orders.accept');
        Route::post('/orders/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectOrder'])->name('orders.reject');
        Route::post('/orders/{id}/replace-stock/{stockUnitId}', [\App\Http\Controllers\AdminController::class, 'replaceStock'])->name('orders.replace-stock');
        Route::post('/orders/{id}/replace-stock-bulk', [\App\Http\Controllers\AdminController::class, 'replaceStockBulk'])->name('orders.replace-stock-bulk');
        Route::post('/orders/{id}/refund', [\App\Http\Controllers\AdminController::class, 'refundOrder'])->name('orders.refund');
        Route::post('/orders/{id}/refund-bulk', [\App\Http\Controllers\AdminController::class, 'refundBulk'])->name('orders.refund-bulk');
        
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users'])->name('users.index');
        Route::get('/sellers', [\App\Http\Controllers\AdminController::class, 'sellers'])->name('sellers.index');
        Route::get('/sellers/{id}/report-pdf', [\App\Http\Controllers\AdminController::class, 'exportSellerReportPdf'])->name('sellers.report-pdf');
        Route::post('/users/export', [\App\Http\Controllers\AdminController::class, 'exportUsers'])->name('users.export');
        Route::put('/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}', [\App\Http\Controllers\AdminController::class, 'deleteUser'])->name('users.destroy');
        Route::post('/users/{id}/suspend', [\App\Http\Controllers\AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::post('/users/{id}/unsuspend', [\App\Http\Controllers\AdminController::class, 'unsuspendUser'])->name('users.unsuspend');
        Route::post('/users/{id}/impersonate', [\App\Http\Controllers\AdminController::class, 'impersonate'])->name('users.impersonate');

        // Logins & Notifications
        Route::get('/logins', [\App\Http\Controllers\AdminController::class, 'logins'])->name('logins.index');
        Route::post('/logins/block-ip', [\App\Http\Controllers\AdminController::class, 'blockIp'])->name('logins.block-ip');
        Route::post('/logins/unblock-ip', [\App\Http\Controllers\AdminController::class, 'unblockIp'])->name('logins.unblock-ip');
        Route::post('/logins/block-device', [\App\Http\Controllers\AdminController::class, 'blockDevice'])->name('logins.block-device');
        Route::post('/logins/unblock-device', [\App\Http\Controllers\AdminController::class, 'unblockDevice'])->name('logins.unblock-device');
        Route::post('/logins/unified-block', [\App\Http\Controllers\AdminController::class, 'unifiedBlock'])->name('logins.unified-block');
        Route::post('/logins/unified-unblock', [\App\Http\Controllers\AdminController::class, 'unifiedUnblock'])->name('logins.unified-unblock');
        Route::post('/logins/unblock-all-devices', [\App\Http\Controllers\AdminController::class, 'unblockAllDevices'])->name('logins.unblock-all-devices');
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
        Route::post('/broadcast/resend/{jobId}', [\App\Http\Controllers\AdminController::class, 'resendBroadcast'])->name('broadcast.resend');
        Route::get('/broadcast/analytics/{jobId}', [\App\Http\Controllers\AdminController::class, 'getBroadcastAnalytics'])->name('broadcast.analytics');
        Route::post('/broadcast/analytics/{jobId}/recalculate', [\App\Http\Controllers\AdminController::class, 'recalculateBroadcastAnalytics'])->name('broadcast.analytics.recalculate');
        Route::get('/broadcast/failures/{jobId}/export', [\App\Http\Controllers\AdminController::class, 'exportBroadcastFailures'])->name('broadcast.failures.export');
        Route::get('/broadcast/cta-suggestions', [\App\Http\Controllers\AdminController::class, 'suggestBroadcastCtaUrls'])->name('broadcast.cta-suggestions');

        
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

        // Dashboard Promo Cards Management
        Route::get('/promos', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'index'])->name('promos.index');
        Route::get('/promos/create', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'create'])->name('promos.create');
        Route::post('/promos', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'store'])->name('promos.store');
        Route::post('/promos/reorder', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'reorder'])->name('promos.reorder');
        Route::get('/promos/{promo}/edit', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'edit'])->name('promos.edit');
        Route::put('/promos/{promo}', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'update'])->name('promos.update');
        Route::post('/promos/{promo}/move', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'move'])->name('promos.move');
        Route::post('/promos/{promo}/toggle', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'toggleStatus'])->name('promos.toggle');
        Route::delete('/promos/{promo}', [\App\Http\Controllers\Admin\DashboardPromoController::class, 'destroy'])->name('promos.destroy');
        
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
        Route::post('/backup/wipe/run', [\App\Http\Controllers\Admin\BackupController::class, 'runWipe'])->name('backup.wipe.run');
        Route::get('/backup/wipe/status', [\App\Http\Controllers\Admin\BackupController::class, 'wipeStatus'])->name('backup.wipe.status');
        
        // System Actions
        Route::get('/website/settings', [\App\Http\Controllers\AdminController::class, 'websiteSettings'])->name('website.settings');

        // Remote Control Android Root Gateway
        Route::get('/remote-control', [\App\Http\Controllers\Admin\RemoteControlController::class, 'index'])->name('remote-control.index');
        Route::get('/remote-control/devices', [\App\Http\Controllers\Admin\RemoteControlController::class, 'getDevices'])->name('remote-control.devices');
        Route::get('/remote-control/service-status', [\App\Http\Controllers\Admin\RemoteControlController::class, 'serviceStatus'])->name('remote-control.service-status');
        Route::post('/remote-control/settings', [\App\Http\Controllers\Admin\RemoteControlController::class, 'updateSettings'])->name('remote-control.settings.update');
        Route::post('/remote-control/install-service', [\App\Http\Controllers\Admin\RemoteControlController::class, 'installService'])->name('remote-control.install-service');
        Route::post('/remote-control/restart-service', [\App\Http\Controllers\Admin\RemoteControlController::class, 'restartService'])->name('remote-control.restart-service');

        // Admin Payouts / Withdrawals
        Route::get('/withdrawals', [\App\Http\Controllers\AdminController::class, 'withdrawals'])->name('withdrawals.index');
        Route::post('/withdrawals/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveWithdrawal'])->name('withdrawals.approve');
        Route::post('/withdrawals/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectWithdrawal'])->name('withdrawals.reject');

        // Admin Product Workers management
        Route::post('/products/{id}/workers', [\App\Http\Controllers\AdminController::class, 'addWorker'])->name('products.workers.store');
        Route::delete('/products/{id}/workers/{userId}', [\App\Http\Controllers\AdminController::class, 'removeWorker'])->name('products.workers.destroy');

        // Escrow Management (Rekber Admin Panel)
        Route::prefix('rekber')->name('escrow.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'store'])->name('store');
            Route::get('/{code}', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'show'])->name('show');
            Route::get('/{code}/edit', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'edit'])->name('edit');
            Route::put('/{code}', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'update'])->name('update');
            Route::delete('/{code}', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'destroy'])->name('destroy');
            Route::post('/{code}/verify-deposit', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'verifyDeposit'])->name('verify-deposit');
            Route::post('/{code}/payout', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'processPayout'])->name('payout');
            Route::post('/{code}/resolve-dispute', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'resolveDispute'])->name('resolve-dispute');
            Route::post('/settings', [\App\Http\Controllers\Admin\EscrowAdminController::class, 'updateSettings'])->name('settings');
        });

        // Admin Customer Wallet Top-ups
        Route::get('/wallet', [\App\Http\Controllers\WalletController::class, 'adminIndex'])->name('wallet.index');
        Route::post('/wallet/manual-credit', [\App\Http\Controllers\WalletController::class, 'adminManualCredit'])->name('wallet.manual-credit');
        Route::post('/wallet/topup/{id}/confirm', [\App\Http\Controllers\WalletController::class, 'adminConfirmTopup'])->name('wallet.topup.confirm');
        Route::post('/wallet/topup/{id}/reject', [\App\Http\Controllers\WalletController::class, 'adminRejectTopup'])->name('wallet.topup.reject');

        // Financial Reports & Profit/Loss (P&L)
        Route::get('/finance/reports', [\App\Http\Controllers\Admin\FinancialReportController::class, 'index'])->name('finance.reports');
        Route::get('/finance/reports/export-csv', [\App\Http\Controllers\Admin\FinancialReportController::class, 'exportCsv'])->name('finance.reports.export-csv');
        Route::get('/finance/reports/export-pdf', [\App\Http\Controllers\Admin\FinancialReportController::class, 'exportPdf'])->name('finance.reports.export-pdf');

        // System Health Monitor & VPS Control Center
        Route::get('/system/health', [\App\Http\Controllers\Admin\SystemHealthController::class, 'index'])->name('system.health');
        Route::post('/system/health/ping-telegram', [\App\Http\Controllers\Admin\SystemHealthController::class, 'pingTelegram'])->name('system.health.ping-telegram');
        Route::post('/system/health/ping-vpn', [\App\Http\Controllers\Admin\SystemHealthController::class, 'pingVpn'])->name('system.health.ping-vpn');
        Route::post('/system/health/flush-cache', [\App\Http\Controllers\Admin\SystemHealthController::class, 'flushCaches'])->name('system.health.flush-cache');
        Route::post('/system/health/artisan', [\App\Http\Controllers\Admin\SystemHealthController::class, 'runArtisan'])->name('system.health.artisan');
        Route::post('/system/health/logs/clear', [\App\Http\Controllers\Admin\SystemHealthController::class, 'clearLogs'])->name('system.health.logs.clear');
        Route::get('/system/health/logs/download', [\App\Http\Controllers\Admin\SystemHealthController::class, 'downloadLog'])->name('system.health.logs.download');
        Route::get('/system/health/logs/fetch', [\App\Http\Controllers\Admin\SystemHealthController::class, 'fetchLogs'])->name('system.health.logs.fetch');
        Route::post('/system/health/backups/prune', [\App\Http\Controllers\Admin\SystemHealthController::class, 'pruneBackups'])->name('system.health.backups.prune');
        Route::post('/system/health/maintenance', [\App\Http\Controllers\Admin\SystemHealthController::class, 'toggleMaintenance'])->name('system.health.maintenance');
        Route::post('/system/health/service-action', [\App\Http\Controllers\Admin\SystemHealthController::class, 'serviceAction'])->name('system.health.service-action');
        Route::post('/system/health/website-restart', [\App\Http\Controllers\Admin\SystemHealthController::class, 'websiteRestart'])->name('system.health.website-restart');
        Route::post('/system/health/bot-commands', [\App\Http\Controllers\Admin\SystemHealthController::class, 'toggleBotCommands'])->name('system.health.bot-commands');
        Route::post('/system/health/check-update', [\App\Http\Controllers\Admin\SystemHealthController::class, 'checkUpdate'])->name('system.health.check-update');
        Route::post('/system/health/update', [\App\Http\Controllers\Admin\SystemHealthController::class, 'runUpdate'])->name('system.health.update');
        Route::post('/system/health/rollback', [\App\Http\Controllers\Admin\SystemHealthController::class, 'runRollback'])->name('system.health.rollback');
        Route::post('/system/health/repair', [\App\Http\Controllers\Admin\SystemHealthController::class, 'runRepair'])->name('system.health.repair');
        Route::get('/system/health/task-status', [\App\Http\Controllers\Admin\SystemHealthController::class, 'taskStatus'])->name('system.health.task-status');
        Route::post('/system/health/turnstile', [\App\Http\Controllers\Admin\SystemHealthController::class, 'updateTurnstile'])->name('system.health.turnstile');
        Route::post('/system/health/reset-admin-password', [\App\Http\Controllers\Admin\SystemHealthController::class, 'resetAdminPassword'])->name('system.health.reset-admin-password');

        // Admin VPN Multi-Server & Account Management (REST API vpnxray-main + Legacy SSH)
        Route::prefix('vpn-servers')->name('vpn-servers.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\VpnServerController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\VpnServerController::class, 'store'])->name('store');
            Route::put('/{id}', [\App\Http\Controllers\Admin\VpnServerController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/default', [\App\Http\Controllers\Admin\VpnServerController::class, 'setDefault'])->whereNumber('id')->name('default');
            Route::post('/{id}/ping', [\App\Http\Controllers\Admin\VpnServerController::class, 'ping'])->whereNumber('id')->name('ping');
            Route::post('/{id}/diagnose', [\App\Http\Controllers\Admin\VpnServerController::class, 'diagnose'])->whereNumber('id')->name('diagnose');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\VpnServerController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('vpn-accounts')->name('vpn-accounts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\VpnAccountController::class, 'index'])->name('index');
            Route::get('/check-username', [\App\Http\Controllers\Admin\VpnAccountController::class, 'checkUsername'])->name('check-username')->middleware('throttle:60,1');
            Route::get('/{id}/config', [\App\Http\Controllers\Admin\VpnAccountController::class, 'fetchConfig'])->whereNumber('id')->name('config');
            Route::post('/', [\App\Http\Controllers\Admin\VpnAccountController::class, 'store'])->name('store');
            Route::post('/sync-server', [\App\Http\Controllers\Admin\VpnAccountController::class, 'syncServer'])->name('sync-server');
            Route::post('/{id}/renew', [\App\Http\Controllers\Admin\VpnAccountController::class, 'renew'])->whereNumber('id')->name('renew');
            Route::post('/{id}/suspend', [\App\Http\Controllers\Admin\VpnAccountController::class, 'suspend'])->whereNumber('id')->name('suspend');
            Route::post('/{id}/unsuspend', [\App\Http\Controllers\Admin\VpnAccountController::class, 'unsuspend'])->whereNumber('id')->name('unsuspend');
            Route::post('/{id}/retry', [\App\Http\Controllers\Admin\VpnAccountController::class, 'retry'])->whereNumber('id')->name('retry');
            Route::post('/{id}/sync', [\App\Http\Controllers\Admin\VpnAccountController::class, 'sync'])->whereNumber('id')->name('sync');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\VpnAccountController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::get('/security/firewall', [\App\Http\Controllers\Admin\FirewallController::class, 'index'])->name('security.firewall');
        Route::post('/security/firewall', [\App\Http\Controllers\Admin\FirewallController::class, 'store'])->name('security.firewall.store');
        Route::post('/security/firewall/{id}/toggle', [\App\Http\Controllers\Admin\FirewallController::class, 'toggle'])->name('security.firewall.toggle');
        Route::post('/security/firewall/auto-block', [\App\Http\Controllers\Admin\FirewallController::class, 'autoBlockAggressive'])->name('security.firewall.auto-block');
        Route::post('/security/firewall/policy', [\App\Http\Controllers\Admin\FirewallController::class, 'updatePolicy'])->name('security.firewall.policy');
        Route::post('/security/firewall/sessions/{id}/revoke', [\App\Http\Controllers\Admin\FirewallController::class, 'revokeUserSession'])->name('security.firewall.revoke-session');
        Route::post('/security/firewall/users/{id}/unsuspend', [\App\Http\Controllers\Admin\FirewallController::class, 'unsuspendUser'])->name('security.firewall.unsuspend');
        Route::delete('/security/firewall/{id}', [\App\Http\Controllers\Admin\FirewallController::class, 'destroy'])->name('security.firewall.destroy');

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
            Route::post('/gmail-checker/start-check', [\App\Http\Controllers\Admin\GmailCheckerController::class, 'startCheck'])->name('gmail-checker.start-check');
        });

        Route::middleware('tool.access:2fa_generator')->group(function () {
            Route::get('/2fa-generator', [\App\Http\Controllers\TwoFactorController::class, 'index'])->name('2fa-generator');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('seller')->prefix('seller')->name('seller.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SellerController::class, 'dashboard'])->name('dashboard');
        Route::get('/analytics', [\App\Http\Controllers\SellerController::class, 'analytics'])->name('analytics');
        
        Route::get('/stock', [\App\Http\Controllers\SellerController::class, 'stock'])->name('stock.index');
        Route::post('/stock', [\App\Http\Controllers\SellerController::class, 'storeStock'])->name('stock.store');
        Route::post('/stock/checkout', [\App\Http\Controllers\SellerController::class, 'checkoutStocks'])->name('stock.checkout');
        Route::post('/stock/bulk-move', [\App\Http\Controllers\SellerController::class, 'bulkMoveStock'])->name('stock.bulkMove');
        Route::post('/stock/bulk-delete', [\App\Http\Controllers\SellerController::class, 'bulkDestroyStock'])->name('stock.bulkDestroy');
        Route::put('/stock/{id}/move', [\App\Http\Controllers\SellerController::class, 'moveStock'])->name('stock.move');
        Route::put('/stock/{id}', [\App\Http\Controllers\SellerController::class, 'updateStock'])->name('stock.update');
        Route::delete('/stock/{id}', [\App\Http\Controllers\SellerController::class, 'destroyStock'])->name('stock.destroy');

        Route::get('/products', [\App\Http\Controllers\SellerController::class, 'products'])->name('products.index');
        Route::post('/products', [\App\Http\Controllers\SellerController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{id}', [\App\Http\Controllers\SellerController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{id}', [\App\Http\Controllers\SellerController::class, 'destroyProduct'])->name('products.destroy');
        Route::get('/products/{id}/export-unsold', [\App\Http\Controllers\SellerController::class, 'exportUnsoldStock'])->name('products.export-unsold');
        Route::post('/products/{id}/workers', [\App\Http\Controllers\SellerController::class, 'addWorker'])->name('products.workers.store');
        Route::delete('/products/{id}/workers/{userId}', [\App\Http\Controllers\SellerController::class, 'removeWorker'])->name('products.workers.destroy');
        
        Route::get('/finance', [\App\Http\Controllers\SellerController::class, 'finance'])->name('finance.index');
        Route::post('/finance/withdraw', [\App\Http\Controllers\SellerController::class, 'requestWithdrawal'])->name('finance.withdraw');
        
        Route::get('/bank-accounts', [\App\Http\Controllers\SellerController::class, 'bankAccounts'])->name('bank-accounts.index');
        Route::post('/bank-accounts', [\App\Http\Controllers\SellerController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::delete('/bank-accounts/{id}', [\App\Http\Controllers\SellerController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');

        Route::get('/orders', [\App\Http\Controllers\SellerController::class, 'orders'])->name('orders.index');
        Route::get('/orders/{id}', [\App\Http\Controllers\SellerController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{id}/cancel', [\App\Http\Controllers\SellerController::class, 'cancelOrder'])->name('orders.cancel');

        Route::get('/complaints', [\App\Http\Controllers\SellerController::class, 'complaints'])->name('complaints.index');
        Route::get('/complaints/{id}', [\App\Http\Controllers\SellerController::class, 'showComplaint'])->name('complaints.show');
        Route::post('/complaints/{id}/status', [\App\Http\Controllers\SellerController::class, 'updateComplaintStatus'])->name('complaints.updateStatus');

        // Seller Coupons Management
        Route::get('/coupons', [\App\Http\Controllers\Seller\CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [\App\Http\Controllers\Seller\CouponController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{id}', [\App\Http\Controllers\Seller\CouponController::class, 'update'])->name('coupons.update');
        Route::delete('/coupons/{id}', [\App\Http\Controllers\Seller\CouponController::class, 'destroy'])->name('coupons.destroy');

        Route::get('/settings', [\App\Http\Controllers\SellerController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SellerController::class, 'updateSettings'])->name('settings.update');
    });
});

