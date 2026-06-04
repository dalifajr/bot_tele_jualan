@extends('layouts.app')

@section('title', 'GitHub Live Checker')
@section('page_subtitle', 'Tool')

@push('styles')
<style>
    .checker-card {
        border-radius: 16px;
        border: none;
        overflow: hidden;
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
    .progress-wrapper {
        background: var(--bs-body-bg);
        border-radius: 10px;
        padding: 2px;
    }
    .progress-wrapper .progress {
        height: 10px;
        border-radius: 8px;
    }
    .cookie-status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        animation: pulse 2s infinite;
    }
    .cookie-status-indicator.valid { background: #198754; }
    .cookie-status-indicator.invalid { background: #dc3545; }
    .cookie-status-indicator.pending { background: #6c757d; }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .log-container {
        max-height: 400px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    .delay-slider-label {
        font-variant-numeric: tabular-nums;
    }
    #result-table tbody tr:last-child {
        background-color: rgba(var(--bs-primary-rgb), 0.04);
    }
    .stock-action-row {
        background: rgba(var(--bs-warning-rgb), 0.05);
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fab fa-github me-2"></i>GitHub Live Checker</h4>
        <p class="text-muted mb-0">Cek status akun GitHub secara massal — Approved (PRO), Revoked, atau Suspended</p>
    </div>
</div>

{{-- Section 1: Cookie Session --}}
<div class="card shadow-sm checker-card mb-3">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold mb-0">
                <i class="fas fa-key text-warning me-2"></i>Cookie Session GitHub
            </h6>
            <div class="d-flex align-items-center gap-2" id="cookie-status-area">
                <span class="cookie-status-indicator {{ $cookieValid ? 'valid' : 'pending' }}"></span>
                <span class="small fw-medium" id="cookie-status-text">
                    @if($cookieValid)
                        <span class="text-success">Login sebagai: <strong>{{ $cookieUser }}</strong></span>
                    @else
                        <span class="text-muted">Belum divalidasi</span>
                    @endif
                </span>
            </div>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col">
                <label class="form-label text-muted small fw-bold mb-1">Cookie Value <code class="text-muted">(user_session / _gh_sess)</code></label>
                <input type="text" id="github-cookie-input" class="form-control"
                       placeholder="Paste cookie value dari browser..." value="{{ $cookieValid ? '••••••••••••••••••••••••••' : '' }}">
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btn-validate-cookie" onclick="validateCookie()">
                    <i class="fas fa-check-circle me-1"></i>Validasi
                </button>
            </div>
        </div>
        <div class="form-text mt-2">
            <i class="fas fa-info-circle me-1"></i>
            Buka <strong>github.com</strong> → Login → DevTools (F12) → Application → Cookies → Salin value <code>user_session</code>. 
            Gabungkan menjadi format: <code>user_session=NILAI_COOKIE</code>
        </div>
    </div>
</div>

{{-- Section 2: Input Akun & Config --}}
<div class="row g-3 mb-3">
    {{-- Left: Input Accounts --}}
    <div class="col-lg-8">
        <div class="card shadow-sm checker-card h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-users text-primary me-2"></i>Daftar Akun untuk Dicek
                </h6>

                {{-- Tabs --}}
                <ul class="nav nav-tabs mb-3" id="inputTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-manual" type="button">
                            <i class="fas fa-keyboard me-1"></i>Manual Input
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stock" type="button">
                            <i class="fas fa-database me-1"></i>Dari Stok Akun
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-file" type="button">
                            <i class="fas fa-file-upload me-1"></i>Upload File
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Tab 1: Manual Input --}}
                    <div class="tab-pane fade show active" id="tab-manual">
                        <textarea id="manual-usernames" class="form-control" rows="8"
                            placeholder="Masukkan daftar username (satu per baris)&#10;&#10;Format yang didukung:&#10;username1&#10;username2&#10;&#10;Atau format lengkap:&#10;Username: username1&#10;Password: pass&#10;F2A: token&#10;&#10;Atau format singkat:&#10;user:pass:2fa"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Username akan di-parse otomatis dari berbagai format</small>
                            <small class="text-primary fw-bold" id="parsed-count-manual">0 username</small>
                        </div>
                    </div>

                    {{-- Tab 2: From Stock --}}
                    <div class="tab-pane fade" id="tab-stock">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Pilih Produk</label>
                            <select id="stock-product-select" class="form-select" onchange="loadStockUsernames()">
                                <option value="">-- Pilih Produk --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->stock_units_count }} stok)</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="stock-usernames-area" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="checkbox" id="select-all-stock" class="form-check-input" onchange="toggleAllStock()">
                                    <label for="select-all-stock" class="form-check-label small fw-bold">Pilih Semua</label>
                                </div>
                                <small class="text-primary fw-bold"><span id="stock-selected-count">0</span> / <span id="stock-total-count">0</span> dipilih</small>
                            </div>
                            <div id="stock-list" style="max-height: 250px; overflow-y: auto;" class="border rounded-3 p-2">
                                {{-- Populated via JS --}}
                            </div>
                        </div>
                        <div id="stock-loading" class="text-center py-4 d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            <span class="text-muted">Memuat stok akun...</span>
                        </div>
                    </div>

                    {{-- Tab 3: File Upload --}}
                    <div class="tab-pane fade" id="tab-file">
                        <div class="border rounded-3 p-4 text-center bg-light">
                            <i class="fas fa-cloud-upload-alt text-primary mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted small mb-2">Upload file <code>.txt</code> berisi daftar username</p>
                            <input type="file" id="file-upload" accept=".txt" class="form-control form-control-sm mx-auto" style="max-width: 300px;" onchange="handleFileUpload(this)">
                        </div>
                        <div id="file-preview" class="mt-2 d-none">
                            <small class="text-muted">Preview:</small>
                            <textarea id="file-content" class="form-control mt-1" rows="5" readonly></textarea>
                            <small class="text-primary fw-bold mt-1 d-block" id="parsed-count-file">0 username</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Config & Start --}}
    <div class="col-lg-4">
        <div class="card shadow-sm checker-card h-100">
            <div class="card-body p-4 d-flex flex-column">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-cog text-secondary me-2"></i>Konfigurasi
                </h6>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">
                        Delay Antar Request: <span class="delay-slider-label text-primary" id="delay-value">2</span> detik
                    </label>
                    <input type="range" class="form-range" id="delay-slider" min="1" max="10" value="2" step="1"
                           oninput="document.getElementById('delay-value').textContent = this.value">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">1s (cepat)</small>
                        <small class="text-muted">10s (aman)</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Mode Pengecekan</label>
                    <div class="bg-light rounded-3 p-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="fas fa-search text-info mt-1"></i>
                            <small>Step 1: Cari username di GitHub Search</small>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-id-badge text-success mt-1"></i>
                            <small>Step 2: Buka profil & cek badge PRO</small>
                        </div>
                    </div>
                </div>

                <div class="mt-auto pt-3">
                    <button type="button" class="btn btn-success w-100 rounded-pill py-2 fw-bold" id="btn-start-check" onclick="startChecking()">
                        <i class="fas fa-play me-2"></i>Mulai Pengecekan
                    </button>
                    <button type="button" class="btn btn-danger w-100 rounded-pill py-2 fw-bold d-none" id="btn-stop-check" onclick="stopChecking()">
                        <i class="fas fa-stop me-2"></i>Hentikan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Section 3: Results --}}
