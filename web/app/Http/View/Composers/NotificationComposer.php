<?php

namespace App\Http\View\Composers;

use App\Models\Order;
use App\Models\StockUnit;
use App\Models\TelegramLoginToken;
use Illuminate\View\View;

class NotificationComposer
{
    public function compose(View $view)
    {
        $readAt = session('notifications_read_at');

        $pendingOrdersQuery = Order::whereIn('status', ['pending_payment', 'paid']);
        $pendingLoginsQuery = TelegramLoginToken::where('status', 'pending');
        
        if ($readAt) {
            $pendingOrdersQuery->where('created_at', '>', $readAt);
            $pendingLoginsQuery->where('created_at', '>', $readAt);
        }

        $pendingOrdersCount = $pendingOrdersQuery->count();
        $pendingLoginsCount = $pendingLoginsQuery->count();
        $readyStockCount = StockUnit::where('stock_status', 'ready')->where('is_sold', false)->count();

        $saveHours = \App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80;
        $readyToVerifyQuery = StockUnit::where('stock_status', 'saved_for_verification')
            ->where('is_sold', false)
            ->where(function($query) use ($saveHours) {
                $query->whereNotNull('available_at')
                      ->where('available_at', '<=', now())
                      ->orWhere(function($q) use ($saveHours) {
                          $q->whereNull('available_at')
                            ->where('created_at', '<=', now()->subHours((int)$saveHours));
                      });
            });
            
        if ($readAt) {
            $readyToVerifyQuery->where('created_at', '>', $readAt);
        }
        $readyToVerifyCount = $readyToVerifyQuery->count();

        $totalNotifications = $pendingOrdersCount + $pendingLoginsCount + $readyToVerifyCount;

        $view->with(compact('pendingOrdersCount', 'pendingLoginsCount', 'readyStockCount', 'readyToVerifyCount', 'totalNotifications'));
    }
}
