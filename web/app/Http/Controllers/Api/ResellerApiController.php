<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\ResellerApiLog;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResellerApiController extends Controller
{
    /**
     * GET /api/v1/ping
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('reseller_api_user');
        /** @var ResellerApiKey $apiKey */
        $apiKey = $request->attributes->get('reseller_api_key');

        $request->attributes->set('reseller_response_summary', 'Ping OK');

        return response()->json([
            'success' => true,
            'message' => 'pong',
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'key_name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/balance
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('reseller_api_user')->fresh();

        $recentTransactions = WalletTransaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'category' => $tx->category,
                'amount' => (int) $tx->amount,
                'balance_after' => (int) $tx->balance_after,
                'reference' => $tx->reference,
                'description' => $tx->description,
                'created_at' => optional($tx->created_at)->toIso8601String(),
            ]);

        $request->attributes->set('reseller_response_summary', 'Balance Rp ' . number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.'));

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => 'IDR',
                'wallet_balance' => (int) ($user->customer_wallet_balance ?? 0),
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    /**
     * GET /api/v1/products
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('is_suspended', false)
            ->orderBy('id', 'asc');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->get();

        // Pre-aggregate ready stock counts in a single SQL query (no N+1)
        $stockCounts = StockUnit::query()
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function ($q) {
                $q->where('stock_status', 'ready')->orWhereNull('stock_status');
            })
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $onlyInStock = filter_var($request->query('in_stock', false), FILTER_VALIDATE_BOOLEAN);

        $data = $products->map(function (Product $product) use ($stockCounts) {
            $availableStock = $product->is_vpn
                ? 999
                : (int) ($stockCounts[$product->id] ?? 0);

            $type = $product->is_vpn ? 'vpn' : ($product->is_vcc ? 'vcc' : 'standard');

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'currency' => 'IDR',
                'type' => $type,
                'warranty_days' => (int) ($product->warranty_days ?? 0),
                'available_stock' => $availableStock,
                'in_stock' => $availableStock > 0,
                'vpn_protocol' => $product->is_vpn ? $product->vpn_protocol : null,
                'vpn_duration_days' => $product->is_vpn ? (int) $product->vpn_duration_days : null,
                'vcc_services' => $product->is_vcc ? $product->getVccServicesWithStock() : null,
                'description' => Str::limit((string) ($product->description ?? ''), 300),
            ];
        });

        if ($onlyInStock) {
            $data = $data->filter(fn ($item) => $item['available_stock'] > 0)->values();
        } else {
            $data = $data->values();
        }

        $request->attributes->set('reseller_response_summary', 'Listed ' . $data->count() . ' products');

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/products/{id}
     */
    public function showProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)->where('is_suspended', false)->first();

        if (!$product) {
            $request->attributes->set('reseller_response_summary', "Product #{$id} not found");
            return response()->json([
                'success' => false,
                'error' => 'product_not_found',
                'message' => 'Product not found or currently unavailable.',
            ], 404);
        }

        $availableStock = $product->is_vpn
            ? 999
            : StockUnit::where('product_id', $product->id)
                ->where('is_sold', false)
                ->whereNull('sold_order_id')
                ->where(function ($q) {
                    $q->where('stock_status', 'ready')->orWhereNull('stock_status');
                })
                ->where(function ($q) {
                    $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->count();

        $type = $product->is_vpn ? 'vpn' : ($product->is_vcc ? 'vcc' : 'standard');
        $request->attributes->set('reseller_response_summary', "Product #{$id} stock={$availableStock}");

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'currency' => 'IDR',
                'type' => $type,
                'warranty_days' => (int) ($product->warranty_days ?? 0),
                'available_stock' => $availableStock,
                'in_stock' => $availableStock > 0,
                'vpn_protocol' => $product->is_vpn ? $product->vpn_protocol : null,
                'vpn_duration_days' => $product->is_vpn ? (int) $product->vpn_duration_days : null,
                'vcc_services' => $product->is_vcc ? $product->getVccServicesWithStock() : null,
                'description' => $product->description,
            ],
        ]);
    }

    /**
     * POST /api/v1/orders
     * Instant purchase deducted from customer_wallet_balance with atomic stock lock and idempotency.
     */
    public function storeOrder(Request $request, WalletService $walletService, OrderService $orderService): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('reseller_api_user');
        /** @var ResellerApiKey $apiKey */
        $apiKey = $request->attributes->get('reseller_api_key');

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'client_ref' => ['nullable', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'vcc_services' => ['nullable', 'array', 'min:1', 'max:20'],
            'vcc_services.*' => ['string', 'max:100'],
            'vpn_username' => ['nullable', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'vpn_password' => ['nullable', 'string', 'min:4', 'max:30', 'regex:/^[a-zA-Z0-9@#$%^&*_+=!-]+$/'],
        ]);

        $clientRef = isset($validated['client_ref']) ? trim($validated['client_ref']) : null;

        // Idempotency check: if same user already succeeded with this client_ref, return existing order without double-charging
        if ($clientRef) {
            $existingLog = ResellerApiLog::where('user_id', $user->id)
                ->where('client_ref', $clientRef)
                ->whereNotNull('order_ref')
                ->whereIn('status_code', [200, 201])
                ->orderByDesc('id')
                ->first();

            if ($existingLog) {
                $existingOrder = Order::with(['items.product', 'stockUnits', 'vpnAccounts'])
                    ->where('customer_id', $user->id)
                    ->where('order_ref', $existingLog->order_ref)
                    ->first();

                if ($existingOrder) {
                    $request->attributes->set('reseller_order_ref', $existingOrder->order_ref);
                    $request->attributes->set('reseller_response_summary', "Idempotent hit: {$existingOrder->order_ref}");

                    return response()->json([
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Existing order returned for duplicate client_ref.',
                        'data' => $this->formatOrderPayload($existingOrder, $user->fresh()),
                    ], 200);
                }
            }
        }

        $product = Product::where('id', $validated['product_id'])->where('is_suspended', false)->first();
        if (!$product) {
            $request->attributes->set('reseller_response_summary', 'Product unavailable');
            return response()->json([
                'success' => false,
                'error' => 'product_not_found',
                'message' => 'Product not found or currently suspended.',
            ], 404);
        }

        $vccServices = $validated['vcc_services'] ?? [];
        if ($product->is_vcc) {
            if (empty($vccServices)) {
                return response()->json([
                    'success' => false,
                    'error' => 'vcc_services_required',
                    'message' => 'Field vcc_services (array) is required for VCC products.',
                ], 422);
            }
            $quantity = count($vccServices);
        } else {
            $quantity = (int) ($validated['quantity'] ?? 1);
        }

        if ($product->is_vpn) {
            if (empty($validated['vpn_username'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'vpn_username_required',
                    'message' => 'Field vpn_username is required for VPN products.',
                ], 422);
            }
            if ($product->vpn_protocol === 'ssh' && empty($validated['vpn_password'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'vpn_password_required',
                    'message' => 'Field vpn_password is required for SSH VPN products.',
                ], 422);
            }
        }

        $subtotal = (int) $product->price * $quantity;
        $totalAmount = $subtotal;

        try {
            $order = DB::transaction(function () use (
                $user,
                $product,
                $quantity,
                $subtotal,
                $totalAmount,
                $vccServices,
                $validated,
                $walletService,
                $apiKey,
                $clientRef
            ) {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
                if ((int) ($lockedUser->customer_wallet_balance ?? 0) < $totalAmount) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                // Lock stock units BEFORE deducting balance
                $reservedUnits = collect();
                if ($product->is_vcc) {
                    foreach ($vccServices as $service) {
                        $unit = StockUnit::where('product_id', $product->id)
                            ->where('vcc_service', $service)
                            ->where('is_sold', false)
                            ->whereNull('sold_order_id')
                            ->where(function ($q) {
                                $q->where('stock_status', 'ready')->orWhereNull('stock_status');
                            })
                            ->where(function ($q) {
                                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                            })
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->first();

                        if (!$unit) {
                            throw new \RuntimeException("OUT_OF_STOCK:VCC {$service}");
                        }
                        $reservedUnits->push($unit);
                    }
                } elseif (!$product->is_vpn) {
                    $reservedUnits = StockUnit::where('product_id', $product->id)
                        ->where('is_sold', false)
                        ->whereNull('sold_order_id')
                        ->where(function ($q) {
                            $q->where('stock_status', 'ready')->orWhereNull('stock_status');
                        })
                        ->where(function ($q) {
                            $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                        })
                        ->orderBy('id', 'asc')
                        ->limit($quantity)
                        ->lockForUpdate()
                        ->get();

                    if ($reservedUnits->count() < $quantity) {
                        throw new \RuntimeException('OUT_OF_STOCK:' . $reservedUnits->count());
                    }
                }

                $orderRef = 'API' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));

                if ($totalAmount > 0) {
                    $walletService->debit(
                        $lockedUser->id,
                        $totalAmount,
                        'reseller_api_order',
                        $orderRef,
                        __('Pembelian Reseller API #:ref (:product x:qty)', [
                            'ref' => $orderRef,
                            'product' => $product->name,
                            'qty' => $quantity,
                        ]),
                        [
                            'api_key_id' => $apiKey->id,
                            'client_ref' => $clientRef,
                        ]
                    );
                }

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $lockedUser->id,
                    'subtotal' => $subtotal,
                    'coupon_code' => null,
                    'discount_amount' => 0,
                    'unique_code' => 0,
                    'total_amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes(15),
                ]);

                $vpnUsername = $product->is_vpn
                    ? $validated['vpn_username'] . '-' . strtolower(Str::random(4))
                    : null;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => (int) $product->price,
                    'discount_amount' => 0,
                    'vpn_username' => $vpnUsername,
                    'vpn_password' => $product->is_vpn ? ($validated['vpn_password'] ?? null) : null,
                    'vcc_services' => $product->is_vcc ? $vccServices : null,
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'payment_ref' => 'PAY-' . $orderRef,
                    'expected_amount' => $totalAmount,
                    'received_amount' => $totalAmount,
                    'source_app' => 'RESELLER_API',
                    'status' => 'pending',
                ]);

                $reservedIds = [];
                foreach ($reservedUnits as $unit) {
                    $unit->update([
                        'stock_status' => 'reserved_checkout',
                        'sold_order_id' => $order->id,
                    ]);
                    $reservedIds[] = $unit->id;
                }

                DB::table('audit_logs')->insert([
                    'action' => 'reseller_api_order_create',
                    'actor_id' => $lockedUser->id,
                    'entity_type' => 'order',
                    'entity_id' => $order->id,
                    'detail' => "order_ref={$orderRef}; api_key_id={$apiKey->id}; product_id={$product->id}; qty={$quantity}; total={$totalAmount}; stock_ids=[" . implode(',', $reservedIds) . "]",
                    'created_at' => now(),
                ]);

                return $order;
            });

            // Deliver immediately using core OrderService (handles seller commission & held_funds atomically)
            $orderService->confirmPayment($order, 'RESELLER_API');
            $order->refresh()->load(['items.product', 'stockUnits', 'vpnAccounts']);
            $freshUser = $user->fresh();

            $payload = $this->formatOrderPayload($order, $freshUser);
            $request->attributes->set('reseller_order_ref', $order->order_ref);
            $request->attributes->set('reseller_response_summary', "Delivered {$order->order_ref} (Rp " . number_format($totalAmount, 0, ',', '.') . ")");

            // Dispatch optional webhook safely
            $this->dispatchWebhook($apiKey, 'order.delivered', $payload);

            return response()->json([
                'success' => true,
                'message' => 'Order completed and credentials delivered.',
                'data' => $payload,
            ], 201);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                $request->attributes->set('reseller_response_summary', 'Insufficient wallet balance');
                return response()->json([
                    'success' => false,
                    'error' => 'insufficient_balance',
                    'message' => 'Customer wallet balance is insufficient for this order.',
                    'required_amount' => $totalAmount,
                    'current_balance' => (int) ($user->fresh()->customer_wallet_balance ?? 0),
                ], 402);
            }

            if (str_starts_with($e->getMessage(), 'OUT_OF_STOCK:')) {
                $detail = substr($e->getMessage(), strlen('OUT_OF_STOCK:'));
                $request->attributes->set('reseller_response_summary', 'Out of stock (' . $detail . ')');
                return response()->json([
                    'success' => false,
                    'error' => 'insufficient_stock',
                    'message' => 'Requested stock quantity is currently unavailable.',
                    'available_detail' => $detail,
                ], 409);
            }

            Log::error('Reseller API order runtime error: ' . $e->getMessage());
            $request->attributes->set('reseller_response_summary', 'Order error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'order_failed',
                'message' => 'Failed to process order.',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Reseller API order unexpected error: ' . $e->getMessage());
            $request->attributes->set('reseller_response_summary', 'Internal error');
            return response()->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred while processing order.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/orders
     */
    public function orders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('reseller_api_user');
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $orders = Order::with(['items.product'])
            ->where('customer_id', $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $orders->getCollection()->map(fn (Order $order) => [
            'order_ref' => $order->order_ref,
            'status' => $order->status,
            'total_amount' => (int) $order->total_amount,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'delivered_at' => optional($order->delivered_at)->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
            ])->values(),
        ]);

        $request->attributes->set('reseller_response_summary', 'Listed ' . $items->count() . ' orders');

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/{orderRef}
     */
    public function showOrder(Request $request, string $orderRef): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('reseller_api_user');

        $order = Order::with(['items.product', 'stockUnits', 'vpnAccounts'])
            ->where('customer_id', $user->id)
            ->where('order_ref', $orderRef)
            ->first();

        if (!$order) {
            $request->attributes->set('reseller_response_summary', "Order {$orderRef} not found");
            return response()->json([
                'success' => false,
                'error' => 'order_not_found',
                'message' => 'Order reference not found.',
            ], 404);
        }

        $request->attributes->set('reseller_order_ref', $order->order_ref);
        $request->attributes->set('reseller_response_summary', "Fetched {$order->order_ref}");

        return response()->json([
            'success' => true,
            'data' => $this->formatOrderPayload($order, $user->fresh()),
        ]);
    }

    protected function formatOrderPayload(Order $order, User $user): array
    {
        $credentials = $order->stockUnits->map(fn (StockUnit $unit) => [
            'stock_id' => $unit->id,
            'product_id' => $unit->product_id,
            'vcc_service' => $unit->vcc_service,
            'data' => $unit->raw_text,
        ])->values()->all();

        $vpnAccounts = $order->vpnAccounts->map(fn ($vpn) => [
            'id' => $vpn->id,
            'protocol' => $vpn->protocol,
            'username' => $vpn->username,
            'password' => $vpn->password,
            'uuid' => $vpn->uuid,
            'server' => $vpn->display_domain,
            'quota_gb' => (int) ($vpn->quota_gb ?? 0),
            'ip_limit' => (int) ($vpn->ip_limit ?? 1),
            'status' => $vpn->status,
            'config' => $vpn->config_payload ?? null,
            'config_text' => $vpn->config_link ?? null,
            'expired_at' => optional($vpn->expired_at)->toIso8601String(),
        ])->values()->all();

        return [
            'order_ref' => $order->order_ref,
            'status' => $order->status,
            'subtotal' => (int) $order->subtotal,
            'total_amount' => (int) $order->total_amount,
            'remaining_balance' => (int) ($user->customer_wallet_balance ?? 0),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'delivered_at' => optional($order->delivered_at)->toIso8601String(),
            'warranty_expires_at' => optional($order->warranty_expires_at)->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
            ])->values()->all(),
            'credentials' => $credentials,
            'vpn_accounts' => $vpnAccounts,
        ];
    }

    /**
     * Dispatch signed HMAC-SHA256 webhook with basic SSRF guard against local/private networks.
     */
    protected function dispatchWebhook(ResellerApiKey $apiKey, string $event, array $payload): void
    {
        $url = trim((string) ($apiKey->webhook_url ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return;
        }

        // SSRF Guard: reject localhost and private/reserved IP ranges
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return;
        }
        $resolvedIp = gethostbyname($host);
        if (filter_var($resolvedIp, FILTER_VALIDATE_IP) && !filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return;
        }

        $body = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', (string) $jsonBody, (string) ($apiKey->webhook_secret ?: $apiKey->key_hash));

        try {
            Http::timeout(4)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Reseller-Event' => $event,
                    'X-Reseller-Signature' => 'sha256=' . $signature,
                ])
                ->withBody((string) $jsonBody, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning("Reseller API webhook failed for key #{$apiKey->id}: " . $e->getMessage());
        }
    }
}
