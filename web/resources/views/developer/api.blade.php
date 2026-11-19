@extends('layouts.app')

@section('title', __('API Reseller (H2H)'))

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--on-surface);">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: var(--primary-container); color: var(--on-primary-container);">
                    <i class="fas fa-code"></i>
                </span>
                <span>{{ __('Integrasi API Reseller (H2H)') }}</span>
                <span class="badge rounded-pill" style="background: var(--tertiary-container); color: var(--on-tertiary-container); font-size: 0.72rem;">REST v1</span>
            </h1>
            <p class="text-secondary mb-0 small">
                {{ __('Hubungkan bot Telegram, toko otomatis, atau sistem reseller Anda langsung ke stok produk dengan pemotongan Dompet Saldo secara atomik dan instan.') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('wallet.index') }}" class="btn btn-sm rounded-pill px-3 fw-semibold" style="background: var(--secondary-container); color: var(--on-secondary-container);">
                <i class="fas fa-wallet me-1"></i>{{ __('Saldo Dompet') }}: Rp {{ number_format($stats['wallet_balance'], 0, ',', '.') }}
            </a>
            <a href="{{ route('developer.api.index', ['tab' => 'docs']) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                <i class="fas fa-book-open me-1"></i>{{ __('Dokumentasi API') }}
            </a>
        </div>
    </div>

    {{-- One-Time Plain API Key Reveal Alert --}}
    @if(session('new_api_key'))
    <div class="card border-0 mb-4" style="border-radius: 20px; background: var(--primary-container); color: var(--on-primary-container); border: 1px solid var(--primary) !important;">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: var(--primary); color: #fff;">
                    <i class="fas fa-key"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <h6 class="fw-bold mb-1">{{ __('Simpan API Key Anda Sekarang!') }} ({{ session('new_api_key_name') }})</h6>
                    <p class="small mb-2 opacity-90">{{ __('Demi keamanan, kunci lengkap ini hanya ditampilkan SATU KALI dan disimpan dalam bentuk hash SHA-256. Jika hilang, Anda harus melakukan rotasi kunci.') }}</p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control font-monospace fw-semibold" id="newlyCreatedApiKey" value="{{ session('new_api_key') }}" readonly>
                        <button type="button" class="btn btn-primary fw-semibold" onclick="copyTextValue('newlyCreatedApiKey', this)">
                            <i class="fas fa-copy me-1"></i>{{ __('Salin Key') }}
                        </button>
                    </div>
                    @if(session('new_webhook_secret'))
                    <div class="small fw-semibold mb-1 mt-2">{{ __('Webhook Signing Secret (HMAC-SHA256)') }}:</div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" id="newlyCreatedWebhookSecret" value="{{ session('new_webhook_secret') }}" readonly>
                        <button type="button" class="btn btn-outline-primary fw-semibold" onclick="copyTextValue('newlyCreatedWebhookSecret', this)">
                            <i class="fas fa-copy me-1"></i>{{ __('Salin Secret') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @elseif(session('new_webhook_secret'))
    <div class="alert alert-info border-0 rounded-4 mb-4">
        <div class="fw-bold mb-1"><i class="fas fa-shield-halved me-1"></i>{{ __('Webhook Signing Secret Baru Anda') }}</div>
        <div class="input-group input-group-sm mt-2">
            <input type="text" class="form-control font-monospace" id="newlyCreatedWebhookSecret" value="{{ session('new_webhook_secret') }}" readonly>
            <button type="button" class="btn btn-primary" onclick="copyTextValue('newlyCreatedWebhookSecret', this)">{{ __('Salin Secret') }}</button>
        </div>
    </div>
    @endif

    {{-- KPI Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary mb-1">{{ __('Kunci API Aktif') }}</div>
                    <div class="h4 fw-bold mb-0">{{ $stats['active_keys'] }}</div>
                    <div class="small text-secondary mt-1">{{ __('Maks. :max kunci', ['max' => $isAdmin ? 20 : 5]) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary mb-1">{{ __('Permintaan Hari Ini') }}</div>
                    <div class="h4 fw-bold mb-0">{{ number_format($stats['today_requests']) }}</div>
                    <div class="small text-secondary mt-1">{{ __('Total') }}: {{ number_format($stats['total_requests']) }} hits</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary mb-1">{{ __('Rasio Sukses (7 Hari)') }}</div>
                    <div class="h4 fw-bold mb-0 text-success">{{ number_format($stats['success_rate'], 1) }}%</div>
                    <div class="small text-secondary mt-1">HTTP 2xx</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary mb-1">{{ __('Base Endpoint') }}</div>
                    <div class="fw-bold font-monospace text-truncate small mt-1" title="{{ $baseUrl }}/api/v1">{{ $baseUrl }}/api/v1</div>
                    <div class="small text-secondary mt-1">JSON · Bearer Auth</div>
                </div>
            </div>
        </div>
    </div>

    {{-- M3 Pill Tab Strip --}}
    <div class="mb-4">
        <div class="m3-tab-strip">
            <a href="{{ route('developer.api.index', ['tab' => 'keys']) }}"
               class="m3-tab-pill {{ $activeTab === 'keys' ? 'active' : '' }}">
                <i class="fas fa-key"></i>
                <span>{{ __('Kunci API & Keamanan') }}</span>
                <span class="badge rounded-pill {{ $activeTab === 'keys' ? 'bg-primary text-white' : 'bg-secondary-subtle text-body' }}">{{ $apiKeys->count() }}</span>
            </a>
            <a href="{{ route('developer.api.index', ['tab' => 'docs']) }}"
               class="m3-tab-pill {{ $activeTab === 'docs' ? 'active' : '' }}">
                <i class="fas fa-book"></i>
                <span>{{ __('Dokumentasi & Contoh Kode') }}</span>
            </a>
            <a href="{{ route('developer.api.index', ['tab' => 'logs']) }}"
               class="m3-tab-pill {{ $activeTab === 'logs' ? 'active' : '' }}">
                <i class="fas fa-terminal"></i>
                <span>{{ __('Log Request') }}</span>
                <span class="badge rounded-pill {{ $activeTab === 'logs' ? 'bg-primary text-white' : 'bg-secondary-subtle text-body' }}">{{ $logs->count() }}</span>
            </a>
            @if($isAdmin)
            <a href="{{ route('developer.api.index', ['tab' => 'admin']) }}"
               class="m3-tab-pill {{ $activeTab === 'admin' ? 'active' : '' }}">
                <i class="fas fa-shield-halved"></i>
                <span>{{ __('Kontrol Global Admin') }}</span>
                <span class="badge rounded-pill {{ $activeTab === 'admin' ? 'bg-primary text-white' : 'bg-danger text-white' }}">{{ $adminData['global_active_keys'] ?? 0 }}</span>
            </a>
            @endif
        </div>
    </div>

    {{-- TAB 1: API KEYS --}}
    @if($activeTab === 'keys')
    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1"><i class="fas fa-plus-circle text-primary me-2"></i>{{ __('Buat API Key Baru') }}</h5>
                    <p class="small text-secondary mb-3">{{ __('Kunci API menggunakan saldo Dompet Saldo Anda untuk pembelian otomatis tanpa menunggu konfirmasi QRIS.') }}</p>

                    <form action="{{ route('developer.api.keys.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('Nama Integrasi / Label') }}</label>
                            <input type="text" name="name" class="form-control" placeholder="{{ __('Contoh: Bot Telegram Reseller') }}" required maxlength="100" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('IP Whitelist (Opsional)') }}</label>
                            <textarea name="allowed_ips" class="form-control font-monospace small" rows="2" placeholder="103.10.20.30, 103.10.21.0/24">{{ old('allowed_ips') }}</textarea>
                            <div class="form-text">{{ __('Pisahkan dengan koma atau baris baru. Kosongkan untuk mengizinkan semua IP.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('Webhook URL (Opsional)') }}</label>
                            <input type="url" name="webhook_url" class="form-control font-monospace small" placeholder="https://bot-anda.com/webhook/order" value="{{ old('webhook_url') }}">
                            <div class="form-text">{{ __('Menerima POST JSON bertanda tangan HMAC-SHA256 saat pesanan selesai.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('Rate Limit (Request / Menit)') }}</label>
                            <input type="number" name="rate_limit_per_minute" class="form-control" min="5" max="{{ $isAdmin ? 300 : 120 }}" value="{{ old('rate_limit_per_minute', 60) }}">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold py-2">
                            <i class="fas fa-key me-1"></i>{{ __('Generate API Key') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-list-check text-primary me-2"></i>{{ __('Daftar API Key Anda') }}</h5>

                    @forelse($apiKeys as $key)
                    <div class="p-3 m3-subcard mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-bold">{{ $key->name }}</span>
                                    @if($key->is_active)
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">{{ __('Aktif') }}</span>
                                    @else
                                        <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle">{{ __('Dijeda') }}</span>
                                    @endif
                                    <span class="badge rounded-pill bg-secondary-subtle text-body font-monospace">{{ $key->key_prefix }}</span>
                                </div>
                                <div class="small text-secondary mt-1 d-flex flex-wrap gap-3">
                                    <span><i class="fas fa-gauge-high me-1"></i>{{ $key->rate_limit_per_minute }} req/m</span>
                                    <span><i class="fas fa-chart-simple me-1"></i>{{ number_format($key->requests_count) }} hits</span>
                                    <span><i class="far fa-clock me-1"></i>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : __('Belum pernah dipakai') }}</span>
                                    @if($key->last_used_ip)
                                        <span class="font-monospace"><i class="fas fa-network-wired me-1"></i>{{ $key->last_used_ip }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="collapse" data-bs-target="#editKey{{ $key->id }}">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form action="{{ route('developer.api.keys.toggle', $key->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill" title="{{ $key->is_active ? __('Nonaktifkan') : __('Aktifkan') }}">
                                        <i class="fas {{ $key->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('developer.api.keys.rotate', $key->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Rotasi kunci akan membatalkan kunci lama secara instan. Lanjutkan?') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill" title="{{ __('Rotasi Kunci') }}">
                                        <i class="fas fa-arrows-rotate"></i>
                                    </button>
                                </form>
                                <form action="{{ route('developer.api.keys.destroy', $key->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Cabut API Key ini secara permanen?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="{{ __('Cabut Kunci') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="small d-flex flex-wrap gap-3 text-secondary">
                            <div>
                                <strong>{{ __('IP Whitelist') }}:</strong>
                                @if(!empty($key->allowed_ips))
                                    <span class="font-monospace">{{ implode(', ', $key->allowed_ips) }}</span>
                                @else
                                    <span>{{ __('Semua IP Diizinkan') }}</span>
                                @endif
                            </div>
                            <div>
                                <strong>{{ __('Webhook') }}:</strong>
                                <span class="font-monospace">{{ $key->webhook_url ?: __('Tidak dikonfigurasi') }}</span>
                            </div>
                        </div>

                        {{-- Inline Edit Collapse --}}
                        <div class="collapse mt-3 pt-3 border-top" id="editKey{{ $key->id }}" style="border-color: var(--outline-variant) !important;">
                            <form action="{{ route('developer.api.keys.update', $key->id) }}" method="POST" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">{{ __('Label') }}</label>
                                    <input type="text" name="name" class="form-control form-control-sm" value="{{ $key->name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">{{ __('Rate Limit / Menit') }}</label>
                                    <input type="number" name="rate_limit_per_minute" class="form-control form-control-sm" min="5" max="{{ $isAdmin ? 300 : 120 }}" value="{{ $key->rate_limit_per_minute }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">{{ __('IP Whitelist') }}</label>
                                    <input type="text" name="allowed_ips" class="form-control form-control-sm font-monospace" value="{{ is_array($key->allowed_ips) ? implode(', ', $key->allowed_ips) : '' }}" placeholder="103.10.20.30, 103.10.21.0/24">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">{{ __('Webhook URL') }}</label>
                                    <input type="url" name="webhook_url" class="form-control form-control-sm font-monospace" value="{{ $key->webhook_url }}" placeholder="https://...">
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">{{ __('Simpan Perubahan') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-5 text-secondary">
                        <i class="fas fa-key fa-2x mb-2 opacity-50"></i>
                        <div class="fw-semibold">{{ __('Belum ada API Key dibuat') }}</div>
                        <div class="small">{{ __('Gunakan formulir di samping untuk membuat API Key pertama Anda.') }}</div>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 2: DOCUMENTATION --}}
    @if($activeTab === 'docs')
    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card border-0 mb-4" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-lock text-primary me-2"></i>{{ __('Autentikasi & Keamanan') }}</h5>
                    <p class="small text-secondary">
                        {{ __('Sertakan API Key Anda pada setiap permintaan menggunakan salah satu header berikut:') }}
                    </p>
                    <pre class="p-3 rounded-4 font-monospace small mb-3" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">Authorization: Bearer rsk_live_xxxxxxxxxxxxxxxx
# {{ __('atau') }}
X-API-Key: rsk_live_xxxxxxxxxxxxxxxx</pre>
                    <div class="small text-secondary">
                        <i class="fas fa-circle-info text-primary me-1"></i>
                        {{ __('Gunakan field opsional') }} <code>client_ref</code> {{ __('pada endpoint pembelian untuk jaminan idempotency: jika permintaan diulang karena timeout jaringan, saldo tidak akan terpotong dua kali dan pesanan awal akan dikembalikan.') }}
                    </div>
                </div>
            </div>

            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-route text-primary me-2"></i>{{ __('Daftar Endpoint REST v1') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th>{{ __('Method') }}</th>
                                    <th>{{ __('Endpoint') }}</th>
                                    <th>{{ __('Fungsi') }}</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/ping</td>
                                    <td>{{ __('Uji koneksi & validasi kunci API') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/balance</td>
                                    <td>{{ __('Cek saldo Dompet Saldo & 10 mutasi terakhir') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/products?in_stock=1</td>
                                    <td>{{ __('Daftar produk aktif beserta harga & stok real-time') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/products/{id}</td>
                                    <td>{{ __('Detail 1 produk, layanan VCC, & ketersediaan stok') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">POST</span></td>
                                    <td class="font-monospace">/api/v1/orders</td>
                                    <td>{{ __('Beli produk instan (potong Dompet Saldo, langsung kirim kredensial)') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/orders</td>
                                    <td>{{ __('Riwayat pesanan akun Anda (paginasi)') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">GET</span></td>
                                    <td class="font-monospace">/api/v1/orders/{orderRef}</td>
                                    <td>{{ __('Ambil ulang detail & kredensial pesanan tertentu') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold mt-4 mb-2">{{ __('Payload POST /api/v1/orders') }}</h6>
                    <pre class="p-3 rounded-4 font-monospace small mb-0" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">{
  "product_id": 12,
  "quantity": 2,
  "client_ref": "TRX-RESELLER-20261120-001"
}</pre>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 mb-4" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-terminal text-primary me-2"></i>{{ __('Contoh Integrasi Siap Pakai') }}</h5>

                    <ul class="nav nav-pills nav-fill gap-1 mb-3 small" role="tablist">
                        <li class="nav-item"><button class="nav-link active rounded-pill py-1" data-bs-toggle="pill" data-bs-target="#codeCurl" type="button">cURL</button></li>
                        <li class="nav-item"><button class="nav-link rounded-pill py-1" data-bs-toggle="pill" data-bs-target="#codePhp" type="button">PHP</button></li>
                        <li class="nav-item"><button class="nav-link rounded-pill py-1" data-bs-toggle="pill" data-bs-target="#codePython" type="button">Python</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="codeCurl">
<pre class="p-3 rounded-4 font-monospace small mb-0" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">curl -X POST "{{ $baseUrl }}/api/v1/orders" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 1,
    "client_ref": "INV-0001"
  }'</pre>
                        </div>
                        <div class="tab-pane fade" id="codePhp">
<pre class="p-3 rounded-4 font-monospace small mb-0" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">$ch = curl_init("{{ $baseUrl }}/api/v1/orders");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer YOUR_API_KEY",
        "Content-Type: application/json",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "product_id" => 1,
        "quantity" => 1,
        "client_ref" => "INV-0001",
    ]),
]);
$res = json_decode(curl_exec($ch), true);</pre>
                        </div>
                        <div class="tab-pane fade" id="codePython">
<pre class="p-3 rounded-4 font-monospace small mb-0" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">import requests

resp = requests.post(
    "{{ $baseUrl }}/api/v1/orders",
    headers={"Authorization": "Bearer YOUR_API_KEY"},
    json={
        "product_id": 1,
        "quantity": 1,
        "client_ref": "INV-0001",
    },
    timeout=15,
)
print(resp.json())</pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="fas fa-signature text-primary me-2"></i>{{ __('Verifikasi Signature Webhook') }}</h6>
                    <p class="small text-secondary mb-2">
                        {{ __('Setiap webhook menyertakan header') }} <code>X-Reseller-Signature</code> {{ __('berformat') }} <code>sha256=&lt;hmac&gt;</code> {{ __('yang dihitung dari raw body JSON menggunakan Webhook Secret Anda.') }}
                    </p>
                    <pre class="p-3 rounded-4 font-monospace small mb-0" style="background: var(--surface-container-highest); color: var(--on-surface); overflow-x: auto;">$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);
hash_equals($expected, $_SERVER['HTTP_X_RESELLER_SIGNATURE']);</pre>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 3: REQUEST LOGS --}}
    @if($activeTab === 'logs')
    <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-terminal text-primary me-2"></i>{{ __('50 Log Permintaan API Terakhir') }}</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead>
                        <tr class="text-secondary">
                            <th>{{ __('Waktu') }}</th>
                            <th>{{ __('Key') }}</th>
                            <th>{{ __('Endpoint') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Latensi') }}</th>
                            <th>{{ __('IP') }}</th>
                            <th>{{ __('Ringkasan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ optional($log->created_at)->format('d M H:i:s') }}</td>
                            <td class="font-monospace">{{ $log->apiKey?->name ?? '-' }}</td>
                            <td class="font-monospace">
                                <span class="badge bg-secondary-subtle text-body me-1">{{ $log->method }}</span>{{ $log->endpoint }}
                            </td>
                            <td>
                                @php
                                    $stClass = $log->status_code >= 200 && $log->status_code < 300 ? 'bg-success' : ($log->status_code >= 400 && $log->status_code < 500 ? 'bg-warning text-dark' : 'bg-danger');
                                @endphp
                                <span class="badge {{ $stClass }}">{{ $log->status_code }}</span>
                            </td>
                            <td class="text-nowrap">{{ $log->duration_ms }} ms</td>
                            <td class="font-monospace">{{ $log->ip_address }}</td>
                            <td>
                                {{ $log->response_summary ?: '-' }}
                                @if($log->order_ref)
                                    <span class="badge bg-primary-subtle text-primary font-monospace ms-1">{{ $log->order_ref }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">{{ __('Belum ada riwayat panggilan API.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 4: ADMIN GLOBAL CONTROL --}}
    @if($isAdmin && $activeTab === 'admin' && $adminData)
    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-2"><i class="fas fa-sliders text-danger me-2"></i>{{ __('Kebijakan Akses Global') }}</h5>
                    <p class="small text-secondary mb-3">{{ __('Atur siapa saja yang dapat membuat API Key dan mengakses endpoint H2H Reseller.') }}</p>
                    <form action="{{ route('developer.api.admin.access-mode') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <select name="access_mode" class="form-select">
                                <option value="all" {{ $adminData['access_mode'] === 'all' ? 'selected' : '' }}>{{ __('Semua Pengguna Aktif (Default)') }}</option>
                                <option value="selected" {{ $adminData['access_mode'] === 'selected' ? 'selected' : '' }}>{{ __('Hanya Pengguna Terpilih (via Kelola Pelanggan)') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">{{ __('Simpan Kebijakan') }}</button>
                    </form>

                    <hr style="border-color: var(--outline-variant);">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-secondary">{{ __('Total Key Aktif Platform') }}</span>
                        <span class="fw-bold">{{ $adminData['global_active_keys'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-secondary">{{ __('Total Hit API Hari Ini') }}</span>
                        <span class="fw-bold">{{ number_format($adminData['global_today_requests']) }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-secondary">{{ __('Order Sukses via API Hari Ini') }}</span>
                        <span class="fw-bold text-success">{{ number_format($adminData['global_orders_today']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-users-gear text-primary me-2"></i>{{ __('Semua API Key Reseller Aktif') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>{{ __('Pemilik') }}</th>
                                    <th>{{ __('Label & Prefix') }}</th>
                                    <th>{{ __('Hits / Limit') }}</th>
                                    <th>{{ __('Terakhir Dipakai') }}</th>
                                    <th class="text-end">{{ __('Aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adminData['all_keys'] as $gKey)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $gKey->user?->display_name ?? ('User #' . $gKey->user_id) }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">{{ $gKey->user?->role }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $gKey->name }}</div>
                                        <code class="small">{{ $gKey->key_prefix }}</code>
                                    </td>
                                    <td>{{ number_format($gKey->requests_count) }} <span class="text-secondary">/ {{ $gKey->rate_limit_per_minute }}m</span></td>
                                    <td>{{ $gKey->last_used_at ? $gKey->last_used_at->diffForHumans() : '-' }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('developer.api.admin.keys.revoke', $gKey->id) }}" method="POST" onsubmit="return confirm('{{ __('Cabut paksa API Key milik reseller ini?') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">{{ __('Cabut') }}</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-secondary">{{ __('Belum ada API Key aktif di seluruh platform.') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
    function copyTextValue(inputId, btn) {
        const el = document.getElementById(inputId);
        if (!el) return;
        const val = el.value;
        navigator.clipboard.writeText(val).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __("Tersalin") }}';
            setTimeout(() => { btn.innerHTML = orig; }, 1800);
        });
    }
</script>
@endsection
