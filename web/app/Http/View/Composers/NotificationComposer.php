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

        // Completed Broadcasts
        $completedBroadcastsQuery = \App\Models\BroadcastJob::where('status', 'completed')->where('is_read', false);
        $completedBroadcastsCount = $completedBroadcastsQuery->count();
        $completedBroadcasts = $completedBroadcastsQuery->get();

        // Escrow Count for Sidebar Badges
        $activeEscrowCount = 0;
        $adminEscrowCount = 0;
        if (\Illuminate\Support\Facades\Auth::check()) {
            $authUser = \Illuminate\Support\Facades\Auth::user();
            if ($authUser->role === 'admin') {
                $adminEscrowCount = \App\Models\EscrowTransaction::whereIn('status', [
                    \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
                    \App\Models\EscrowTransaction::STATUS_WAITING_PAYOUT,
                    \App\Models\EscrowTransaction::STATUS_DISPUTED,
                ])->count();
            }

            if ($authUser->canUseRekber()) {
                $activeEscrowCount = \App\Models\EscrowTransaction::where(function ($q) use ($authUser) {
                    $q->where('creator_id', $authUser->id)
                      ->orWhere('buyer_user_id', $authUser->id)
                      ->orWhere('seller_user_id', $authUser->id);
                })->whereIn('status', [
                    \App\Models\EscrowTransaction::STATUS_WAITING_DEPOSIT,
                    \App\Models\EscrowTransaction::STATUS_FUNDS_HELD,
                    \App\Models\EscrowTransaction::STATUS_DELIVERED,
                    \App\Models\EscrowTransaction::STATUS_DISPUTED,
                ])->count();
            }
        }

        $totalNotifications = $pendingOrdersCount + $pendingLoginsCount + $readyToVerifyCount + $completedBroadcastsCount;

        $view->with(compact(
            'pendingOrdersCount', 
            'pendingLoginsCount', 
            'readyStockCount', 
            'readyToVerifyCount', 
            'completedBroadcastsCount',
            'completedBroadcasts',
            'totalNotifications',
            'activeEscrowCount',
            'adminEscrowCount'
        ));
    }
}
