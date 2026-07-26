<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan - {{ $product->name }}</title>
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
            padding: 6px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 6px 8px;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 4px;
        }
        .badge-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .badge-info {
            background-color: #cff4fc;
            color: #055160;
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
                    <div class="title">Laporan Penjualan & Pendapatan</div>
                    <div class="subtitle">Katalog Produk Digital • {{ config('app.name', 'Bot Tele Jualan') }}</div>
                </td>
                <td class="text-right">
                    <div style="font-weight: bold; color: #495057;">TANGGAL DICETAK</div>
                    <div class="subtitle">{{ now()->format('d M Y H:i:s') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Informasi Produk & Seller --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Produk:</td>
            <td class="info-value"><strong>#{{ $product->id }} {{ $product->name }}</strong></td>
            <td class="info-label">Pemilik / Seller:</td>
            <td class="info-value"><strong>{{ $sellerName }}</strong> ({{ $sellerRole }})</td>
        </tr>
        <tr>
            <td class="info-label">Harga Satuan:</td>
            <td class="info-value">{{ $product->formatted_price }}</td>
            <td class="info-label">Potongan Komisi:</td>
            <td class="info-value">{{ $platformFeePercent }}% per transaksi</td>
        </tr>
        <tr>
            <td class="info-label">Periode Laporan:</td>
            <td class="info-value"><strong>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</strong></td>
            <td class="info-label">Tipe Produk:</td>
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
                <div class="summary-title">Total Penjualan (Gross)</div>
                <div class="summary-value" style="color: #0d6efd;">Rp {{ number_format($totalGrossRevenue, 0, ',', '.') }}</div>
                <div class="summary-subtext">{{ $totalSoldUnits }} Unit Terjual</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="summary-card summary-card-warning">
                <div class="summary-title">Komisi Platform ({{ $platformFeePercent }}%)</div>
                <div class="summary-value" style="color: #d97706;">Rp {{ number_format($platformCommission, 0, ',', '.') }}</div>
                <div class="summary-subtext">Hak Platform</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="summary-card summary-card-success">
                <div class="summary-title">Pendapatan Bersih (Net)</div>
                <div class="summary-value" style="color: #198754;">Rp {{ number_format($totalNetEarnings, 0, ',', '.') }}</div>
                <div class="summary-subtext">Hasil Akhir Seller / Admin</div>
            </td>
        </tr>
    </table>

    {{-- Detail Rincian Transaksi --}}
    <div style="font-weight: bold; font-size: 11px; margin-bottom: 6px; color: #0d6efd;">
        RINCIAN TRANSAKSI PENJUALAN ({{ $totalSoldUnits }} UNIT)
    </div>

    @if($stockUnits->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 15%;">ID Order</th>
                <th style="width: 22%;">Tanggal Terjual</th>
                <th style="width: 23%;">Pembeli</th>
                <th style="width: 20%;">Uploader Stok</th>
                <th class="text-right" style="width: 15%;">Harga</th>
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
                <td>{{ $unit->order && $unit->order->customer ? ($unit->order->customer->full_name ?? $unit->order->customer->username) : 'Pelanggan' }}</td>
                <td>{{ $unit->uploader ? ($unit->uploader->full_name ?? $unit->uploader->username) : ($unit->seller ? ($unit->seller->full_name ?? $unit->seller->username) : 'Admin Utama') }}</td>
                <td class="text-right">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="text-align: center; padding: 25px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 6px; color: #6c757d; font-style: italic;">
        Tidak terdapat transaksi penjualan produk pada periode <strong>{{ $startDate->format('d M Y') }}</strong> s/d <strong>{{ $endDate->format('d M Y') }}</strong>.
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>Laporan ini di-generate secara otomatis oleh sistem {{ config('app.name', 'Bot Tele Jualan') }}.</td>
                <td class="text-right">Halaman 1 dari 1</td>
            </tr>
        </table>
    </div>

</body>
</html>
