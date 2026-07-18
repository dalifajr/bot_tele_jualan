@extends('layouts.app')

@section('title', 'Gmail Live Checker')
@section('page_subtitle', 'Tool')

@push('styles')
<style>
    .checker-card {
        border-radius: 16px;
        border: none;
        overflow: hidden;
    }
    .action-bar-card {
        border-radius: 16px;
        border: none;
        overflow: visible !important;
    }
    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .result-row {
        animation: fadeInRow 0.3s ease-out;
    }
    @keyframes fadeInRow {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .counter-card {
        border-radius: 14px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    .counter-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }
    .counter-card .counter-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    .log-container {
        max-height: 450px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-envelope me-2"></i>{{ __('Gmail Live Checker') }}</h4>
        <p class="text-muted mb-0">{{ __('Cek status akun Gmail secara massal (Live / Disabled) menggunakan alat eksternal GmailVer.com') }}</p>
    </div>
</div>

{{-- Section 1: Pilih Produk & Muat Akun --}}
<div class="card shadow-sm checker-card mb-3">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3">
            <i class="fas fa-database text-primary me-2"></i>{{ __('Muat Akun dari Stok Produk') }}
        </h6>
        <div class="row g-2 align-items-end">
            <div class="col">
                <label class="form-label text-muted small fw-bold mb-1">{{ __('Pilih Produk') }}</label>
                <select id="stock-product-select" class="form-select">
                    <option value="">{{ __('-- Pilih Produk Gmail --') }}</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->stock_units_count }} stok)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btn-load-stock" onclick="loadStock()">
                    <i class="fas fa-sync-alt me-1"></i>{{ __('Muat Stok Akun') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Section 2: Pengecekan Eksternal & Proses --}}
<div class="row g-3 mb-3">
    {{-- Left: Email List & External Link --}}
    <div class="col-lg-6">
        <div class="card shadow-sm checker-card h-100">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-clipboard-list text-info me-2"></i>{{ __('1. Daftar Email Stok') }}
                </h6>
                <div class="flex-grow-1 mb-3">
                    <textarea id="clean-emails-textarea" class="form-control" rows="8" readonly 
                              placeholder="{{ __('Daftar email bersih akan muncul di sini setelah Anda memuat stok produk...') }}"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary rounded-pill flex-grow-1" onclick="copyEmails()">
                        <i class="fas fa-copy me-1"></i>{{ __('Salin Email') }}
                    </button>
                    <a href="https://www.gmailchecklive.com/" target="_blank" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-external-link-alt me-1"></i>{{ __('Buka Pengecek') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Paste Die Emails --}}
    <div class="col-lg-6">
        <div class="card shadow-sm checker-card h-100">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-paste text-danger me-2"></i>2. Proses Email Mati (Die)
                </h6>
                <div class="flex-grow-1 mb-3">
                    <textarea id="die-emails-textarea" class="form-control" rows="8" 
                              placeholder="{{ __('Tempelkan daftar akun Gmail yang berstatus DIE / DISABLED dari web pengecek ke sini...&#10;&#10;Contoh format (Bisa paste langsung):&#10;sjurokanda@gmail.com&#10;zafrangnwn@gmail.com') }}"></textarea>
                </div>
                <button type="button" class="btn btn-danger w-100 rounded-pill py-2 fw-bold" onclick="processDieEmails()">
                    <i class="fas fa-cogs me-2"></i>{{ __('Cocokkan & Proses Email Mati') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Section 3: Results Table & Actions --}}
