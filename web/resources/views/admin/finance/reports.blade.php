@extends('layouts.app')

@section('title', __('Laporan Keuangan & Laba Rugi (P&L)'))

@section('content')
@php
    $pnl = $report['pnl'];
    $cf = $report['cashflow'];
    $liab = $report['liabilities'];
    $daily = $report['daily_series'];
    $exportParams = [
        'preset' => $period['preset'],
        'start_date' => $period['start']->format('Y-m-d'),
        'end_date' => $period['end']->format('Y-m-d'),
    ];
    $fmtRp = fn ($n) => ($n < 0 ? '-Rp ' : 'Rp ') . number_format(abs((int) $n), 0, ',', '.');
@endphp

<div class="container-fluid px-0">
    {{-- Header & Export Controls --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--on-surface);">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px; background: var(--primary-container); color: var(--on-primary-container);">
                    <i class="fas fa-file-invoice-dollar"></i>
                </span>
                <span>{{ __('Laporan Keuangan & Laba Rugi (P&L)') }}</span>
            </h1>
            <p class="text-secondary mb-0 small">
                {{ __('Ringkasan akuntansi terpadu: Omzet Kotor, Pendapatan Bersih Platform, Komisi Seller, Fee Rekber, Arus Kas Dompet, serta Neraca Kewajiban (Liabilitas).') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.finance.reports.export-csv', $exportParams) }}" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                <i class="fas fa-file-csv me-1"></i>{{ __('Ekspor CSV (Excel)') }}
            </a>
            <a href="{{ route('admin.finance.reports.export-pdf', $exportParams) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                <i class="fas fa-file-pdf me-1"></i>{{ __('Unduh Laporan PDF') }}
            </a>
        </div>
    </div>

    {{-- Period Filter Bar --}}
    <div class="card border-0 mb-4" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.finance.reports') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="col-12 col-md-auto">
                    <div class="d-flex flex-nowrap overflow-x-auto gap-1.5 pb-1" style="scrollbar-width: thin;">
                        @foreach([
                            'today' => __('Hari Ini'),
                            '7d' => __('7 Hari'),
                            '30d' => __('30 Hari'),
                            'this_month' => __('Bulan Ini'),
                            'last_month' => __('Bulan Lalu'),
                            'this_year' => __('Tahun Ini'),
                        ] as $pKey => $pLabel)
                        <a href="{{ route('admin.finance.reports', ['tab' => $activeTab, 'preset' => $pKey]) }}"
                           class="btn btn-sm rounded-pill px-3 text-nowrap flex-shrink-0 {{ $period['preset'] === $pKey ? 'btn-primary fw-semibold' : 'btn-outline-secondary' }}">
                            {{ $pLabel }}
                        </a>
                        @endforeach
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">{{ __('Dari Tanggal') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $period['start']->format('Y-m-d') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">{{ __('Sampai Tanggal') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $period['end']->format('Y-m-d') }}">
                </div>
                <div class="col-12 col-md-auto">
                    <input type="hidden" name="preset" value="custom">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                        <i class="fas fa-filter me-1"></i>{{ __('Terapkan Rentang') }}
                    </button>
                </div>
                <div class="col-12 col-md text-md-end small text-secondary">
                    <i class="far fa-calendar-check me-1"></i>{{ __('Periode Aktif') }}: <strong>{{ $period['label'] }}</strong>
                    ({{ $period['start']->format('d M Y') }} – {{ $period['end']->format('d M Y') }})
                </div>
            </form>
        </div>
    </div>

    {{-- Top KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card m3-card-primary h-100">
                <div class="card-body p-3">
                    <div class="small fw-semibold opacity-75">{{ __('Pendapatan Bersih Platform') }}</div>
                    <div class="h4 fw-bold my-1">{{ $fmtRp($pnl['net_platform_revenue']) }}</div>
                    <div class="small opacity-85">{{ __('Admin + Komisi Seller + Fee Rekber − Refund') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Total GMV Penjualan') }}</div>
                    <div class="h4 fw-bold my-1 text-success">{{ $fmtRp($pnl['gross_gmv']) }}</div>
                    <div class="small text-secondary">{{ number_format($pnl['orders_delivered_count']) }} {{ __('order selesai') }} · AOV {{ $fmtRp($pnl['avg_order_value']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Arus Top-Up vs Payout') }}</div>
                    <div class="h5 fw-bold my-1 text-primary">+{{ $fmtRp($cf['topup_confirmed_total']) }}</div>
                    <div class="small text-danger">{{ __('Payout Keluar') }}: {{ $fmtRp($cf['payout_approved_total']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Total Kewajiban Kas (Liabilitas)') }}</div>
                    <div class="h4 fw-bold my-1 text-warning">{{ $fmtRp($liab['total_liabilities']) }}</div>
                    <div class="small text-secondary">{{ __('Dana milik Customer, Seller & Rekber aktif') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- M3 Pill Tab Strip --}}
    <div class="mb-4">
        <div class="m3-tab-strip">
            @foreach([
                'pnl' => ['icon' => 'fa-scale-balanced', 'label' => __('Laba Rugi (P&L) & Tren')],
                'liabilities' => ['icon' => 'fa-vault', 'label' => __('Neraca Kewajiban (Liabilitas)')],
                'performance' => ['icon' => 'fa-ranking-star', 'label' => __('Kontribusi Produk & Seller')],
                'ledger' => ['icon' => 'fa-book-journal-whills', 'label' => __('Buku Besar (Ledger)')],
            ] as $tKey => $tMeta)
            <a href="{{ route('admin.finance.reports', array_merge($exportParams, ['tab' => $tKey])) }}"
               class="m3-tab-pill {{ $activeTab === $tKey ? 'active' : '' }}">
                <i class="fas {{ $tMeta['icon'] }}"></i>
                <span>{{ $tMeta['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- TAB 1: PROFIT & LOSS STATEMENT + DAILY CHART --}}
    @if($activeTab === 'pnl')
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 h-100" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-table-list text-primary me-2"></i>{{ __('Laporan Laba Rugi Operasional (P&L)') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody class="small">
                                <tr class="table-active">
                                    <th colspan="2" class="py-2">{{ __('A. PENDAPATAN PLATFORM (REVENUE)') }}</th>
                                </tr>
                                <tr>
                                    <td>{{ __('Penjualan Produk Toko Utama (Admin)') }}</td>
                                    <td class="text-end fw-semibold">{{ $fmtRp($pnl['admin_product_revenue']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Pendapatan Komisi Platform dari Mitra Seller') }}</td>
                                    <td class="text-end fw-semibold text-success">{{ $fmtRp($pnl['platform_seller_commission']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Pendapatan Fee Rekber / Escrow Selesai') }} ({{ $cf['escrow_completed_count'] }})</td>
                                    <td class="text-end fw-semibold text-info">{{ $fmtRp($pnl['escrow_fee_revenue']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Pendapatan Kode Unik Pembayaran') }}</td>
                                    <td class="text-end fw-semibold">{{ $fmtRp($pnl['unique_code_revenue']) }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fw-bold">{{ __('Total Pendapatan Kotor Platform') }}</td>
                                    <td class="text-end fw-bold">{{ $fmtRp($pnl['gross_platform_income']) }}</td>
                                </tr>

                                <tr class="table-active">
                                    <th colspan="2" class="py-2">{{ __('B. PENGURANG & BEBAN LANGSUNG') }}</th>
                                </tr>
                                <tr>
                                    <td>{{ __('Beban Refund / Kompensasi Garansi ke Dompet') }}</td>
                                    <td class="text-end fw-semibold text-danger">{{ $fmtRp(-$pnl['wallet_refund_total']) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary">{{ __('Memo: Total Diskon Kupon Terpakai (sudah memotong GMV)') }}</td>
                                    <td class="text-end text-secondary">{{ $fmtRp($pnl['coupon_discounts']) }}</td>
                                </tr>

                                <tr class="border-top border-2" style="background: var(--primary-container); color: var(--on-primary-container);">
                                    <th class="py-2">{{ __('ESTIMASI LABA BERSIH PLATFORM (NET REVENUE)') }}</th>
                                    <th class="text-end py-2 fs-6">{{ $fmtRp($pnl['net_platform_revenue']) }}</th>
                                </tr>

                                <tr class="table-active">
                                    <th colspan="2" class="py-2">{{ __('C. MEMO VOLUME PERDAGANGAN & ARUS KAS') }}</th>
                                </tr>
                                <tr>
                                    <td>{{ __('GMV Kotor Produk Mitra Seller') }}</td>
                                    <td class="text-end">{{ $fmtRp($pnl['seller_gross_sales']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Hak Bersih Mitra Seller (Diteruskan ke Seller)') }}</td>
                                    <td class="text-end">{{ $fmtRp($pnl['seller_net_earnings']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Volume Belanja via Reseller API (H2H)') }}</td>
                                    <td class="text-end fw-semibold text-primary">{{ $fmtRp($pnl['reseller_api_revenue']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Total Top-Up Dompet Terkonfirmasi') }} ({{ $cf['topup_count'] }}x)</td>
                                    <td class="text-end">{{ $fmtRp($cf['topup_confirmed_total']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Injeksi Saldo Manual Admin / Potongan Manual') }}</td>
                                    <td class="text-end">+{{ $fmtRp($cf['wallet_manual_credits']) }} / -{{ $fmtRp($cf['wallet_manual_debits']) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Total Payout Seller Dicairkan') }} ({{ $cf['payout_approved_count'] }}x)</td>
                                    <td class="text-end text-danger">{{ $fmtRp($cf['payout_approved_total']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 h-100" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-chart-column text-primary me-2"></i>{{ __('Tren Harian GMV vs Pendapatan Platform') }}</h5>
                        <span class="badge rounded-pill bg-secondary-subtle text-body">{{ count($daily) }} {{ __('hari') }}</span>
                    </div>

                    @php
                        $chartSlice = array_slice($daily, -14);
                        $maxVal = max(1, collect($chartSlice)->max('gmv'), collect($chartSlice)->max('platform_revenue'));
                    @endphp
                    <div class="d-flex align-items-end gap-2 pb-2 pt-3" style="height: 220px; overflow-x: auto;">
                        @foreach($chartSlice as $day)
                        @php
                            $gmvH = max(4, (int) round(($day['gmv'] / $maxVal) * 165));
                            $revH = max(4, (int) round(($day['platform_revenue'] / $maxVal) * 165));
                        @endphp
                        <div class="d-flex flex-column align-items-center flex-fill" style="min-width: 34px;" title="{{ $day['date'] }} | GMV: {{ $fmtRp($day['gmv']) }} | Platform: {{ $fmtRp($day['platform_revenue']) }}">
                            <div class="d-flex align-items-end gap-1" style="height: 170px;">
                                <div class="rounded-top" style="width: 11px; height: {{ $day['gmv'] > 0 ? $gmvH : 3 }}px; background: rgba(var(--primary-rgb), 0.35);"></div>
                                <div class="rounded-top" style="width: 11px; height: {{ $day['platform_revenue'] > 0 ? $revH : 3 }}px; background: var(--primary);"></div>
                            </div>
                            <span class="text-secondary mt-1" style="font-size: 0.65rem;">{{ $day['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-3 small text-secondary mt-2 mb-3">
                        <span><span class="d-inline-block rounded-1 me-1" style="width: 10px; height: 10px; background: rgba(var(--primary-rgb), 0.35);"></span>{{ __('GMV Penjualan') }}</span>
                        <span><span class="d-inline-block rounded-1 me-1" style="width: 10px; height: 10px; background: var(--primary);"></span>{{ __('Pendapatan Platform') }}</span>
                    </div>

                    <div class="table-responsive" style="max-height: 230px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="sticky-top" style="background: var(--surface-container-low);">
                                <tr class="text-secondary">
                                    <th>{{ __('Tanggal') }}</th>
                                    <th class="text-center">{{ __('Order') }}</th>
                                    <th class="text-end">{{ __('GMV') }}</th>
                                    <th class="text-end">{{ __('Platform') }}</th>
                                    <th class="text-end">{{ __('Top-Up') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_reverse($daily) as $day)
                                @if($day['orders_count'] > 0 || $day['platform_revenue'] > 0 || $day['topup_in'] > 0 || $day['payout_out'] > 0)
                                <tr>
                                    <td class="text-nowrap">{{ $day['date'] }}</td>
                                    <td class="text-center">{{ $day['orders_count'] }}</td>
                                    <td class="text-end">{{ $fmtRp($day['gmv']) }}</td>
                                    <td class="text-end fw-semibold text-primary">{{ $fmtRp($day['platform_revenue']) }}</td>
                                    <td class="text-end text-success">{{ $fmtRp($day['topup_in']) }}</td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 2: LIABILITIES / BALANCE SHEET --}}
    @if($activeTab === 'liabilities')
    <div class="alert border-0 rounded-4 mb-4" style="background: var(--secondary-container); color: var(--on-secondary-container);">
        <i class="fas fa-circle-info me-2"></i>
        <strong>{{ __('Catatan Akuntansi Penting:') }}</strong>
        {{ __('Total Kewajiban Kas di bawah ini adalah dana titipan milik Pelanggan (Dompet Saldo), Mitra Seller (Saldo Siap Tarik & Garansi Tertahan), serta transaksi Rekber aktif. Pastikan saldo rekening bank/QRIS operasional minimal sama atau lebih besar dari angka ini sebelum menarik profit pribadi.') }}
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Saldo Dompet Pelanggan') }}</div>
                    <div class="h5 fw-bold mt-1 text-primary">{{ $fmtRp($liab['customer_wallet_liability']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Saldo Siap Tarik Seller') }}</div>
                    <div class="h5 fw-bold mt-1 text-success">{{ $fmtRp($liab['seller_available_payable']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Dana Garansi Tertahan (Held)') }}</div>
                    <div class="h5 fw-bold mt-1 text-warning">{{ $fmtRp($liab['held_funds_liability']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 h-100" style="border-radius: 20px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3">
                    <div class="small text-secondary">{{ __('Dana Rekber Aktif Ditahan') }}</div>
                    <div class="h5 fw-bold mt-1 text-info">{{ $fmtRp($liab['active_escrow_liability']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-wallet text-primary me-2"></i>{{ __('Top 10 Pemegang Saldo Dompet Pelanggan') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>{{ __('Pengguna') }}</th>
                                    <th>{{ __('Role') }}</th>
                                    <th class="text-end">{{ __('Saldo Dompet') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($liab['top_customer_wallets'] as $cw)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $cw->display_name }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">@{{ $cw->username ?: 'user-' . $cw->id }}</div>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-body">{{ $cw->role }}</span></td>
                                    <td class="text-end fw-bold text-primary">{{ $fmtRp($cw->customer_wallet_balance) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center py-4 text-secondary">{{ __('Tidak ada saldo mengendap.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-store text-success me-2"></i>{{ __('Top 10 Kewajiban Saldo Mitra Seller') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>{{ __('Mitra Seller') }}</th>
                                    <th class="text-end">{{ __('Siap Tarik') }}</th>
                                    <th class="text-end">{{ __('Tertahan') }}</th>
                                    <th class="text-end">{{ __('Total Hak') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($liab['top_seller_payables'] as $sp)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $sp['name'] }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">Fee {{ $sp['fee_percent'] }}%</div>
                                    </td>
                                    <td class="text-end">{{ $fmtRp($sp['available']) }}</td>
                                    <td class="text-end text-warning">{{ $fmtRp($sp['held']) }}</td>
                                    <td class="text-end fw-bold text-success">{{ $fmtRp($sp['total']) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center py-4 text-secondary">{{ __('Tidak ada kewajiban saldo seller saat ini.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 3: PRODUCT & SELLER PERFORMANCE --}}
    @if($activeTab === 'performance')
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3 p-md-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-box-open text-primary me-2"></i>{{ __('Top Produk Berdasarkan Kontribusi Profit Platform') }}</h5>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>{{ __('Produk') }}</th>
                                    <th class="text-center">{{ __('Terjual') }}</th>
                                    <th class="text-end">{{ __('GMV') }}</th>
                                    <th class="text-end">{{ __('Profit Platform') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['top_products'] as $tp)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $tp['name'] }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">{{ $tp['owner_name'] }}</div>
                                    </td>
                                    <td class="text-center m3-tabular-nums">{{ number_format($tp['units_sold']) }}</td>
                                    <td class="text-end m3-tabular-nums">{{ $fmtRp($tp['gmv']) }}</td>
                                    <td class="text-end fw-bold text-primary m3-tabular-nums">{{ $fmtRp($tp['platform_revenue']) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center py-4 text-secondary">{{ __('Belum ada penjualan produk pada periode ini.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-md-none d-flex flex-column gap-1.5">
                        @forelse($report['top_products'] as $tp)
                        <div class="m3-mobile-item-card is-ultra-compact">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-body text-truncate" style="font-size: 0.83rem;">{{ $tp['name'] }}</div>
                                    <div class="text-secondary text-truncate m3-tabular-nums" style="font-size: 0.72rem;">
                                        {{ $tp['owner_name'] }} · {{ number_format($tp['units_sold']) }} unit · GMV {{ $fmtRp($tp['gmv']) }}
                                    </div>
                                </div>
                                <span class="fw-bold text-primary flex-shrink-0 m3-tabular-nums" style="font-size: 0.82rem;">{{ $fmtRp($tp['platform_revenue']) }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">{{ __('Belum ada penjualan produk pada periode ini.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
                <div class="card-body p-3 p-md-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-medal text-warning me-2"></i>{{ __('Top Mitra Seller & Kontribusi Komisi') }}</h5>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead>
                                <tr class="text-secondary">
                                    <th>{{ __('Seller') }}</th>
                                    <th class="text-center">{{ __('Unit') }}</th>
                                    <th class="text-end">{{ __('GMV') }}</th>
                                    <th class="text-end">{{ __('Komisi Platform') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['top_sellers'] as $ts)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $ts['name'] }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">@{{ $ts['username'] ?: 'seller-' . $ts['seller_id'] }}</div>
                                    </td>
                                    <td class="text-center m3-tabular-nums">{{ number_format($ts['units_sold']) }}</td>
                                    <td class="text-end m3-tabular-nums">{{ $fmtRp($ts['gmv']) }}</td>
                                    <td class="text-end fw-bold text-success m3-tabular-nums">{{ $fmtRp($ts['platform_fee']) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center py-4 text-secondary">{{ __('Belum ada transaksi produk seller pada periode ini.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-md-none d-flex flex-column gap-1.5">
                        @forelse($report['top_sellers'] as $ts)
                        <div class="m3-mobile-item-card is-ultra-compact">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-body text-truncate" style="font-size: 0.83rem;">{{ $ts['name'] }}</div>
                                    <div class="text-secondary text-truncate m3-tabular-nums" style="font-size: 0.72rem;">
                                        @{{ $ts['username'] ?: 'seller-' . $ts['seller_id'] }} · {{ number_format($ts['units_sold']) }} unit · GMV {{ $fmtRp($ts['gmv']) }}
                                    </div>
                                </div>
                                <span class="fw-bold text-success flex-shrink-0 m3-tabular-nums" style="font-size: 0.82rem;">{{ $fmtRp($ts['platform_fee']) }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">{{ __('Belum ada transaksi produk seller pada periode ini.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 4: UNIFIED LEDGER --}}
    @if($activeTab === 'ledger')
    <div class="card border-0" style="border-radius: 22px; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-book-journal-whills text-primary me-2"></i>{{ __('Buku Besar Transaksi Lintas Modul') }}</h5>
                <span class="badge rounded-pill bg-secondary-subtle text-body">{{ $report['ledger']->count() }} {{ __('entri') }}</span>
            </div>
            {{-- Desktop Table --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead>
                        <tr class="text-secondary">
                            <th>{{ __('Waktu') }}</th>
                            <th>{{ __('Kategori') }}</th>
                            <th>{{ __('Referensi') }}</th>
                            <th>{{ __('Pihak') }}</th>
                            <th>{{ __('Keterangan') }}</th>
                            <th class="text-end">{{ __('Nominal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['ledger'] as $row)
                        <tr>
                            <td class="text-nowrap">{{ optional($row['date'])->format('d M Y H:i') }}</td>
                            <td><span class="badge bg-{{ $row['badge'] }}-subtle text-{{ $row['badge'] }} border border-{{ $row['badge'] }}-subtle">{{ $row['type_label'] }}</span></td>
                            <td class="font-monospace">{{ $row['reference'] }}</td>
                            <td>{{ $row['party'] }}</td>
                            <td class="text-secondary">{{ \Illuminate\Support\Str::limit($row['detail'], 60) }}</td>
                            <td class="text-end fw-bold m3-tabular-nums {{ $row['direction'] === 'in' ? 'text-success' : 'text-danger' }}">
                                {{ $row['direction'] === 'in' ? '+' : '-' }}{{ $fmtRp($row['amount']) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">{{ __('Belum ada transaksi finansial pada periode yang dipilih.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Collapsible Compact List (< 768px) --}}
            <div class="d-md-none m3-collapsible-list" id="adminFinanceLedgerAccordion">
                @forelse($report['ledger'] as $row)
                <div class="m3-mobile-item-card m3-collapsible-card">
                    <div class="m3-compact-row">
                        <button type="button"
                                class="m3-compact-trigger"
                                data-bs-toggle="collapse"
                                data-bs-target="#adminLedgerCollapse-{{ $loop->index }}"
                                aria-expanded="false"
                                aria-controls="adminLedgerCollapse-{{ $loop->index }}">
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-1.5 min-w-0">
                                    <span class="badge bg-{{ $row['badge'] }}-subtle text-{{ $row['badge'] }} rounded-pill px-2 py-0 flex-shrink-0" style="font-size: 0.64rem;">{{ $row['type_label'] }}</span>
                                    <span class="fw-bold text-body text-truncate" style="font-size: 0.82rem;">{{ $row['party'] }}</span>
                                </div>
                                <div class="text-secondary text-truncate" style="font-size: 0.71rem;">
                                    <span class="font-monospace">{{ $row['reference'] }}</span> · {{ optional($row['date'])->format('d M, H:i') }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <span class="fw-bold m3-tabular-nums {{ $row['direction'] === 'in' ? 'text-success' : 'text-danger' }}" style="font-size: 0.8rem;">
                                    {{ $row['direction'] === 'in' ? '+' : '-' }}{{ $fmtRp($row['amount']) }}
                                </span>
                                <i class="fas fa-chevron-down m3-compact-chevron"></i>
                            </div>
                        </button>
                    </div>
                    <div class="collapse" id="adminLedgerCollapse-{{ $loop->index }}" data-bs-parent="#adminFinanceLedgerAccordion">
                        <div class="m3-collapsible-body small text-secondary">
                            <div class="text-body mb-1">{{ $row['detail'] ?: '-' }}</div>
                            <div><i class="far fa-clock me-1"></i>{{ optional($row['date'])->format('d M Y, H:i') }}</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 text-secondary small">{{ __('Belum ada transaksi finansial pada periode yang dipilih.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
