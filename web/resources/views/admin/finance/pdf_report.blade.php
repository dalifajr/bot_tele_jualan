<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan &amp; Laba Rugi (P&amp;L)</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 20px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 18px; margin: 0 0 4px 0; color: #111827; }
        .header .meta { font-size: 10px; color: #6b7280; }
        .section-title { font-size: 12px; font-weight: bold; color: #1d4ed8; margin: 16px 0 8px 0; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .highlight { background: #eff6ff; font-weight: bold; color: #1e40af; }
        .danger { color: #dc2626; }
        .success { color: #15803d; }
        .kpi-table td { width: 25%; background: #f9fafb; }
        .kpi-val { font-size: 14px; font-weight: bold; margin-top: 3px; }
        .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
@php
    $pnl = $report['pnl'];
    $cf = $report['cashflow'];
    $liab = $report['liabilities'];
    $fmtRp = fn ($n) => ($n < 0 ? '-Rp ' : 'Rp ') . number_format(abs((int) $n), 0, ',', '.');
@endphp

<div class="header">
    <h1>Laporan Keuangan &amp; Laba Rugi (P&amp;L) — {{ config('app.name') }}</h1>
    <div class="meta">
        Periode: <strong>{{ $period['start']->format('d M Y') }} – {{ $period['end']->format('d M Y') }}</strong> ({{ $period['label'] }})
        &nbsp;|&nbsp; Dicetak: {{ now()->format('d M Y H:i:s') }}
    </div>
</div>

<table class="kpi-table">
    <tr>
        <td>
            <div>Pendapatan Bersih Platform</div>
            <div class="kpi-val success">{{ $fmtRp($pnl['net_platform_revenue']) }}</div>
        </td>
        <td>
            <div>Total GMV Penjualan</div>
            <div class="kpi-val">{{ $fmtRp($pnl['gross_gmv']) }}</div>
        </td>
        <td>
            <div>Top-Up Masuk / Payout</div>
            <div class="kpi-val">{{ $fmtRp($cf['topup_confirmed_total']) }}</div>
        </td>
        <td>
            <div>Total Kewajiban Kas</div>
            <div class="kpi-val danger">{{ $fmtRp($liab['total_liabilities']) }}</div>
        </td>
    </tr>
</table>

<div class="section-title">1. Laporan Laba Rugi Operasional (P&amp;L)</div>
<table>
    <thead>
        <tr>
            <th>Komponen Keuangan</th>
            <th class="text-right">Nominal (IDR)</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Penjualan Produk Toko Utama (Admin)</td><td class="text-right">{{ $fmtRp($pnl['admin_product_revenue']) }}</td></tr>
        <tr><td>Komisi Platform dari Penjualan Mitra Seller</td><td class="text-right">{{ $fmtRp($pnl['platform_seller_commission']) }}</td></tr>
        <tr><td>Pendapatan Fee Rekber (Escrow) Selesai ({{ $cf['escrow_completed_count'] }} transaksi)</td><td class="text-right">{{ $fmtRp($pnl['escrow_fee_revenue']) }}</td></tr>
        <tr><td>Pendapatan Kode Unik Pembayaran</td><td class="text-right">{{ $fmtRp($pnl['unique_code_revenue']) }}</td></tr>
        <tr><td>Beban Refund / Garansi Dompet</td><td class="text-right danger">{{ $fmtRp(-$pnl['wallet_refund_total']) }}</td></tr>
        <tr class="highlight"><td>ESTIMASI LABA BERSIH PLATFORM (NET REVENUE)</td><td class="text-right">{{ $fmtRp($pnl['net_platform_revenue']) }}</td></tr>
        <tr><td>Memo: Total Diskon Kupon Terpakai</td><td class="text-right">{{ $fmtRp($pnl['coupon_discounts']) }}</td></tr>
        <tr><td>Memo: GMV Kotor Produk Seller / Hak Bersih Seller</td><td class="text-right">{{ $fmtRp($pnl['seller_gross_sales']) }} / {{ $fmtRp($pnl['seller_net_earnings']) }}</td></tr>
        <tr><td>Memo: Volume Transaksi Reseller API (H2H)</td><td class="text-right">{{ $fmtRp($pnl['reseller_api_revenue']) }}</td></tr>
    </tbody>
</table>

<div class="section-title">2. Neraca Kewajiban Kas Tersimpan (Liabilitas Saat Ini)</div>
<table>
    <thead>
        <tr>
            <th>Pos Kewajiban (Dana Titipan)</th>
            <th class="text-right">Nominal (IDR)</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Kewajiban Saldo Dompet Pelanggan</td><td class="text-right">{{ $fmtRp($liab['customer_wallet_liability']) }}</td></tr>
        <tr><td>Kewajiban Saldo Siap Tarik Mitra Seller</td><td class="text-right">{{ $fmtRp($liab['seller_available_payable']) }}</td></tr>
        <tr><td>Dana Garansi Tertahan (Held Funds)</td><td class="text-right">{{ $fmtRp($liab['held_funds_liability']) }}</td></tr>
        <tr><td>Dana Rekber Aktif Ditahan</td><td class="text-right">{{ $fmtRp($liab['active_escrow_liability']) }}</td></tr>
        <tr class="highlight"><td>TOTAL KEWAJIBAN KAS (LIABILITIES)</td><td class="text-right">{{ $fmtRp($liab['total_liabilities']) }}</td></tr>
    </tbody>
</table>

<div class="section-title">3. Top Produk Berdasarkan Kontribusi Profit</div>
<table>
    <thead>
        <tr>
            <th>Produk</th>
            <th>Pemilik</th>
            <th class="text-center">Unit</th>
            <th class="text-right">GMV</th>
            <th class="text-right">Profit Platform</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['top_products']->take(10) as $tp)
        <tr>
            <td>{{ $tp['name'] }}</td>
            <td>{{ $tp['owner_name'] }}</td>
            <td class="text-center">{{ $tp['units_sold'] }}</td>
            <td class="text-right">{{ $fmtRp($tp['gmv']) }}</td>
            <td class="text-right">{{ $fmtRp($tp['platform_revenue']) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center">Belum ada penjualan pada periode ini.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Dokumen ini dibuat otomatis oleh sistem {{ config('app.name') }}.
</div>
</body>
</html>
