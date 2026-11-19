<?php

use App\Http\Controllers\Api\ResellerApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reseller H2H REST API v1
|--------------------------------------------------------------------------
| Authenticated via Authorization: Bearer rsk_live_... or X-API-Key header.
*/
Route::prefix('v1')
    ->middleware(['reseller.api'])
    ->name('api.v1.')
    ->group(function () {
        Route::get('/ping', [ResellerApiController::class, 'ping'])->name('ping');
        Route::get('/balance', [ResellerApiController::class, 'balance'])->name('balance');
        Route::get('/products', [ResellerApiController::class, 'products'])->name('products.index');
        Route::get('/products/{id}', [ResellerApiController::class, 'showProduct'])->whereNumber('id')->name('products.show');
        Route::get('/orders', [ResellerApiController::class, 'orders'])->name('orders.index');
        Route::post('/orders', [ResellerApiController::class, 'storeOrder'])->name('orders.store');
        Route::get('/orders/{orderRef}', [ResellerApiController::class, 'showOrder'])->name('orders.show');
    });
