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

        $totalNotifications = $pendingOrdersCount + $pendingLoginsCount;

        $view->with(compact('pendingOrdersCount', 'pendingLoginsCount', 'readyStockCount', 'totalNotifications'));
    }
}
