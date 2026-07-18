@extends('layouts.app')

@section('title', 'Riwayat Percobaan Login')
@section('page_subtitle', 'Akun')

@section('content')
<div class="row g-4">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Riwayat Percobaan Login') }}</h4>
                <p class="text-muted mb-0">{{ __('Catatan masuk ke akun Anda berdasarkan alamat IP dan perangkat.') }}</p>
            </div>
            <a href="{{ route('profile') }}" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Kembali ke Profil') }}
            </a>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
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
                                <td class="px-4 text-secondary small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->{{ __('is_successful)') }}
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
                                    @endif
                                </td>
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
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="requestUnblockIp(event, '{{ $log->ip_address }}', '{{ $log->location ?? 'Unknown' }}', '{{ $log->device_type }}', '{{ $log->browser ?? '-' }}')">
                                            <i class="fas fa-unlock me-1"></i>{{ __('Buka Blokir') }}
                                        </button>
                                    @else
                                        <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline" onsubmit="confirmBlockIp(event, this)">
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
                    <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login.') }}</p>
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

    function requestUnblockIp(event, ip, location, device, browser) {
        event.preventDefault();
        
        if (admins.length === 0) {
            Swal.fire({
                title: 'Hubungi Admin',
                text: 'Tidak ada admin aktif saat ini untuk memproses permintaan Anda.',
                icon: 'error',
                confirmButtonText: 'Mengerti'
            });
            return;
        }
        
        Swal.fire({
            title: 'Buka Blokir IP',
            html: `IP Anda <b>${ip}</b> {{ __('saat ini terblokir.') }}<br>Silakan hubungi administrator untuk membuka blokir ini.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Chat Admin',
            cancelButtonText: 'Mengerti'
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
                        title: 'Pilih Administrator',
                        text: 'Pilih admin yang ingin Anda hubungi:',
                        input: 'select',
                        inputOptions: adminOptions,
                        inputPlaceholder: 'Pilih admin...',
                        showCancelButton: true,
                        confirmButtonText: 'Pilih & Hubungi',
                        cancelButtonText: 'Batal',
                        inputValidator: (value) => {
                            return new Promise((resolve) => {
                                if (value) {
                                    resolve();
                                } else {
                                    resolve('Anda harus memilih administrator!');
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
