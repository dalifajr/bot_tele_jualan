<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Laporan Penjualan') }} - {{ $product->name }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #2b2d42;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
        }
        .title {
            font-size: 16px;
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
            padding: 7px 10px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            width: 25%;
        }
        .info-value {
            color: #212529;
            width: 25%;
        }
        .summary-box {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .summary-card {
            width: 32%;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .summary-card-primary {
            border-left: 4px solid #0d6efd;
        }
        .summary-card-warning {
            border-left: 4px solid #ffc107;
        }
        .summary-card-success {
            border-left: 4px solid #198754;
        }
        .summary-title {
            font-size: 9px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #212529;
        }
        .summary-subtext {
            font-size: 8.5px;
            color: #8c98a4;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 7px 10px;
            font-size: 10px;
        }
        .data-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            font-size: 8.5px;
            color: #6c757d;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="title">{{ __('Laporan Penjualan & Pendapatan') }}</div>
                    <div class="subtitle">{{ __('Katalog Produk Digital') }} • {{ config('app.name', 'Bot Tele Jualan') }}</div>
                </td>
                <td class="text-right">
                    <div style="font-weight: bold; color: #495057;">{{ __('TANGGAL DICETAK') }}</div>
                    <div class="subtitle">{{ now()->translatedFormat('d M Y H:i:s') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Informasi Produk & Seller --}}
    <table class="info-table">
        <tr>
            <td class="info-label">{{ __('Nama Produk:') }}</td>
            <td class="info-value"><strong>#{{ $product->id }} {{ $product->name }}</strong></td>
            <td class="info-label">{{ __('Pemilik / Seller:') }}</td>
            <td class="info-value"><strong>{{ $sellerName }}</strong> ({{ $sellerRole }})</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('Harga Satuan:') }}</td>
            <td class="info-value">{{ $product->formatted_price }}</td>
            <td class="info-label">{{ __('Potongan Komisi:') }}</td>
            <td class="info-value">{{ $platformFeePercent }}% {{ __('per transaksi') }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('Periode Laporan:') }}</td>
            <td class="info-value"><strong>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</strong></td>
            <td class="info-label">{{ __('Tipe Produk:') }}</td>
            <td class="info-value">
                @if($product->is_vpn)
                    VPN ({{ strtoupper($product->vpn_protocol) }})
                @else
                    Digital Account / Key
                @endif
            </td>
        </tr>
    </table>

    {{-- Financial Summary Cards --}}
    <table class="summary-box">
        <tr>
            <td class="summary-card summary-card-primary">
                <div class="summary-title">{{ __('Total Penjualan (Gross)') }}</div>
                <div class="summary-value" style="color: #0d6efd;">Rp {{ number_format($totalGrossRevenue, 0, ',', '.') }}</div>
                <div class="summary-subtext">{{ $totalSoldUnits }} {{ __('Unit Terjual') }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="summary-card summary-card-warning">
                <div class="summary-title">{{ __('Komisi Platform') }} ({{ $platformFeePercent }}%)</div>
                <div class="summary-value" style="color: #d97706;">Rp {{ number_format($platformCommission, 0, ',', '.') }}</div>
                <div class="summary-subtext">{{ __('Hak Platform') }}</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="summary-card summary-card-success">
                <div class="summary-title">{{ __('Pendapatan Bersih (Net)') }}</div>
                <div class="summary-value" style="color: #198754;">Rp {{ number_format($totalNetEarnings, 0, ',', '.') }}</div>
                <div class="summary-subtext">{{ __('Hasil Akhir Seller / Admin') }}</div>
            </td>
        </tr>
    </table>

    {{-- Detail Rincian Transaksi --}}
    <div style="font-weight: bold; font-size: 11px; margin-bottom: 6px; color: #0d6efd;">
        {{ __('RINCIAN TRANSAKSI PENJUALAN (:count UNIT)', ['count' => $totalSoldUnits]) }}
    </div>

    @if($stockUnits->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 15%;">{{ __('ID Order') }}</th>
                <th style="width: 22%;">{{ __('Tanggal Terjual') }}</th>
                <th style="width: 23%;">{{ __('Pembeli') }}</th>
                <th style="width: 20%;">{{ __('Uploader Stok') }}</th>
                <th class="text-right" style="width: 15%;">{{ __('Harga') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockUnits as $index => $unit)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <strong>#{{ $unit->sold_order_id ?? '-' }}</strong>
                </td>
                <td>{{ $unit->order && $unit->order->created_at ? $unit->order->created_at->format('d M Y H:i') : ($unit->created_at ? $unit->created_at->format('d M Y H:i') : '-') }}</td>
                <td>{{ $unit->order && $unit->order->customer ? ($unit->order->customer->full_name ?? $unit->order->customer->username) : __('Pelanggan') }}</td>
                <td>{{ $unit->uploader ? ($unit->uploader->full_name ?? $unit->uploader->username) : ($unit->seller ? ($unit->seller->full_name ?? $unit->seller->username) : __('Admin Utama')) }}</td>
                <td class="text-right">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="text-align: center; padding: 25px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 6px; color: #6c757d; font-style: italic;">
        {{ __('Tidak terdapat transaksi penjualan produk pada periode tanggal yang dipilih.') }}
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>{{ __('Laporan ini di-generate secara otomatis oleh sistem :app.', ['app' => config('app.name', 'Bot Tele Jualan')]) }}</td>
                <td class="text-right">{{ __('Halaman 1 dari 1') }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