<div id="results-section" class="d-none">
    {{-- Summary Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="card counter-card shadow-sm" style="border-color: rgba(13,110,253,0.2); background: rgba(13,110,253,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-primary" id="count-total">0</div>
                    <small class="text-primary fw-bold">{{ __('TOTAL AKUN') }}</small>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card counter-card shadow-sm" style="border-color: rgba(25,135,84,0.2); background: rgba(25,135,84,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-success" id="count-live">0</div>
                    <small class="text-success fw-bold">{{ __('✅ LIVE') }}</small>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card counter-card shadow-sm" style="border-color: rgba(220,53,69,0.2); background: rgba(220,53,69,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-danger" id="count-disabled">0</div>
                    <small class="text-danger fw-bold">{{ __('❌ DISABLED') }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Bar --}}
    <div class="card shadow-sm action-bar-card mb-3" id="action-bar" style="z-index: 1025;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark"><span id="action-selected-count">0</span> {{ __('akun dipilih') }}</span>
                    <span class="text-muted">|</span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>{{ __('Filter Status') }}
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="filterResults('all')">{{ __('Semua') }}</a></li>
                            <li><a class="dropdown-item text-success" href="#" onclick="filterResults('live')">{{ __('✅ Live') }}</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="filterResults('disabled')">{{ __('❌ Disabled') }}</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    {{-- Bulk Stock Update --}}
                    <button class="btn btn-sm btn-outline-info rounded-pill" onclick="showBulkStockModal()" id="btn-bulk-stock">
                        <i class="fas fa-exchange-alt me-1"></i>{{ __('Update Stok Masal') }}
                    </button>
                    {{-- Delete Selected --}}
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="showDeleteSelectedModal()" id="btn-delete-selected">
                        <i class="fas fa-trash-alt me-1"></i>{{ __('Hapus Stok Terpilih') }}
                    </button>
                    {{-- Delete Disabled --}}
                    <button class="btn btn-sm btn-danger rounded-pill" onclick="showDeleteDisabledModal()" id="btn-delete-disabled">
                        <i class="fas fa-trash me-1"></i>{{ __('Hapus Semua Akun Disabled') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Result Table --}}
    <div class="card shadow-sm checker-card">
        <div class="card-body p-0">
            <div class="log-container" id="log-container">
                <table class="table table-hover align-middle mb-0" id="result-table">
                    <thead class="sticky-top bg-white">
                        <tr class="text-secondary small border-bottom">
                            <th class="px-4 py-3 border-0" style="width:40px;">
                                <input type="checkbox" class="form-check-input" id="select-all-results" onchange="toggleAllResults()">
                            </th>
                            <th class="py-3 border-0" style="width: 50px;">#</th>
                            <th class="py-3 border-0">{{ __('Email') }}</th>
                            <th class="py-3 border-0" style="width: 150px;">{{ __('Status') }}</th>
                            <th class="py-3 border-0">{{ __('Keterangan') }}</th>
                        </tr>
                    </thead>
                    <tbody id="result-tbody">
                        {{-- Populated via JavaScript --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    let stockMap = {}; // Maps email -> stock_id
    let resultsData = []; // Processed results
    let counts = { total: 0, live: 0, disabled: 0 };

    // ─── Load Stock ───
    function loadStock() {
        const productId = document.getElementById('stock-product-select').value;
        if (!productId) {
            Swal.fire({ icon: 'warning', title: 'Pilih Produk', text: 'Silakan pilih produk terlebih dahulu.', confirmButtonColor: '#0d6efd' });
            return;
        }

        const btn = document.getElementById('btn-load-stock');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';

        fetch('{{ route("admin.tools.gmail-checker.load-stock") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.emails) {
                stockMap = {};
                const emailsText = data.emails.map(item => {
                    stockMap[item.email] = item.stock_id;
                    return item.email;
                }).join('\n');

                document.getElementById('clean-emails-textarea').value = emailsText;
                
                // Hide results section on reload
                document.getElementById('results-section').classList.add('d-none');

                Swal.fire({ icon: 'success', title: 'Berhasil!', text: `${data.count} akun berhasil dimuat dari stok.`, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal memuat data dari server.' });
            }
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Muat Stok Akun';
        });
    }

    // ─── Copy Emails ───
    function copyEmails() {
        const textarea = document.getElementById('clean-emails-textarea');
        if (!textarea.value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Kosong', text: 'Tidak ada email untuk disalin.' });
            return;
        }
        
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        
        try {
            navigator.clipboard.writeText(textarea.value).then(() => {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Daftar email berhasil disalin.', timer: 1500, showConfirmButton: false });
            });
        } catch (err) {
            document.execCommand('copy');
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Daftar email berhasil disalin.', timer: 1500, showConfirmButton: false });
        }
    }

    // ─── Process Die Emails ───
    function processDieEmails() {
        const rawDie = document.getElementById('die-emails-textarea').value.trim();
        if (!rawDie) {
            Swal.fire({ icon: 'warning', title: 'Input Kosong', text: 'Masukkan hasil email mati (Die) terlebih dahulu.' });
            return;
        }

        const emails = rawDie.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g) || [];
        if (emails.length === 0) {
            Swal.fire({ icon: 'error', title: 'Format Salah', text: 'Tidak ada alamat email yang valid ditemukan pada teks input.' });
            return;
        }

        resultsData = [];
        counts = { total: 0, live: 0, disabled: 0 };
        const uniqueEmails = [...new Set(emails.map(e => e.toLowerCase()))];

        uniqueEmails.forEach(email => {
            const stockId = stockMap[email] || null;
            resultsData.push({
                email: email,
                status: 'disabled',
                stock_id: stockId
            });
            counts.disabled++;
            counts.total++;
        });

        document.getElementById('count-total').textContent = counts.total;
        document.getElementById('count-live').textContent = '0';
        document.getElementById('count-disabled').textContent = counts.disabled;

        renderResultsTable();
        document.getElementById('results-section').classList.remove('d-none');
        document.getElementById('action-selected-count').textContent = '0';
        document.getElementById('select-all-results').checked = false;

        document.getElementById('results-section').scrollIntoView({ behavior: 'smooth' });
    }
    
    function appendResultRow(r, index) {
        const tbody = document.getElementById('result-tbody');
        const statusConfig = {
            'live': { badge: 'bg-success', icon: '✅', label: 'LIVE' },
            'disabled': { badge: 'bg-danger', icon: '❌', label: 'DISABLED' }
        };
        const cfg = statusConfig[r.status] || { badge: 'bg-secondary', icon: '❓', label: 'UNKNOWN' };
        
        const row = document.createElement('tr');
        row.className = 'result-row';
        row.setAttribute('data-status', r.status);
        row.setAttribute('data-email', r.email);
        
        const detailText = r.stock_id 
            ? `<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Terhubung ke Stok (ID: ${r.stock_id})</span>`
            : `<span class="text-muted"><i class="fas fa-exclamation-circle me-1"></i>Manual (Non-Stok)</span>`;

        row.innerHTML = `
            <td class="px-4">
                <input type="checkbox" class="form-check-input result-cb" value="${r.email}" 
                       data-stock-id="${r.stock_id || ''}" ${r.stock_id ? '' : 'disabled'} onchange="updateActionCount()">
            </td>
            <td class="fw-bold text-muted">${index}</td>
            <td class="fw-medium">${r.email}</td>
            <td><span class="badge ${cfg.badge} status-badge rounded-pill px-3">${cfg.icon} ${cfg.label}</span></td>
            <td>${detailText}</td>
        `;
        tbody.appendChild(row);
        
        const hasDisabledStock = resultsData.some(res => res.status === 'disabled' && res.stock_id);
        document.getElementById('btn-delete-disabled').style.display = hasDisabledStock ? 'inline-block' : 'none';
        
        const container = document.getElementById('log-container');
        container.scrollTop = container.scrollHeight;
    }

    // ─── Render Results Table ───
    function renderResultsTable() {
        const tbody = document.getElementById('result-tbody');
        tbody.innerHTML = '';
        resultsData.forEach((r, i) => appendResultRow(r, i + 1));
        updateActionCount();
    }

    // ─── Checkbox Helpers ───
    function toggleAllResults() {
        const checked = document.getElementById('select-all-results').checked;
        document.querySelectorAll('.result-cb:not([disabled])').forEach(cb => {
            const row = cb.closest('tr');
            if (row.style.display !== 'none') {
                cb.checked = checked;
            }
        });
        updateActionCount();
    }

    function updateActionCount() {
        const checked = document.querySelectorAll('.result-cb:checked');
        document.getElementById('action-selected-count').textContent = checked.length;

        // Toggle bulk update and delete selected buttons
        const hasSelected = checked.length > 0;
        document.getElementById('btn-bulk-stock').disabled = !hasSelected;
        document.getElementById('btn-delete-selected').disabled = !hasSelected;
    }

    function filterResults(status) {
        const rows = document.querySelectorAll('#result-tbody tr');
        rows.forEach(row => {
            if (status === 'all' || row.getAttribute('data-status') === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                const cb = row.querySelector('.result-cb');
                if (cb) cb.checked = false;
            }
        });
        updateActionCount();
    }

    // ─── Actions ───
    function getSelectedStockIds() {
        const checked = document.querySelectorAll('.result-cb:checked');
        return Array.from(checked).map(cb => parseInt(cb.dataset.stockId)).filter(id => !isNaN(id));
    }

    function showBulkStockModal() {
        const stockIds = getSelectedStockIds();
        if (stockIds.length === 0) {
            Swal.fire({ icon: 'info', title: 'Pilih Akun', text: 'Pilih akun yang terhubung ke stok jualan terlebih dahulu.' });
            return;
        }

        Swal.fire({
            title: 'Update Status Stok Masal',
            html: `
                <p class="small text-muted mb-3">${stockIds.length} akun stok akan diupdate</p>
                <select id="swal-stock-status" class="form-select">
                    <option value="ready">{{ __('Ready') }}</option>
                    @if(Auth::user()->role === 'admin')
                    <option value="awaiting_benefits">{{ __('Awaiting Benefits') }}</option>
                    @endif
                    <option value="saved_for_verification">{{ __('Simpan Akun') }}</option>
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Update Status',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0d6efd',
            preConfirm: () => {
                return document.getElementById('swal-stock-status').value;
            }
        }).then(result => {
            if (result.isConfirmed) {
                sendBulkRequest('update_status', stockIds, result.value);
            }
        });
    }

    function showDeleteSelectedModal() {
        const stockIds = getSelectedStockIds();
        if (stockIds.length === 0) {
            Swal.fire({ icon: 'info', title: 'Pilih Akun', text: 'Pilih akun yang terhubung ke stok jualan terlebih dahulu.' });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Hapus Stok Terpilih?',
            html: `<p>{{ __('Anda akan menghapus') }} <strong>${stockIds.length}</strong> {{ __('stok akun terpilih.') }}</p><p class="text-danger small">{{ __('Tindakan ini tidak dapat dibatalkan!') }}</p>`,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus',
        }).then(result => {
            if (result.isConfirmed) {
                sendBulkRequest('delete', stockIds);
            }
        });
    }

    function showDeleteDisabledModal() {
        const disabledStockIds = resultsData
            .filter(r => r.status === 'disabled' && r.stock_id)
            .map(r => r.stock_id);

        if (disabledStockIds.length === 0) {
            Swal.fire({ icon: 'info', title: 'Tidak Ada Data', text: 'Tidak ada akun disabled yang terhubung dengan stok.' });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Hapus Semua Stok Akun Disabled?',
            html: `<p>{{ __('Anda akan menghapus') }} <strong>${disabledStockIds.length}</strong> {{ __('stok akun yang berstatus DISABLED secara otomatis.') }}</p><p class="text-danger small">{{ __('Tindakan ini tidak dapat dibatalkan!') }}</p>`,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus Semua Disabled',
        }).then(result => {
            if (result.isConfirmed) {
                sendBulkRequest('delete', disabledStockIds);
            }
        });
    }

    // Send Bulk Requests
    function sendBulkRequest(action, stockIds, status = null) {
        Swal.fire({
            title: 'Memproses...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("admin.tools.gmail-checker.bulk-action") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ action: action, stock_ids: stockIds, status: status })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false });
                
                // Remove processed/deleted stock ids from local mapping & resultsData
                if (action === 'delete') {
                    resultsData = resultsData.filter(r => !stockIds.includes(r.stock_id));
                    // Update counts
                    counts.total = resultsData.length;
                    counts.live = resultsData.filter(r => r.status === 'live').count; // wait, let's fix counts recalculation below
                    counts.live = resultsData.filter(r => r.status === 'live').length;
                    counts.disabled = resultsData.filter(r => r.status === 'disabled').length;
                    
                    document.getElementById('count-total').textContent = counts.total;
                    document.getElementById('count-live').textContent = counts.live;
                    document.getElementById('count-disabled').textContent = counts.disabled;

                    // Clean stockMap values
                    Object.keys(stockMap).forEach(key => {
                        if (stockIds.includes(stockMap[key])) {
                            delete stockMap[key];
                        }
                    });
                }
                
                renderResultsTable();
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message || 'Terjadi kesalahan saat memproses.' });
            }
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        });
    }
</script>
@endpush