<div id="results-section" class="d-none">
    {{-- Progress --}}
    <div class="card shadow-sm checker-card mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">
                    <i class="fas fa-tasks text-info me-2"></i>Progress
                    <span class="badge bg-info-subtle text-info ms-2" id="progress-status">Running</span>
                </h6>
                <span class="fw-bold" id="progress-text">0 / 0</span>
            </div>
            <div class="progress-wrapper">
                <div class="progress">
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" id="progress-bar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card counter-card shadow-sm" style="border-color: rgba(25,135,84,0.2); background: rgba(25,135,84,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-success" id="count-approved">0</div>
                    <small class="text-success fw-bold">✅ APPROVED</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card counter-card shadow-sm" style="border-color: rgba(255,193,7,0.3); background: rgba(255,193,7,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-warning" id="count-not-approved">0</div>
                    <small class="text-warning fw-bold">⚠️ REVOKED</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card counter-card shadow-sm" style="border-color: rgba(220,53,69,0.2); background: rgba(220,53,69,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-danger" id="count-suspended">0</div>
                    <small class="text-danger fw-bold">❌ SUSPENDED</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card counter-card shadow-sm" style="border-color: rgba(108,117,125,0.2); background: rgba(108,117,125,0.03);">
                <div class="card-body text-center py-3">
                    <div class="counter-value text-secondary" id="count-error">0</div>
                    <small class="text-secondary fw-bold">🔄 ERROR</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Bar --}}
    <div class="card shadow-sm checker-card mb-3" id="action-bar" style="display: none;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark"><span id="action-selected-count">0</span> akun dipilih</span>
                    <span class="text-muted">|</span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter Status
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="filterResults('all')">Semua</a></li>
                            <li><a class="dropdown-item text-success" href="#" onclick="filterResults('approved')">✅ Approved</a></li>
                            <li><a class="dropdown-item text-warning" href="#" onclick="filterResults('not_approved')">⚠️ Revoked</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="filterResults('suspended')">❌ Suspended</a></li>
                            <li><a class="dropdown-item text-secondary" href="#" onclick="filterResults('error')">🔄 Error</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    {{-- Export --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary rounded-pill dropdown-toggle" data-bs-toggle="dropdown" id="btn-export">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="exportResults('all')"><i class="fas fa-file-csv me-2 text-success"></i>Semua Hasil</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportResults('approved')"><i class="fas fa-check-circle me-2 text-success"></i>Hanya Approved</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportResults('not_approved')"><i class="fas fa-exclamation-circle me-2 text-warning"></i>Hanya Revoked</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportResults('suspended')"><i class="fas fa-times-circle me-2 text-danger"></i>Hanya Suspended</a></li>
                        </ul>
                    </div>
                    {{-- Bulk Stock Update --}}
                    <button class="btn btn-sm btn-outline-info rounded-pill" onclick="showBulkStockModal()" id="btn-bulk-stock" style="display:none;">
                        <i class="fas fa-exchange-alt me-1"></i>Update Stok Masal
                    </button>
                    {{-- Delete Suspended --}}
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="showDeleteSuspendedModal()" id="btn-delete-suspended" style="display:none;">
                        <i class="fas fa-trash-alt me-1"></i>Hapus Stok Suspended
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
                            <th class="py-3 border-0">#</th>
                            <th class="py-3 border-0">Username</th>
                            <th class="py-3 border-0">Status</th>
                            <th class="py-3 border-0">Detail</th>
                            <th class="py-3 border-0">Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="result-tbody">
                        {{-- Populated via JavaScript --}}
                    </tbody>
                </table>
            </div>
            <div class="text-center py-4 text-muted" id="result-empty">
                <i class="fas fa-clipboard-list mb-2" style="font-size: 2rem;"></i>
                <p class="mb-0 small">Hasil pengecekan akan muncul di sini...</p>
            </div>
        </div>
    </div>
</div>

{{-- History Section --}}
@if($batches->count() > 0)
<div class="card shadow-sm checker-card mt-3">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-history text-muted me-2"></i>Riwayat Pengecekan Terakhir</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small">
                        <th class="border-0">Batch</th>
                        <th class="border-0">Tanggal</th>
                        <th class="border-0">Total</th>
                        <th class="border-0">Status</th>
                        <th class="border-0 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                    <tr>
                        <td class="fw-bold text-muted">#{{ $batch->id }}</td>
                        <td class="small">{{ $batch->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $batch->checked_count }} / {{ $batch->total_accounts }}</td>
                        <td>
                            @php
                                $batchBadge = match($batch->status) {
                                    'completed' => 'bg-success-subtle text-success',
                                    'running' => 'bg-info-subtle text-info',
                                    'stopped' => 'bg-warning-subtle text-warning',
                                    default => 'bg-secondary-subtle text-secondary',
                                };
                            @endphp
                            <span class="badge {{ $batchBadge }} rounded-pill px-3">{{ ucfirst($batch->status) }}</span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light rounded-pill px-3" onclick="loadBatchResults({{ $batch->id }})">
                                <i class="fas fa-eye me-1"></i>Lihat
                            </button>
                            <a href="{{ route('admin.tools.github-checker.export', $batch->id) }}?status=all" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="fas fa-download me-1"></i>CSV
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    let currentBatchId = null;
    let usernameQueue = [];
    let isRunning = false;
    let currentIndex = 0;
    let stockMap = {}; // Maps username -> stock_id for stock-based checks

    // Counters
    let counts = { approved: 0, not_approved: 0, suspended: 0, error: 0 };

    // ─── Cookie Validation ───
    function validateCookie() {
        const cookieInput = document.getElementById('github-cookie-input');
        const cookie = cookieInput.value.trim();
        if (!cookie || cookie.includes('•')) {
            Swal.fire({ icon: 'warning', title: 'Input Cookie', text: 'Masukkan cookie value yang valid.', confirmButtonColor: '#0d6efd' });
            return;
        }

        const btn = document.getElementById('btn-validate-cookie');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Validasi...';

        fetch('{{ route("admin.tools.github-checker.set-cookie") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ github_cookie: cookie })
        })
        .then(r => r.json())
        .then(data => {
            const statusArea = document.getElementById('cookie-status-area');
            const indicator = statusArea.querySelector('.cookie-status-indicator');
            const statusText = document.getElementById('cookie-status-text');

            if (data.valid) {
                indicator.className = 'cookie-status-indicator valid';
                statusText.innerHTML = '<span class="text-success">Login sebagai: <strong>' + data.logged_in_as + '</strong></span>';
                cookieInput.value = '••••••••••••••••••••••••••';
                Swal.fire({ icon: 'success', title: 'Cookie Valid!', text: data.message, timer: 2000, showConfirmButton: false });
            } else {
                indicator.className = 'cookie-status-indicator invalid';
                statusText.innerHTML = '<span class="text-danger">' + data.message + '</span>';
                Swal.fire({ icon: 'error', title: 'Cookie Invalid', text: data.message, confirmButtonColor: '#dc3545' });
            }
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memvalidasi cookie: ' + err.message });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Validasi';
        });
    }

    // ─── Load Stock Usernames ───
    function loadStockUsernames() {
        const productId = document.getElementById('stock-product-select').value;
        const area = document.getElementById('stock-usernames-area');
        const loading = document.getElementById('stock-loading');
        const list = document.getElementById('stock-list');

        if (!productId) {
            area.classList.add('d-none');
            return;
        }

        loading.classList.remove('d-none');
        area.classList.add('d-none');

        fetch('{{ route("admin.tools.github-checker.load-stock") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        })
        .then(r => r.json())
        .then(data => {
            loading.classList.add('d-none');

            if (data.usernames && data.usernames.length > 0) {
                stockMap = {};
                list.innerHTML = data.usernames.map((item, i) => {
                    stockMap[item.username] = item.stock_id;
                    return `<div class="form-check py-1 px-2">
                        <input type="checkbox" class="form-check-input stock-item-cb" value="${item.username}" 
                               data-stock-id="${item.stock_id}" id="stock-${i}" checked onchange="updateStockCount()">
                        <label class="form-check-label small" for="stock-${i}">
                            <strong>${item.username}</strong>
                            <span class="text-muted ms-1">(ID: ${item.stock_id})</span>
                        </label>
                    </div>`;
                }).join('');
                document.getElementById('stock-total-count').textContent = data.usernames.length;
                document.getElementById('stock-selected-count').textContent = data.usernames.length;
                document.getElementById('select-all-stock').checked = true;
                area.classList.remove('d-none');
            } else {
                list.innerHTML = '<div class="text-center text-muted small py-3">Tidak ada stok akun yang bisa di-parse username-nya.</div>';
                area.classList.remove('d-none');
            }
        })
        .catch(err => {
            loading.classList.add('d-none');
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        });
    }

    function toggleAllStock() {
        const checked = document.getElementById('select-all-stock').checked;
        document.querySelectorAll('.stock-item-cb').forEach(cb => cb.checked = checked);
        updateStockCount();
    }

    function updateStockCount() {
        const count = document.querySelectorAll('.stock-item-cb:checked').length;
        document.getElementById('stock-selected-count').textContent = count;
    }

    // ─── File Upload ───
    function handleFileUpload(input) {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            document.getElementById('file-content').value = content;
            document.getElementById('file-preview').classList.remove('d-none');

            // Count parsed usernames
            const lines = content.split('\n').filter(l => l.trim());
            document.getElementById('parsed-count-file').textContent = lines.length + ' baris';
        };
        reader.readAsText(file);
    }

    // ─── Collect Usernames ───
    function collectUsernames() {
        const activeTab = document.querySelector('#inputTabs .nav-link.active');
        const tabTarget = activeTab?.getAttribute('data-bs-target');

        if (tabTarget === '#tab-manual') {
            return document.getElementById('manual-usernames').value;
        } else if (tabTarget === '#tab-stock') {
            const selected = document.querySelectorAll('.stock-item-cb:checked');
            return Array.from(selected).map(cb => cb.value).join('\n');
        } else if (tabTarget === '#tab-file') {
            return document.getElementById('file-content')?.value || '';
        }
        return '';
    }

    // ─── Start Checking ───
    function startChecking() {
        const input = collectUsernames();
        if (!input.trim()) {
            Swal.fire({ icon: 'warning', title: 'Input Kosong', text: 'Masukkan daftar username terlebih dahulu.' });
            return;
        }

        const delay = parseInt(document.getElementById('delay-slider').value) || 2;

        document.getElementById('btn-start-check').classList.add('d-none');
        document.getElementById('btn-stop-check').classList.remove('d-none');
        document.getElementById('results-section').classList.remove('d-none');
        document.getElementById('result-empty').style.display = 'none';
        document.getElementById('result-tbody').innerHTML = '';
        counts = { approved: 0, not_approved: 0, suspended: 0, error: 0 };
        updateCounters();

        fetch('{{ route("admin.tools.github-checker.start") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ usernames: input, delay: delay })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                resetUI();
                return;
            }

            currentBatchId = data.batch_id;
            usernameQueue = data.usernames;
            currentIndex = 0;
            isRunning = true;

            updateProgress(0, usernameQueue.length);
            processNext(delay);
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            resetUI();
        });
    }

    // ─── Process Next Username ───
    function processNext(delay) {
        if (!isRunning || currentIndex >= usernameQueue.length) {
            finishChecking();
            return;
        }

        const username = usernameQueue[currentIndex];
        updateCurrentRow(username);

        fetch(`/admin/tools/github-checker/check-next/${currentBatchId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ username: username, stock_id: stockMap[username] || null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                appendResult(data.result, data.progress);
                counts[data.result.result]++;
                updateCounters();
                updateProgress(data.progress.checked, data.progress.total);
            }

            currentIndex++;

            if (isRunning && currentIndex < usernameQueue.length) {
                setTimeout(() => processNext(delay), delay * 1000);
            } else {
                finishChecking();
            }
        })
        .catch(err => {
            appendResult({ username: username, result: 'error', detail: 'Request error: ' + err.message }, null);
            counts.error++;
            updateCounters();
            currentIndex++;
            if (isRunning && currentIndex < usernameQueue.length) {
                setTimeout(() => processNext(delay), delay * 1000);
            } else {
                finishChecking();
            }
        });
    }

    // ─── Stop Checking ───
    function stopChecking() {
        isRunning = false;
        if (currentBatchId) {
            fetch(`/admin/tools/github-checker/stop/${currentBatchId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
            });
        }
        finishChecking('stopped');
    }

    // ─── Finish ───
    function finishChecking(status) {
        isRunning = false;
        document.getElementById('btn-start-check').classList.remove('d-none');
        document.getElementById('btn-stop-check').classList.add('d-none');

        const progressStatus = document.getElementById('progress-status');
        if (status === 'stopped') {
            progressStatus.textContent = 'Dihentikan';
            progressStatus.className = 'badge bg-warning-subtle text-warning ms-2';
        } else {
            progressStatus.textContent = 'Selesai';
            progressStatus.className = 'badge bg-success-subtle text-success ms-2';
        }

        // Remove the animated progress bar class
        document.getElementById('progress-bar').classList.remove('progress-bar-animated');

        // Show action bar
        const actionBar = document.getElementById('action-bar');
        actionBar.style.display = 'block';

        // Show bulk stock buttons if checking was from stock
        const activeTab = document.querySelector('#inputTabs .nav-link.active');
        if (activeTab?.getAttribute('data-bs-target') === '#tab-stock') {
            document.getElementById('btn-bulk-stock').style.display = 'inline-block';
            if (counts.suspended > 0) {
                document.getElementById('btn-delete-suspended').style.display = 'inline-block';
            }
        }

        if (status !== 'stopped') {
            Swal.fire({
                icon: 'success',
                title: 'Pengecekan Selesai!',
                html: `<div class="text-start">
                    <p class="mb-1">✅ Approved: <strong>${counts.approved}</strong></p>
                    <p class="mb-1">⚠️ Revoked: <strong>${counts.not_approved}</strong></p>
                    <p class="mb-1">❌ Suspended: <strong>${counts.suspended}</strong></p>
                    <p class="mb-0">🔄 Error: <strong>${counts.error}</strong></p>
                </div>`,
                confirmButtonColor: '#198754',
            });
        }
    }

    // ─── UI Helpers ───
    function updateProgress(checked, total) {
        const pct = total > 0 ? Math.round((checked / total) * 100) : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-text').textContent = checked + ' / ' + total;
    }

    function updateCounters() {
        document.getElementById('count-approved').textContent = counts.approved;
        document.getElementById('count-not-approved').textContent = counts.not_approved;
        document.getElementById('count-suspended').textContent = counts.suspended;
        document.getElementById('count-error').textContent = counts.error;
    }

    function updateCurrentRow(username) {
        // Show a "checking..." row
        const tbody = document.getElementById('result-tbody');
        const existingTemp = document.getElementById('temp-checking-row');
        if (existingTemp) existingTemp.remove();

        const row = document.createElement('tr');
        row.id = 'temp-checking-row';
        row.className = 'result-row';
        row.innerHTML = `
            <td class="px-4"><input type="checkbox" class="form-check-input" disabled></td>
            <td class="fw-bold text-muted">${currentIndex + 1}</td>
            <td class="fw-medium">${username}</td>
            <td><span class="spinner-border spinner-border-sm text-primary me-1"></span><span class="text-primary small">Checking...</span></td>
            <td class="text-muted small">Sedang memeriksa...</td>
            <td class="text-muted small">-</td>
        `;
        tbody.appendChild(row);

        // Auto scroll to bottom
        const container = document.getElementById('log-container');
        container.scrollTop = container.scrollHeight;
    }

    function appendResult(result, progress) {
        const tbody = document.getElementById('result-tbody');
        const existingTemp = document.getElementById('temp-checking-row');
        if (existingTemp) existingTemp.remove();

        const statusConfig = {
            'approved': { badge: 'bg-success', icon: '✅', label: 'APPROVED' },
            'not_approved': { badge: 'bg-warning text-dark', icon: '⚠️', label: 'REVOKED' },
            'suspended': { badge: 'bg-danger', icon: '❌', label: 'SUSPENDED' },
            'error': { badge: 'bg-secondary', icon: '🔄', label: 'ERROR' },
        };

        const cfg = statusConfig[result.result] || statusConfig.error;
        const now = new Date().toLocaleTimeString('id-ID');

        const row = document.createElement('tr');
        row.className = 'result-row';
        row.setAttribute('data-status', result.result);
        row.setAttribute('data-username', result.username);
        row.innerHTML = `
            <td class="px-4"><input type="checkbox" class="form-check-input result-cb" value="${result.username}" data-stock-id="${stockMap[result.username] || ''}" onchange="updateActionCount()"></td>
            <td class="fw-bold text-muted">${currentIndex + 1}</td>
            <td class="fw-medium">${result.username}</td>
            <td><span class="badge ${cfg.badge} status-badge rounded-pill px-3">${cfg.icon} ${cfg.label}</span></td>
            <td class="text-muted small" style="max-width:300px;">${result.detail}</td>
            <td class="text-muted small">${now}</td>
        `;
        tbody.appendChild(row);

        const container = document.getElementById('log-container');
        container.scrollTop = container.scrollHeight;
    }

    function resetUI() {
        document.getElementById('btn-start-check').classList.remove('d-none');
        document.getElementById('btn-stop-check').classList.add('d-none');
    }

    // ─── Results Actions ───
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
        const count = document.querySelectorAll('.result-cb:checked').length;
        document.getElementById('action-selected-count').textContent = count;
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

    function exportResults(status) {
        if (!currentBatchId) return;
        const url = `/admin/tools/github-checker/export/${currentBatchId}?status=${status}`;
        window.open(url, '_blank');
    }

    // ─── Load Historic Batch ───
    function loadBatchResults(batchId) {
        fetch(`/admin/tools/github-checker/progress/${batchId}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('results-section').classList.remove('d-none');
            document.getElementById('result-empty').style.display = 'none';
            document.getElementById('result-tbody').innerHTML = '';

            currentBatchId = batchId;
            counts = data.summary;
            updateCounters();
            updateProgress(data.batch.checked, data.batch.total);

            const progressStatus = document.getElementById('progress-status');
            progressStatus.textContent = data.batch.status === 'completed' ? 'Selesai' : ucfirst(data.batch.status);
            progressStatus.className = data.batch.status === 'completed' ? 'badge bg-success-subtle text-success ms-2' : 'badge bg-secondary-subtle text-secondary ms-2';
            document.getElementById('progress-bar').classList.remove('progress-bar-animated');

            const tbody = document.getElementById('result-tbody');
            const statusConfig = {
                'approved': { badge: 'bg-success', icon: '✅', label: 'APPROVED' },
                'not_approved': { badge: 'bg-warning text-dark', icon: '⚠️', label: 'REVOKED' },
                'suspended': { badge: 'bg-danger', icon: '❌', label: 'SUSPENDED' },
                'error': { badge: 'bg-secondary', icon: '🔄', label: 'ERROR' },
            };

            data.results.forEach((r, i) => {
                const cfg = statusConfig[r.result] || statusConfig.error;
                const row = document.createElement('tr');
                row.className = 'result-row';
                row.setAttribute('data-status', r.result);
                row.setAttribute('data-username', r.username);
                row.innerHTML = `
                    <td class="px-4"><input type="checkbox" class="form-check-input result-cb" value="${r.username}" data-stock-id="${r.stock_id || ''}" onchange="updateActionCount()"></td>
                    <td class="fw-bold text-muted">${i + 1}</td>
                    <td class="fw-medium">${r.username}</td>
                    <td><span class="badge ${cfg.badge} status-badge rounded-pill px-3">${cfg.icon} ${cfg.label}</span></td>
                    <td class="text-muted small">${r.detail}</td>
                    <td class="text-muted small">${r.checked_at}</td>
                `;
                tbody.appendChild(row);
            });

            // Show bulk action buttons if any items are associated with stock
            const hasStockItems = data.results.some(r => r.stock_id);
            if (hasStockItems) {
                document.getElementById('btn-bulk-stock').style.display = 'inline-block';
                const suspendedCount = data.results.filter(r => r.result === 'suspended' && r.stock_id).length;
                if (suspendedCount > 0) {
                    document.getElementById('btn-delete-suspended').style.display = 'inline-block';
                } else {
                    document.getElementById('btn-delete-suspended').style.display = 'none';
                }
            } else {
                document.getElementById('btn-bulk-stock').style.display = 'none';
                document.getElementById('btn-delete-suspended').style.display = 'none';
            }

            // Show action bar
            document.getElementById('action-bar').style.display = 'block';

            const container = document.getElementById('log-container');
            container.scrollTop = 0;
        });
    }

    // ─── Bulk Stock Actions ───
    function showDeleteSuspendedModal() {
        // Get all suspended results with stock IDs
        const suspendedItems = [];
        document.querySelectorAll('#result-tbody tr[data-status="suspended"]').forEach(row => {
            const cb = row.querySelector('.result-cb');
            if (cb && cb.dataset.stockId) {
                suspendedItems.push({ username: cb.value, stockId: cb.dataset.stockId });
            }
        });

        if (suspendedItems.length === 0) {
            Swal.fire({ icon: 'info', title: 'Tidak Ada Data', text: 'Tidak ada akun suspended yang terhubung dengan stok.' });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Hapus Stok Akun Suspended?',
            html: `<p>Anda akan menghapus <strong>${suspendedItems.length}</strong> stok akun yang berstatus SUSPENDED.</p><p class="text-danger small">Tindakan ini tidak dapat dibatalkan!</p>`,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus Stok',
        }).then(result => {
            if (result.isConfirmed) {
                const stockIds = suspendedItems.map(item => parseInt(item.stockId));
                fetch('{{ route("admin.tools.github-checker.bulk-delete-stock") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ stock_ids: stockIds })
                })
                .then(r => r.json())
                .then(data => {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false });
                });
            }
        });
    }

    function showBulkStockModal() {
        const checked = document.querySelectorAll('.result-cb:checked');
        if (checked.length === 0) {
            Swal.fire({ icon: 'info', title: 'Pilih Akun', text: 'Pilih akun yang ingin di-update statusnya menggunakan checkbox.' });
            return;
        }

        // Filter only those with stock IDs
        const stockItems = Array.from(checked).filter(cb => cb.dataset.stockId).map(cb => ({
            username: cb.value,
            stockId: parseInt(cb.dataset.stockId)
        }));

        if (stockItems.length === 0) {
            Swal.fire({ icon: 'info', title: 'Tidak Ada Stok', text: 'Akun yang dipilih tidak terhubung dengan stok produk.' });
            return;
        }

        Swal.fire({
            title: 'Update Status Stok Masal',
            html: `
                <p class="small text-muted mb-3">${stockItems.length} akun stok akan diupdate</p>
                <select id="swal-stock-status" class="form-select">
                    <option value="ready">Ready</option>
                    <option value="awaiting_benefits">Awaiting Benefits</option>
                    <option value="saved_for_verification">Simpan Akun</option>
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
                const stockIds = stockItems.map(item => item.stockId);
                fetch('{{ route("admin.tools.github-checker.bulk-update-stock") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ stock_ids: stockIds, stock_status: result.value })
                })
                .then(r => r.json())
                .then(data => {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false });
                });
            }
        });
    }

    // ─── Username Counter (Manual Input) ───
    document.getElementById('manual-usernames')?.addEventListener('input', function() {
        const lines = this.value.split('\n').filter(l => {
            const t = l.trim();
            return t && !t.match(/^(Password|F2A|2FA|Token|Email|Pass)\s*:/i);
        });
        // Count lines that look like usernames
        let count = 0;
        lines.forEach(l => {
            const t = l.trim();
            if (t.match(/^Username\s*:/i) || t.match(/^[a-zA-Z0-9][\w-]*$/) || (t.includes(':') && !t.includes(' '))) {
                count++;
            }
        });
        document.getElementById('parsed-count-manual').textContent = count + ' username';
    });

    function ucfirst(str) { return str.charAt(0).toUpperCase() + str.slice(1); }
</script>
@endpush
