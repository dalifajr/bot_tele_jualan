@extends('layouts.app')

@section('title', __('Riwayat Percobaan Login'))
@section('page_subtitle', __('Akun'))

@section('content')
<div class="row g-3">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1 text-body fs-5 fs-md-4"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Riwayat Percobaan Login') }}</h4>
                <p class="text-muted small mb-0">{{ __('Catatan masuk ke akun Anda berdasarkan alamat IP dan perangkat.') }}</p>
            </div>
            <a href="{{ route('profile') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Kembali ke Profil') }}
            </a>
        </div>

        <div class="card overflow-hidden" style="border-radius: var(--radius-lg, 20px);">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom">
                                <th class="px-4 py-3 border-0">{{ __('Waktu') }}</th>
                                <th class="py-3 border-0">{{ __('Status') }}</th>
                                <th class="py-3 border-0">{{ __('IP & Lokasi') }}</th>
                                <th class="py-3 border-0">{{ __('Perangkat') }}</th>
                                <th class="py-3 border-0 text-end pe-4">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginLogs as $log)
                            <tr>
                                <td class="px-4 text-secondary small text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-semibold"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-semibold"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-body font-monospace small">{{ $log->ip_address }}</div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-body small fw-medium">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab fa-chrome text-primary me-1"></i>{{ $log->browser ?? '-' }}</div>
                                </td>
                                <td class="text-end pe-4">
                                    @if(\Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address) || \Illuminate\Support\Facades\Cache::has('blocked_user_ip:' . Auth::id() . ':' . $log->ip_address))
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" onclick="requestUnblockIp(event, '{{ $log->ip_address }}', '{{ $log->location ?? 'Unknown' }}', '{{ $log->device_type }}', '{{ $log->browser ?? '-' }}')">
                                            <i class="fas fa-unlock me-1"></i>{{ __('Buka Blokir') }}
                                        </button>
                                    @else
                                        <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline" onsubmit="confirmBlockIp(event, this)">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $log->ip_address }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
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

                {{-- Mobile Collapsible Compact List --}}
                <div class="d-md-none p-2.5 m3-collapsible-list" id="profileLoginsAccordion">
                    @foreach($loginLogs as $log)
                    <div class="m3-mobile-item-card m3-collapsible-card">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#profileLoginCollapse-{{ $log->id }}"
                                    aria-expanded="false"
                                    aria-controls="profileLoginCollapse-{{ $log->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold font-monospace text-body text-truncate" style="font-size: 0.83rem;">{{ $log->ip_address }}</div>
                                    <div class="text-secondary text-truncate" style="font-size: 0.72rem;">
                                        {{ $log->location ?? 'Unknown' }} · {{ $log->created_at->format('d M, H:i') }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ __('Gagal') }}</span>
                                    @endif
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>

                        <div class="collapse" id="profileLoginCollapse-{{ $log->id }}" data-bs-parent="#profileLoginsAccordion">
                            <div class="m3-collapsible-body">
                                <div class="small text-secondary mb-2.5">
                                    <div><i class="fas fa-laptop me-1"></i>{{ $log->device_type }} · <i class="fab fa-chrome me-1"></i>{{ $log->browser ?? '-' }}</div>
                                    <div class="mt-0.5"><i class="far fa-clock me-1"></i>{{ $log->created_at->format('d M Y, H:i') }}</div>
                                </div>
                                @if(\Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address) || \Illuminate\Support\Facades\Cache::has('blocked_user_ip:' . Auth::id() . ':' . $log->ip_address))
                                    <button type="button" class="btn btn-sm btn-outline-success m3-compact-action-btn w-100" onclick="requestUnblockIp(event, '{{ $log->ip_address }}', '{{ $log->location ?? 'Unknown' }}', '{{ $log->device_type }}', '{{ $log->browser ?? '-' }}')">
                                        <i class="fas fa-unlock"></i><span>{{ __('Buka Blokir') }}</span>
                                    </button>
                                @else
                                    <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="m-0" onsubmit="confirmBlockIp(event, this)">
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $log->ip_address }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger m3-compact-action-btn w-100">
                                            <i class="fas fa-ban"></i><span>{{ __('Blokir IP Ini') }}</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="px-3 px-sm-4 py-3 border-top">
                    {{ $loginLogs->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5 px-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                        <i class="fas fa-shield-alt"></i>
                    </span>
                    <p class="text-muted mb-0 small">{{ __('Belum ada riwayat percobaan login.') }}</p>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    const admins = @json($admins);

    function confirmBlockIp(event, form) {
        event.preventDefault();
        Swal.fire({
            title: '{{ __('Pilih Durasi Blokir IP') }}',
            text: "{{ __('Pilih jangka waktu pemblokiran untuk IP :ip') }}".replace(':ip', form.ip_address.value),
            icon: 'warning',
            input: 'select',
            inputOptions: {
                '1': '{{ __('1 Hari') }}',
                '7': '{{ __('7 Hari') }}',
                '30': '{{ __('30 Hari') }}',
                '365': '{{ __('1 Tahun') }}'
            },
            inputValue: '1',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ __('Blokir IP') }}',
            cancelButtonText: '{{ __('Batal') }}',
            inputValidator: (value) => {
                return new Promise((resolve) => {
                    if (value) {
                        resolve();
                    } else {
                        resolve('{{ __('Anda harus memilih durasi blokir!') }}');
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

    function requestUnblockIp(event, ip, location, device, browser) {
        event.preventDefault();
        
        if (admins.length === 0) {
            Swal.fire({
                title: '{{ __('Hubungi Admin') }}',
                text: '{{ __('Tidak ada admin aktif saat ini untuk memproses permintaan Anda.') }}',
                icon: 'error',
                confirmButtonText: '{{ __('Mengerti') }}'
            });
            return;
        }
        
        Swal.fire({
            title: '{{ __('Buka Blokir IP') }}',
            html: `{{ __('IP Anda') }} <b>${ip}</b> {{ __('saat ini terblokir.') }}<br>{{ __('Silakan hubungi administrator untuk membuka blokir ini.') }}`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '{{ __('Chat Admin') }}',
            cancelButtonText: '{{ __('Mengerti') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                if (admins.length === 1) {
                    submitUnblockRequest(admins[0].id, ip, location, device, browser);
                } else {
                    let adminOptions = {};
                    admins.forEach(admin => {
                        adminOptions[admin.id] = admin.full_name || admin.username;
                    });
                    
                    Swal.fire({
                        title: '{{ __('Pilih Administrator') }}',
                        text: '{{ __('Pilih admin yang ingin Anda hubungi:') }}',
                        input: 'select',
                        inputOptions: adminOptions,
                        inputPlaceholder: '{{ __('Pilih admin...') }}',
                        showCancelButton: true,
                        confirmButtonText: '{{ __('Pilih & Hubungi') }}',
                        cancelButtonText: '{{ __('Batal') }}',
                        inputValidator: (value) => {
                            return new Promise((resolve) => {
                                if (value) {
                                    resolve();
                                } else {
                                    resolve('{{ __('Anda harus memilih administrator!') }}');
                                }
                            });
                        }
                    }).then((adminResult) => {
                        if (adminResult.isConfirmed) {
                            submitUnblockRequest(adminResult.value, ip, location, device, browser);
                        }
                    });
                }
            }
        });
    }

    function submitUnblockRequest(adminId, ip, location, device, browser) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = "{{ route('profile.logins.request-unblock') }}";
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = "{{ csrf_token() }}";
        form.appendChild(csrfInput);
        
        const adminInput = document.createElement('input');
        adminInput.type = 'hidden';
        adminInput.name = 'admin_id';
        adminInput.value = adminId;
        form.appendChild(adminInput);
        
        const ipInput = document.createElement('input');
        ipInput.type = 'hidden';
        ipInput.name = 'ip_address';
        ipInput.value = ip;
        form.appendChild(ipInput);
        
        const locInput = document.createElement('input');
        locInput.type = 'hidden';
        locInput.name = 'location';
        locInput.value = location;
        form.appendChild(locInput);
        
        const devInput = document.createElement('input');
        devInput.type = 'hidden';
        devInput.name = 'device';
        devInput.value = device;
        form.appendChild(devInput);
        
        const browInput = document.createElement('input');
        browInput.type = 'hidden';
        browInput.name = 'browser';
        browInput.value = browser;
        form.appendChild(browInput);
        
        document.body.appendChild(form);
        
        let loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.remove('fade-out');
        }
        
        form.submit();
    }
</script>
@endpush
