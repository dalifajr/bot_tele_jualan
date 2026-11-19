{{-- Tab 5: Log Sistem Real-Time (Laravel, Bot Telegram, API, Nginx, VPS Task) --}}
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-file-lines text-primary me-1.5"></i>{{ __('Log Sistem Real-Time') }}</h6>
                <small class="text-muted">{{ __('Pantau log aplikasi Laravel, journalctl Bot/API, Nginx Error, dan output tugas VPS langsung dari browser') }}</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.system.health.logs.download') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
                    <i class="fas fa-download me-1"></i>{{ __('Unduh Laravel Log') }}
                </a>
                <form action="{{ route('admin.system.health.logs.clear') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="active_tab" value="log">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="confirmAction(event, '{{ __('Kosongkan file storage/logs/laravel.log sekarang?') }}')">
                        <i class="fas fa-trash-can me-1"></i>{{ __('Kosongkan Log') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Filter & Kontrol Sumber Log --}}
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">{{ __('Sumber Log') }}</label>
                <select id="logSourceSelect" class="form-select form-select-sm">
                    <option value="laravel" selected>{{ __('Laravel Application Log (laravel.log)') }}</option>
                    <option value="bot">{{ __('Bot Telegram (journalctl jualan-bot)') }}</option>
                    <option value="api">{{ __('API Service (journalctl jualan-api)') }}</option>
                    <option value="nginx">{{ __('Nginx Web Server Error Log') }}</option>
                    <option value="remote-control">{{ __('Remote Control Relay (journalctl)') }}</option>
                    <option value="ops-task">{{ __('Output Tugas VPS (Update / Repair)') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">{{ __('Filter Level') }}</label>
                <select id="logLevelSelect" class="form-select form-select-sm">
                    <option value="all" selected>{{ __('Semua Level') }}</option>
                    <option value="error">{{ __('Hanya Error / Critical') }}</option>
                    <option value="warning">{{ __('Hanya Warning') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">{{ __('Baris Terakhir') }}</label>
                <select id="logLinesSelect" class="form-select form-select-sm">
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="250">250</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" id="btnFetchSystemLogs" class="btn btn-sm btn-primary rounded-pill w-100 fw-semibold">
                    <i class="fas fa-rotate me-1"></i>{{ __('Muat Ulang Log') }}
                </button>
            </div>
        </div>

        <div class="rounded-4 p-3 bg-dark text-light">
            <div class="d-flex justify-content-between align-items-center mb-2 small">
                <span class="text-info font-monospace" id="logViewerMeta">laravel.log · {{ __('Diperbarui:') }} {{ $initialLog['fetched_at'] }}</span>
                <span class="text-secondary">{{ __('Ukuran laravel.log:') }} {{ $metrics['log_size_label'] }}</span>
            </div>
            <pre id="systemLogViewer" class="mb-0 text-light" style="font-size: 0.76rem; line-height: 1.5; max-height: 460px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;">{{ $initialLog['content'] }}</pre>
        </div>
    </div>
</div>
