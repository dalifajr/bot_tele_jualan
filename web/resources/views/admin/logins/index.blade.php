@extends('layouts.app')

@section('title', 'Notifikasi Login Web')
@section('page_subtitle', 'Sistem Keamanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Daftar Percobaan Login') }}</h4>
        <p class="text-muted mb-0">{{ __('Riwayat autentikasi masuk ke web admin.') }}</p>
    </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" id="loginTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-4 fw-medium" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab" aria-selected="true">
            <i class="fas fa-shield-alt me-2"></i>{{ __('Semua Percobaan Web') }}
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-4 fw-medium" id="tg-tab" data-bs-toggle="tab" data-bs-target="#tg-pane" type="button" role="tab" aria-selected="false">
            <i class="fab fa-telegram me-2"></i>{{ __('Login Telegram') }}
        </button>
    </li>
</ul>

<div class="tab-content" id="loginTabsContent">
    <div class="tab-pane fade show active" id="logs-pane" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
                                <th class="px-4 py-3 border-0">{{ __('Waktu') }}</th>
                                <th class="py-3 border-0">{{ __('Status') }}</th>
                                <th class="py-3 border-0">{{ __('User/Email') }}</th>
                                <th class="py-3 border-0">{{ __('IP & Lokasi') }}</th>
                                <th class="py-3 border-0">{{ __('Perangkat') }}</th>
                                <th class="py-3 border-0 text-end pe-4">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginLogs as $log)
                            <tr>
                                <td class="px-4 text-secondary small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
                                    @endif
                                </td>
                                <td class="fw-bold text-muted">{{ $log->username_or_email ?? '-' }}</td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $log->ip_address }}</div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-dark small">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab fa-chrome text-primary me-1"></i>{{ $log->browser ?? '-' }}</div>
                                </td>
                                <td class="text-end pe-4">
                                    @if(\Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address))
                                        <form action="{{ route('admin.logins.unblock-ip') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $log->ip_address }}">
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="confirmAction(event, 'Buka blokir IP ini?')">
                                                <i class="fas fa-unlock me-1"></i>{{ __('Buka Blokir') }}
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.logins.block-ip') }}" method="POST" class="d-inline" onsubmit="confirmBlockIp(event, this)">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $log->ip_address }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                <i class="fas fa-ban me-1"></i>{{ __('Blokir') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $loginLogs->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login.') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tg-pane" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginTokens->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
                                <th class="px-4 py-3 border-0">{{ __('Kode / Token') }}</th>
                                <th class="py-3 border-0">{{ __('Status') }}</th>
                                <th class="py-3 border-0">{{ __('IP Address') }}</th>
                                <th class="py-3 border-0">{{ __('Browser') }}</th>
                                <th class="py-3 border-0">{{ __('Waktu Expired') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginTokens as $login)
                            <tr>
                                <td class="px-4 fw-bold text-muted">{{ $login->token }}</td>
                                <td>
                                    @if($login->status === 'used')
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill">{{ __('Menunggu Verifikasi') }}</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">{{ ucfirst($login->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $login->ip_address ?? '-' }}</td>
                                <td class="text-secondary small text-truncate" style="max-width: 200px;" title="{{ $login->user_agent }}">{{ $login->user_agent ?? '-' }}</td>
                                <td class="text-secondary small">{{ $login->expires_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $loginTokens->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fab fa-telegram text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login telegram.') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function confirmBlockIp(event, form) {
        event.preventDefault();
        Swal.fire({
            title: 'Pilih Durasi Blokir IP',
            text: `Pilih jangka waktu pemblokiran untuk IP ${form.ip_address.value}`,
            icon: 'warning',
            input: 'select',
            inputOptions: {
                '1': '1 Hari',
                '7': '7 Hari',
                '30': '30 Hari',
                '365': '1 Tahun'
            },
            inputValue: '1',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Blokir IP',
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                return new Promise((resolve) => {
                    if (value) {
                        resolve();
                    } else {
                        resolve('Anda harus memilih durasi blokir!');
                    }
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let durationInput = document.createElement('input');
                durationInput.type = 'hidden';
                durationInput.name = 'duration';
                durationInput.value = result.value;
                form.appendChild(durationInput);
                
                let loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.remove('fade-out');
                }
                form.submit();
            }
        });
    }
</script>
@endpush
