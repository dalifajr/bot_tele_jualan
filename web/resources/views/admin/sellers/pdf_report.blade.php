<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Performa Seller - {{ $seller->full_name ?? $seller->username }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #2b2d42;
            font-size: 10.5px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
        }
        .title {
            font-size: 15px;
            font-weight: bold;
            color: #0d6efd;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 10px;
            color: #6c757d;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        .info-table td {
            padding: 6px 10px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            width: 20%;
        }
        .info-value {
            color: #212529;
            width: 30%;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .summary-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .summary-card-primary { border-left: 4px solid #0d6efd; }
        .summary-card-warning { border-left: 4px solid #ffc107; }
        .summary-card-success { border-left: 4px solid #198754; }
        .summary-card-info    { border-left: 4px solid #0dcaf0; }
        
        .summary-title {
            font-size: 8.5px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .summary-value {
            font-size: 13px;
            font-weight: bold;
            color: #212529;
        }
        .summary-subtext {
            font-size: 8px;
            color: #6c757d;
            margin-top: 2px;
        }
        .section-header {
            font-size: 12px;
            font-weight: bold;
            color: #0d6efd;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            font-size: 9.5px;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e9ecef;
            font-size: 9.5px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 4px;
            text-align: center;
        }
        .badge-success { background-color: #d1e7dd; color: #0f5132; }
        .badge-warning { background-color: #fff3cd; color: #664d03; }
        .badge-info    { background-color: #cff4fc; color: #055160; }
        .badge-secondary { background-color: #e2e3e5; color: #41464b; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 8.5px;
            color: #8c98a4;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="title">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
                    <div class="subtitle">LAPORAN PERFORMA SELLER & KONTRIBUSI PLATFORM</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 10px; font-weight: bold;">Dicetak Pada:</div>
                    <div class="subtitle">{{ now()->translatedFormat('d F Y H:i') }} WIB</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Seller Info Table --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Seller</td>
            <td class="info-value">{{ $seller->full_name ?? $seller->username }}</td>
            <td class="info-label">Periode Laporan</td>
            <td class="info-value">{{ $startDate->format('d/m/Y') }} — {{ $endDate->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Username / Telegram</td>
            <td class="info-value">@ {{ $seller->username ?? '-' }} (ID: {{ $seller->telegram_id ?? '-' }})</td>
            <td class="info-label">Potongan Komisi Platform</td>
            <td class="info-value"><span class="badge badge-info">{{ $platformFeePercent }}%</span></td>
        </tr>
        <tr>
            <td class="info-label">Email Seller</td>
            <td class="info-value">{{ $seller->email ?? '-' }}</td>
            <td class="info-label">Kontribusi Platform</td>
            <td class="info-value"><span class="badge badge-success">{{ $contributionPercentage }}% dari Total Omzet</span></td>
        </tr>
    </table>

    {{-- Financial Summary KPI Cards --}}
    <table class="summary-grid">
        <tr>
            <td class="summary-card summary-card-primary">
                <div class="summary-title">Pendapatan Kotor</div>
                <div class="summary-value">Rp {{ number_format($totalGrossRevenue, 0, ',', '.') }}</div>
                <div class="summary-subtext">{{ $totalSoldUnits }} Unit Terjual</div>
            </td>
            <td class="summary-card summary-card-warning">
                <div class="summary-title">Komisi Platform</div>
                <div class="summary-value">Rp {{ number_format($totalPlatformCommission, 0, ',', '.') }}</div>
                <div class="summary-subtext">Skema {{ $platformFeePercent }}% Per Unit</div>
            </td>
            <td class="summary-card summary-card-success">
                <div class="summary-title">Pendapatan Bersih</div>
                <div class="summary-value">Rp {{ number_format($totalNetEarnings, 0, ',', '.') }}</div>
                <div class="summary-subtext">Hak Seller (Net)</div>
            </td>
            <td class="summary-card summary-card-info">
                <div class="summary-title">Stok & Garansi</div>
                <div class="summary-value">{{ $readyStockCount }} Ready</div>
                <div class="summary-subtext">Refund Rate: {{ $refundRatePercent }}% ({{ $refundedStockCount }} unit)</div>
            </td>
        </tr>
    </table>

    {{-- Top Performing Products --}}
    @if($topProducts->count() > 0)
    <div class="section-header">PRODUK TERLARIS SELLER (TOP PERFORMING)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Nama Produk</th>
                <th style="width: 15%; text-align: right;">Harga Unit</th>
                <th style="width: 10%; text-align: center;">Terjual</th>
                <th style="width: 15%; text-align: right;">Omzet Kotor</th>
                <th style="width: 15%; text-align: right;">Bersih Seller</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $index => $prod)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-bold">{{ $prod['name'] }}</td>
                <td class="text-right">Rp {{ number_format($prod['price'], 0, ',', '.') }}</td>
                <td class="text-center"><span class="badge badge-info">{{ $prod['qty'] }} unit</span></td>
                <td class="text-right text-bold">Rp {{ number_format($prod['gross'], 0, ',', '.') }}</td>
                <td class="text-right text-bold" style="color: #198754;">Rp {{ number_format($prod['net'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Detailed Sold Stock Log --}}
    <div class="section-header">LOG DETAIL PRODUK TERJUAL (TOTAL {{ $totalSoldUnits }} ITEM)</div>
    @if($soldStockUnits->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 12%;">No. Order</th>
                <th style="width: 27%;">Produk</th>
                <th style="width: 16%;">Pembeli</th>
                <th style="width: 13%; text-align: right;">Harga</th>
                <th style="width: 13%; text-align: right;">Bersih Seller</th>
            </tr>
        </thead>
        <tbody>
            @foreach($soldStockUnits as $index => $unit)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $unit->order->created_at ? $unit->order->created_at->format('d/m/Y H:i') : $unit->created_at->format('d/m/Y H:i') }}</td>
                <td class="text-bold">#{{ $unit->order->id ?? $unit->sold_order_id ?? '-' }}</td>
                <td>{{ $unit->product->name ?? 'Produk Dihapus' }}</td>
                <td>{{ $unit->order->customer->full_name ?? $unit->order->customer->username ?? 'Guest/Bot' }}</td>
                <td class="text-right">Rp {{ number_format($unit->unit_price, 0, ',', '.') }}</td>
                <td class="text-right text-bold" style="color: #198754;">Rp {{ number_format($unit->unit_net, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="5" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-right">Rp {{ number_format($totalGrossRevenue, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #198754;">Rp {{ number_format($totalNetEarnings, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <div style="text-align: center; padding: 20px; color: #6c757d; background: #f8f9fa; border-radius: 6px;">
        Tidak ada transaksi penjualan untuk seller ini pada periode tanggal yang dipilih.
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Dokumen ini dihasilkan secara otomatis oleh Sistem {{ config('app.name', 'Dzulfikrialifajri Store') }} — Laporan Performa Seller.
    </div>

</body>
</html>
