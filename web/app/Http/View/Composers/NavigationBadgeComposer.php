<?php

namespace App\Http\View\Composers;

use App\Models\BotSetting;
use App\Models\ChatMessage;
use App\Models\ComplaintCase;
use App\Models\LoginLog;
use App\Models\Order;
use App\Models\TelegramLoginToken;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class NavigationBadgeComposer
{
    public function compose(View $view): void
    {
        $globalSettings = Cache::remember('nav:global_settings', 30, function () {
            $settings = BotSetting::whereIn('key', ['maintenance_mode', 'tool_2fa_access_mode'])
                ->pluck('value', 'key');

            return [
                'maintenance_mode' => ($settings['maintenance_mode'] ?? '0') === '1',
                'tool_2fa_access_mode' => $settings['tool_2fa_access_mode'] ?? 'all',
            ];
        });

        $data = [
            'globalMaintenanceActive' => (bool) $globalSettings['maintenance_mode'],
            'unreadChatsCount' => 0,
            'adminComplaintsCount' => 0,
            'sellerComplaintsCount' => 0,
            'customerComplaintsCount' => 0,
            'adminOrdersCount' => 0,
            'sellerOrdersCount' => 0,
            'pendingPayoutsCount' => 0,
            'pendingLoginsCount' => 0,
            'customerLoginBadgeCount' => 0,
            'canAccess2fa' => false,
        ];

        $auth = Auth::user();
        if ($auth) {
            $cacheKey = 'nav:badges:u:' . $auth->id . ':r:' . $auth->role;
            $userBadges = Cache::remember($cacheKey, 15, function () use ($auth) {
                $badges = [
                    'unreadChatsCount' => ChatMessage::where('receiver_id', $auth->id)
                        ->where('is_read', false)
                        ->count(),
                    'adminComplaintsCount' => 0,
                    'sellerComplaintsCount' => 0,
                    'customerComplaintsCount' => 0,
                    'adminOrdersCount' => 0,
                    'sellerOrdersCount' => 0,
                    'pendingPayoutsCount' => 0,
                    'pendingLoginsCount' => 0,
                    'customerLoginBadgeCount' => 0,
                ];

                if ($auth->role === 'admin') {
                    $badges['adminComplaintsCount'] = ComplaintCase::whereIn('status', ['open', 'customer_replied'])->count();
                    $badges['adminOrdersCount'] = Order::whereIn('status', ['pending_payment', 'paid'])->count();
                    $badges['pendingPayoutsCount'] = WithdrawalRequest::where('status', 'pending')->count();
                    $badges['pendingLoginsCount'] = TelegramLoginToken::where('status', 'pending')->count();
                } elseif ($auth->role === 'seller') {
                    $badges['sellerComplaintsCount'] = ComplaintCase::whereIn('status', ['open', 'customer_replied'])
                        ->whereHas('order.items.product', function ($q) use ($auth) {
                            $q->where('creator_id', $auth->id);
                        })->count();
                    $badges['sellerOrdersCount'] = Order::whereIn('status', ['pending_payment', 'paid'])
                        ->whereHas('items.product', function ($q) use ($auth) {
                            $q->where('creator_id', $auth->id);
                        })->count();
                } else {
                    $badges['customerComplaintsCount'] = ComplaintCase::where('customer_id', $auth->id)
                        ->whereIn('status', ['seller_replied'])
                        ->count();

                    $customerPendingLogins = 0;
                    if ($auth->telegram_id) {
                        $customerPendingLogins = TelegramLoginToken::where('telegram_id', $auth->telegram_id)
                            ->where('status', 'pending')
                            ->count();
                    }

                    $userIps = LoginLog::where(function ($q) use ($auth) {
                        $q->where('user_id', $auth->id);
                        if (!empty($auth->username)) {
                            $q->orWhere('username_or_email', $auth->username);
                        }
                        if (!empty($auth->email)) {
                            $q->orWhere('username_or_email', $auth->email);
                        }
                    })->distinct()->pluck('ip_address');

                    $blockedUserIpsCount = 0;
                    foreach ($userIps as $ip) {
                        if (Cache::has('blocked_ip:' . $ip) || Cache::has('blocked_user_ip:' . $auth->id . ':' . $ip)) {
                            $blockedUserIpsCount++;
                        }
                    }

                    $badges['customerLoginBadgeCount'] = $customerPendingLogins + $blockedUserIpsCount;
                }

                return $badges;
            });

            $data = array_merge($data, $userBadges);
            $tool2faAccessMode = $globalSettings['tool_2fa_access_mode'];
            $data['canAccess2fa'] = $auth->role === 'admin'
                || $tool2faAccessMode === 'all'
                || (is_array($auth->allowed_tools) && in_array('2fa_generator', $auth->allowed_tools, true));
        }

        $view->with($data);
    }
}
