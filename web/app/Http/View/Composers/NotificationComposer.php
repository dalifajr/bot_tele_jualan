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
        $pendingOrdersCount = Order::whereIn('status', ['pending_payment', 'paid'])->count();
        $pendingLoginsCount = TelegramLoginToken::where('status', 'pending')->count();
        $readyStockCount = StockUnit::where('stock_status', 'ready')->where('is_sold', false)->count();

        $actualTotal = $pendingOrdersCount + $pendingLoginsCount;
        $clearedCount = session('notifications_cleared_count', 0);
        
        $totalNotifications = max(0, $actualTotal - $clearedCount);

        $view->with(compact('pendingOrdersCount', 'pendingLoginsCount', 'readyStockCount', 'totalNotifications'));
    }
}
