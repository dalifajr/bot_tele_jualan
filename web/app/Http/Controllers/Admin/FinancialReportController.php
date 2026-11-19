<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(protected FinancialReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $allowedTabs = ['pnl', 'liabilities', 'performance', 'ledger'];
        $activeTab = $request->query('tab', 'pnl');
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'pnl';
        }

        $period = $this->reportService->resolvePeriod(
            $request->query('preset'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        $report = $this->reportService->buildReport($period['start'], $period['end']);

        return view('admin.finance.reports', compact('activeTab', 'period', 'report'));
    }

    public function exportCsv(Request $request)
    {
        $period = $this->reportService->resolvePeriod(
            $request->query('preset'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        $report = $this->reportService->buildReport($period['start'], $period['end']);
        $filename = 'laporan-keuangan-pnl-' . $period['start']->format('Ymd') . '-' . $period['end']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($period, $report) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for clean Excel rendering
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['LAPORAN KEUANGAN & LABA RUGI (P&L)', config('app.name')]);
            fputcsv($out, ['Periode', $period['start']->format('Y-m-d') . ' s/d ' . $period['end']->format('Y-m-d')]);
            fputcsv($out, ['Dicetak Pada', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, []);

            fputcsv($out, ['=== RINGKASAN LABA RUGI (P&L) ===', 'Nominal (IDR)']);
            $pnl = $report['pnl'];
            fputcsv($out, ['Gross Merchandise Value (GMV Bersih Setelah Kupon)', $pnl['gross_gmv']]);
            fputcsv($out, ['Penjualan Produk Toko Utama (Admin)', $pnl['admin_product_revenue']]);
            fputcsv($out, ['Penjualan Produk Mitra Seller (GMV Seller)', $pnl['seller_gross_sales']]);
            fputcsv($out, ['Pendapatan Komisi Platform dari Seller', $pnl['platform_seller_commission']]);
            fputcsv($out, ['Hak Bersih Mitra Seller', $pnl['seller_net_earnings']]);
            fputcsv($out, ['Pendapatan Fee Rekber (Escrow)', $pnl['escrow_fee_revenue']]);
            fputcsv($out, ['Pendapatan Kode Unik Pembayaran', $pnl['unique_code_revenue']]);
            fputcsv($out, ['Total Diskon Kupon Terpakai', $pnl['coupon_discounts']]);
            fputcsv($out, ['Beban Refund / Garansi Dompet', -$pnl['wallet_refund_total']]);
            fputcsv($out, ['ESTIMASI PENDAPATAN BERSIH PLATFORM (NET REVENUE)', $pnl['net_platform_revenue']]);
            fputcsv($out, ['Volume Transaksi via Reseller API (H2H)', $pnl['reseller_api_revenue']]);
            fputcsv($out, []);

            fputcsv($out, ['=== NERACA KEWAJIBAN KAS (LIABILITAS SAAT INI) ===', 'Nominal (IDR)']);
            $liab = $report['liabilities'];
            fputcsv($out, ['Kewajiban Saldo Dompet Pelanggan', $liab['customer_wallet_liability']]);
            fputcsv($out, ['Kewajiban Saldo Siap Tarik Seller', $liab['seller_available_payable']]);
            fputcsv($out, ['Dana Garansi Tertahan (Held Funds)', $liab['held_funds_liability']]);
            fputcsv($out, ['Dana Rekber Aktif Ditahan', $liab['active_escrow_liability']]);
            fputcsv($out, ['TOTAL KEWAJIBAN KAS TERSIMPAN', $liab['total_liabilities']]);
            fputcsv($out, []);

            fputcsv($out, ['=== TREN HARIAN ===']);
            fputcsv($out, ['Tanggal', 'Pesanan Selesai', 'GMV (IDR)', 'Pendapatan Platform (IDR)', 'Top-Up Masuk (IDR)', 'Payout Keluar (IDR)']);
            foreach ($report['daily_series'] as $day) {
                fputcsv($out, [
                    $day['date'],
                    $day['orders_count'],
                    $day['gmv'],
                    $day['platform_revenue'],
                    $day['topup_in'],
                    $day['payout_out'],
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['=== BUKU BESAR TRANSAKSI (LEDGER) ===']);
            fputcsv($out, ['Waktu', 'Kategori', 'Arah', 'Referensi', 'Pihak', 'Keterangan', 'Nominal (IDR)', 'Sumber']);
            foreach ($report['ledger'] as $row) {
                fputcsv($out, [
                    optional($row['date'])->format('Y-m-d H:i:s'),
                    $row['type_label'],
                    $row['direction'] === 'in' ? 'MASUK' : 'KELUAR',
                    $row['reference'],
                    $row['party'],
                    $row['detail'],
                    $row['amount'],
                    $row['source'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $period = $this->reportService->resolvePeriod(
            $request->query('preset'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        $report = $this->reportService->buildReport($period['start'], $period['end']);
        $filename = 'laporan-keuangan-pnl-' . $period['start']->format('Ymd') . '-' . $period['end']->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('admin.finance.pdf_report', compact('period', 'report'))
            ->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
