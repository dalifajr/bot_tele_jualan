@extends('layouts.app')

@section('title', 'Progres Live Checker')
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
    .progress-wrapper {
        background: var(--bs-body-bg);
        border-radius: 10px;
        padding: 2px;
    }
    .progress-wrapper .progress {
        height: 10px;
        border-radius: 8px;
    }
    .log-container {
        max-height: 450px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    #result-table tbody tr:last-child {
        background-color: rgba(var(--bs-primary-rgb), 0.04);
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <a href="{{ route('admin.tools.github-checker') }}" class="text-decoration-none text-dark me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            Pengecekan Batch #{{ $batch->id }}
        </h4>
        <p class="text-muted mb-0">Status dan hasil riwayat live checker GitHub</p>
    </div>
    <div>
        <button type="button" class="btn btn-danger rounded-pill px-4" id="btn-stop-check" onclick="stopChecking()" style="display: {{ $batch->status === 'running' ? 'inline-block' : 'none' }}">
            <i class="fas fa-stop me-1"></i>Hentikan Pengecekan
        </button>
        <a href="{{ route('admin.tools.github-checker') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-home me-1"></i>Kembali
        </a>
    </div>
</div>

{{-- Progress Card --}}
<div class="card shadow-sm checker-card mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">
                <i class="fas fa-tasks text-info me-2"></i>Status Pengecekan
                @php
                    $statusBadge = match($batch->status) {
                        'completed' => 'bg-success-subtle text-success',
                        'running' => 'bg-info-subtle text-info',
                        'stopped' => 'bg-warning-subtle text-warning',
                        default => 'bg-secondary-subtle text-secondary',
                    };
                @endphp
                <span class="badge {{ $statusBadge }} ms-2" id="progress-status">{{ ucfirst($batch->status === 'completed' ? 'Selesai' : $batch->status) }}</span>
            </h6>
            <span class="fw-bold" id="progress-text">{{ $batch->checked_count }} / {{ $batch->total_accounts }}</span>
        </div>
        <div class="progress-wrapper">
            <div class="progress">
                <div class="progress-bar bg-primary {{ $batch->status === 'running' ? 'progress-bar-striped progress-bar-animated' : '' }}" 
                     id="progress-bar" 
                     style="width: {{ $batch->total_accounts > 0 ? round(($batch->checked_count / $batch->total_accounts) * 100) : 0 }}%">
                </div>
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
<div class="card shadow-sm action-bar-card mb-3" id="action-bar" style="z-index: 1025;">
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
                        <li><a class="dropdown-item" href="#" onclick="exportResults('all')"><i class="fas fa-file-excel me-2 text-success"></i>Semua Hasil (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportResults('approved')"><i class="fas fa-check-circle me-2 text-success"></i>Hanya Approved</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportResults('not_approved')"><i class="fas fa-exclamation-circle me-2 text-warning"></i>Hanya Revoked</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportResults('suspended')"><i class="fas fa-times-circle me-2 text-danger"></i>Hanya Suspended</a></li>
                    </ul>
                </div>
                {{-- Bulk Stock Update --}}
                <button class="btn btn-sm btn-outline-info rounded-pill" onclick="showBulkStockModal()" id="btn-bulk-stock" style="display:none;">
                    <i class="fas fa-exchange-alt me-1"></i>Update Stok Masal
                </button>
                {{-- Delete Selected Stock --}}
                <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="showDeleteSelectedStockModal()" id="btn-delete-selected-stock" style="display:none;">
                    <i class="fas fa-trash-alt me-1"></i>Hapus Stok Terpilih
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
    </div>
</div>
@endsection

@push('scripts')
<script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const batchId = {{ $batch->id }};
    const delay = {{ $delay }};
    const isInitiallyRunning = "{{ $batch->status }}" === "running";
    let isRunning = isInitiallyRunning;
    let usernameQueue = @json($usernames);
    let currentIndex = 0;
    let counts = { approved: 0, not_approved: 0, suspended: 0, error: 0 };
    let stockMap = @json($stockMap);

    document.addEventListener("DOMContentLoaded", function () {
        loadBatchData();
    });

    function loadBatchData() {
        fetch(`/admin/tools/github-checker/progress/${batchId}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            counts = data.summary;
            updateCounters();
            updateProgress(data.batch.checked, data.batch.total);

            // Populate table
            const tbody = document.getElementById('result-tbody');
            tbody.innerHTML = '';
            
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
                
                if (r.stock_id) {
                    stockMap[r.username] = r.stock_id;
                }
            });

            // If the batch was running, determine where to continue
            if (isRunning) {
                currentIndex = data.batch.checked;
                // If there are still accounts left in the queue, run loop
                if (currentIndex < usernameQueue.length) {
                    processNext();
                } else {
                    finishChecking();
                }
            } else {
                toggleBulkButtons(data.results.some(r => r.stock_id));
            }
        });
    }

    function processNext() {
        if (!isRunning || currentIndex >= usernameQueue.length) {
            finishChecking();
            return;
        }

        const username = usernameQueue[currentIndex];
        updateCurrentRow(username);

        fetch(`/admin/tools/github-checker/check-next/${batchId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ username: username, stock_id: stockMap[username] || null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                appendResult(data.result);
                counts[data.result.result]++;
                updateCounters();
                updateProgress(data.progress.checked, data.progress.total);
            }

            currentIndex++;

            if (isRunning && currentIndex < usernameQueue.length) {
                setTimeout(processNext, delay * 1000);
            } else {
                finishChecking();
            }
        })
        .catch(err => {
            appendResult({ username: username, result: 'error', detail: 'Request error: ' + err.message });
            counts.error++;
            updateCounters();
            currentIndex++;
            
            if (isRunning && currentIndex < usernameQueue.length) {
                setTimeout(processNext, delay * 1000);
            } else {
                finishChecking();
            }
        });
    }

    function updateCurrentRow(username) {
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

        const container = document.getElementById('log-container');
        container.scrollTop = container.scrollHeight;
    }

    function appendResult(result) {
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
            <td class="px-4"><input type="checkbox" class="form-check-input result-cb" value="${result.username}" data-stock-id="${result.stock_id || ''}" onchange="updateActionCount()"></td>
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

    function stopChecking() {
        isRunning = false;
        document.getElementById('btn-stop-check').style.display = 'none';
        
        fetch(`/admin/tools/github-checker/stop/${batchId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
        })
        .then(() => {
            finishChecking('stopped');
        });
    }

    function finishChecking(status = 'completed') {
        isRunning = false;
        document.getElementById('btn-stop-check').style.display = 'none';

        const progressStatus = document.getElementById('progress-status');
        if (status === 'stopped') {
            progressStatus.textContent = 'Dihentikan';
            progressStatus.className = 'badge bg-warning-subtle text-warning ms-2';
        } else {
            progressStatus.textContent = 'Selesai';
            progressStatus.className = 'badge bg-success-subtle text-success ms-2';
        }

        document.getElementById('progress-bar').classList.remove('progress-bar-striped', 'progress-bar-animated');

        // Toggle bulk action buttons if we have stock mapping
        const hasStock = Object.keys(stockMap).length > 0;
        toggleBulkButtons(hasStock);

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

    function toggleBulkButtons(hasStock) {
        if (hasStock) {
            document.getElementById('btn-bulk-stock').style.display = 'inline-block';
            if (counts.suspended > 0) {
                document.getElementById('btn-delete-suspended').style.display = 'inline-block';
            }
        } else {
            document.getElementById('btn-bulk-stock').style.display = 'none';
            document.getElementById('btn-delete-suspended').style.display = 'none';
        }
    }

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

    // ─── Actions ───
    function toggleAllResults() {
        const checked = document.getElementById('select-all-results').checked;
        document.querySelectorAll('.result-cb').forEach(cb => {
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

        // Toggle delete selected stock button dynamically
        const hasSelectedStock = Array.from(checked).some(cb => cb.dataset.stockId);
        const btnDeleteSelected = document.getElementById('btn-delete-selected-stock');
        
        if (btnDeleteSelected) {
            if (!isRunning && hasSelectedStock) {
                btnDeleteSelected.style.display = 'inline-block';
            } else {
                btnDeleteSelected.style.display = 'none';
            }
        }
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
        const url = `/admin/tools/github-checker/export/${batchId}?status=${status}`;
        window.open(url, '_blank');
    }

    function showDeleteSuspendedModal() {
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
                    loadBatchData();
                });
            }
        });
    }

    function showDeleteSelectedStockModal() {
        const checked = document.querySelectorAll('.result-cb:checked');
        if (checked.length === 0) {
            Swal.fire({ icon: 'info', title: 'Pilih Akun', text: 'Pilih akun yang ingin dihapus dari stok menggunakan checkbox.' });
            return;
        }

        const stockItems = Array.from(checked).filter(cb => cb.dataset.stockId).map(cb => ({
            username: cb.value,
            stockId: parseInt(cb.dataset.stockId)
        }));

        if (stockItems.length === 0) {
            Swal.fire({ icon: 'info', title: 'Tidak Ada Stok', text: 'Akun terpilih tidak terhubung dengan stok produk.' });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Hapus Stok Terpilih?',
            html: `<p>Anda akan menghapus <strong>${stockItems.length}</strong> stok akun terpilih.</p><p class="text-danger small">Tindakan ini tidak dapat dibatalkan!</p>`,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus',
        }).then(result => {
            if (result.isConfirmed) {
                const stockIds = stockItems.map(item => item.stockId);
                fetch('{{ route("admin.tools.github-checker.bulk-delete-stock") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ stock_ids: stockIds })
                })
                .then(r => r.json())
                .then(data => {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false });
                    loadBatchData();
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
                    loadBatchData();
                });
            }
        });
    }
</script>
@endpush
