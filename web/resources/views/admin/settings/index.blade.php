@extends('layouts.app')

@section('title', __('Konfigurasi Sistem'))
@section('page_subtitle', __('Konfigurasi Sistem'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Konfigurasi Sistem') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola pengaturan payment, waktu bot, dan konfigurasi lainnya') }}</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-10 mx-auto">
        {{-- Custom Tab Styles --}}
        <style>
            .nav-tabs .nav-link {
                color: #6c757d;
                border: none;
                border-bottom: 3px solid transparent;
                transition: all 0.2s ease-in-out;
            }
            .nav-tabs .nav-link:hover {
                border-color: #e9ecef;
                color: #495057;
            }
            .nav-tabs .nav-link.active {
                color: #0d6efd;
                background-color: transparent;
                border-color: #0d6efd;
            }
        </style>

        <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-4 py-3" id="bot-tab" data-bs-toggle="tab" data-bs-target="#bot" type="button" role="tab">
                    <i class="fas fa-clock me-2"></i>{{ __('Sistem & Waktu') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-4 py-3" id="vpn-tab" data-bs-toggle="tab" data-bs-target="#vpn" type="button" role="tab">
                    <i class="fas fa-server me-2"></i>{{ __('VPN Server') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-4 py-3" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                    <i class="fas fa-qrcode me-2"></i>{{ __('Payment & QRIS') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-4 py-3" id="utils-tab" data-bs-toggle="tab" data-bs-target="#utils" type="button" role="tab">
                    <i class="fas fa-tools me-2"></i>{{ __('Utilitas') }}
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabContent">
            {{-- TAB: SISTEM & WAKTU --}}
            <div class="tab-pane fade show active" id="bot" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <form action="{{ route('admin.settings.update') }}" method="POST" id="formTimerBot">
                            @csrf
                            <h5 class="fw-bold mb-1"><i class="fas fa-clock text-primary me-2"></i>{{ __('Atur Waktu Bot & Sistem') }}</h5>
                            <p class="text-muted small mb-3">{{ __('Atur wilayah waktu dan durasi waktu tunggu untuk status stok. Perubahan akan langsung berlaku untuk sistem website dan bot Telegram.') }}</p>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold small">Region Zona Waktu (Timezone)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary-subtle border-0"><i class="fas fa-globe text-primary"></i></span>
                                    <select name="settings[system_timezone]" class="form-select" required>
                                        <option value="Asia/Jakarta" {{ ($settings['system_timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : '' }}>WIB - Asia/Jakarta (GMT+7)</option>
                                        <option value="Asia/Makassar" {{ ($settings['system_timezone'] ?? '') === 'Asia/Makassar' ? 'selected' : '' }}>WITA - Asia/Makassar (GMT+8)</option>
                                        <option value="Asia/Jayapura" {{ ($settings['system_timezone'] ?? '') === 'Asia/Jayapura' ? 'selected' : '' }}>WIT - Asia/Jayapura (GMT+9)</option>
                                        <option value="Asia/Singapore" {{ ($settings['system_timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : '' }}>SGT - Asia/Singapore (GMT+8)</option>
                                        <option value="UTC" {{ ($settings['system_timezone'] ?? '') === 'UTC' ? 'selected' : '' }}>{{ __('UTC / GMT') }}</option>
                                    </select>
                                </div>
                                <div class="form-text">{!! __('Mengatur zona waktu untuk web admin, seller, dan pesan bot Telegram. Zona aktif saat ini: <strong>:timezone</strong> (Server: :time).', ['timezone' => $settings['system_timezone'] ?? 'UTC', 'time' => now()->timezone($settings['system_timezone'] ?? 'Asia/Jakarta')->format('Y-m-d H:i:s')]) !!}</div>
                            </div>

                             <div class="mb-4">
                                <label class="form-label fw-bold small">{{ __('Batas Waktu Pembayaran (Menit)') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-danger-subtle border-0"><i class="fas fa-stopwatch text-danger"></i></span>
                                    <input type="number" name="settings[checkout_expiry_minutes]" class="form-control" value="{{ $settings['checkout_expiry_minutes'] ?? 15 }}" min="1" max="1440" required>
                                    <span class="input-group-text bg-light border-0 small text-muted">{{ __('menit') }}</span>
                                </div>
                                <div class="form-text">{{ __('Berapa lama pelanggan diberi waktu untuk melakukan pembayaran QRIS setelah pesanan dibuat sebelum pesanan kedaluwarsa dan stok dilepas kembali.') }}</div>
                            </div>

                            <div class="mb-4 border-top pt-4">
                                <h6 class="fw-bold mb-3"><i class="fas fa-cookie-bite me-1 text-secondary"></i> {{ __('Masa Aktif Sesi Cookie (Menit)') }}</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Admin') }}</label>
                                        <div class="input-group">
                                            <input type="number" name="settings[session_lifetime_admin]" class="form-control" value="{{ $settings['session_lifetime_admin'] ?? 120 }}" min="1" max="525600" required>
                                            <span class="input-group-text small text-muted">{{ __('menit') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Seller') }}</label>
                                        <div class="input-group">
                                            <input type="number" name="settings[session_lifetime_seller]" class="form-control" value="{{ $settings['session_lifetime_seller'] ?? 1440 }}" min="1" max="525600" required>
                                            <span class="input-group-text small text-muted">{{ __('menit') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">{{ __('Customer') }}</label>
                                        <div class="input-group">
                                            <input type="number" name="settings[session_lifetime_customer]" class="form-control" value="{{ $settings['session_lifetime_customer'] ?? 43200 }}" min="1" max="525600" required>
                                            <span class="input-group-text small text-muted">{{ __('menit') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text mt-2">{{ __('Atur berapa lama sesi cookie tetap aktif untuk masing-masing role sebelum pengguna harus login kembali (120 = 2 jam, 1440 = 1 hari, 43200 = 30 hari).') }}</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">{{ __('Awaiting Benefits → Ready (Jam)') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-warning-subtle border-0"><i class="fas fa-hourglass-half text-warning"></i></span>
                                        <input type="number" name="settings[github_pack.awaiting_hours]" class="form-control" value="{{ $settings['github_pack.awaiting_hours'] ?? 78 }}" min="1" max="720" required>
                                        <span class="input-group-text bg-light border-0 small text-muted">{{ __('jam') }}</span>
                                    </div>
                                    <div class="form-text">{!! __('Default: 78 jam. Berapa lama akun di <em>awaiting benefits</em> sebelum otomatis menjadi <em>ready</em>.') !!}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">Simpan Akun → Siap Diajukan (Jam)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-info-subtle border-0"><i class="fas fa-save text-info"></i></span>
                                        <input type="number" name="settings[github_pack.save_hours]" class="form-control" value="{{ $settings['github_pack.save_hours'] ?? 80 }}" min="1" max="720" required>
                                        <span class="input-group-text bg-light border-0 small text-muted">{{ __('jam') }}</span>
                                    </div>
                                    <div class="form-text">{!! __('Default: 80 jam. Berapa lama akun <em>simpan akun</em> ditahan sebelum bisa diajukan verifikasi.') !!}</div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">
                                    <i class="fas fa-save me-2"></i>{{ __('Simpan Pengaturan Waktu') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: VPN SERVER --}}
            <div class="tab-pane fade" id="vpn" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <form action="{{ route('admin.settings.update') }}" method="POST">
                            @csrf
                            <h5 class="fw-bold mb-1 text-primary"><i class="fas fa-server me-2"></i>Konfigurasi Server VPN (Xray)</h5>
                            <p class="text-muted small mb-4">{{ __('Atur koneksi SSH menuju server VPS VPN Anda. Web Jualan ini akan membuat akun VPN secara otomatis di server tersebut.') }}</p>

                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold small">{{ __('Alamat IP Server VPN') }}</label>
                                    <input type="text" name="settings[vpn_server_ip]" class="form-control" value="{{ $settings['vpn_server_ip'] ?? '' }}" placeholder="103.x.x.x">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small">{{ __('Port SSH') }}</label>
                                    <input type="number" name="settings[vpn_server_port]" class="form-control" value="{{ $settings['vpn_server_port'] ?? '22' }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">{{ __('Username SSH') }}</label>
                                <input type="text" name="settings[vpn_server_username]" class="form-control" value="{{ $settings['vpn_server_username'] ?? 'root' }}">
                                <div class="form-text">{{ __('Biasanya `root`.') }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Kunci SSH (Private Key)</label>
                                <textarea name="settings[vpn_server_ssh_key_raw]" class="form-control text-muted" rows="6" placeholder="{{ __('-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----') }}" style="font-family: monospace; font-size: 0.85rem;">{{ $settings['vpn_server_ssh_key_raw'] ?? '' }}</textarea>
                                <div class="form-text">{!! __('Paste isi dari file <em>Private Key</em> Anda (.pem / id_rsa) di sini. Kunci ini digunakan untuk autentikasi SSH ke VPS secara otomatis tanpa menggunakan password. Sistem akan menyimpan dan memanfaatkannya dengan aman.') !!}</div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">
                                    <i class="fas fa-save me-2"></i>{{ __('Simpan Konfigurasi VPN') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: PAYMENT & QRIS --}}
            <div class="tab-pane fade" id="payment" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1"><i class="fas fa-qrcode text-success me-2"></i>{{ __('Konfigurasi QRIS Dinamis') }}</h5>
                        <p class="text-muted small mb-4">{{ __('Sistem akan mengekstrak payload dari gambar QRIS yang diupload dan menggunakannya untuk generate QRIS dengan nominal dinamis pada saat checkout.') }}</p>

                        @php
                            $qrisPayload = $settings['qris_static_payload'] ?? null;
                            $qrisImagePath = $settings['qris_image_path'] ?? null;
                        @endphp

                        @if($qrisPayload)
                            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-check-circle fs-3 me-3 text-success"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-success">{{ __('QRIS Dinamis Siap Digunakan') }}</h6>
                                        <small class="text-dark">{{ __('Payload berhasil diekstrak dan siap untuk transaksi.') }}</small>
                                    </div>
                                </div>
                                
                                <div class="row align-items-center">
                                    <div class="col-sm-4 text-center mb-3 mb-sm-0">
                                        @if($qrisImagePath)
                                            <img src="{{ route('admin.settings.qris.image') }}" alt="{{ __('QRIS Tersimpan') }}" class="img-fluid rounded border p-2 bg-white" style="max-height: 150px;">
                                        @else
                                            <div class="border rounded p-4 bg-white text-muted">
                                                <i class="fas fa-image fs-1 mb-2"></i><br>
                                                <small>{{ __('Gambar disetup via Bot Telegram') }}</small>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="form-label fw-bold small text-muted">PAYLOAD EKSTRAK (RAW)</label>
                                        <textarea class="form-control form-control-sm text-muted mb-3" rows="3" readonly>{{ $qrisPayload }}</textarea>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="document.getElementById('qrisUploadForm').classList.toggle('d-none')">
                                                <i class="fas fa-exchange-alt me-1"></i> {{ __('Ganti QRIS') }}
                                            </button>
                                            <form action="{{ route('admin.settings.qris.delete') }}" method="POST" class="d-inline" id="formDeleteQris" onsubmit="confirmAction(event, '{{ __('Yakin ingin menghapus QRIS? QRIS statis dan payload akan dihapus dari sistem.') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                    <i class="fas fa-trash me-1"></i> {{ __('Hapus') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="{{ route('admin.settings.qris.upload') }}" method="POST" enctype="multipart/form-data" id="qrisUploadForm" class="d-none border-top pt-4 mt-2">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted small">{{ __('UPLOAD GAMBAR QRIS BARU') }}</label>
                                    <input type="file" name="qris_image" class="form-control" accept="image/png, image/jpeg, image/jpg" required>
                                    <small class="text-muted">{{ __('Format: JPG, PNG. Maksimal 2MB. Gambar harus cukup jelas agar kode QR bisa diekstrak.') }}</small>
                                </div>
                                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-upload me-1"></i> {{ __('Upload & Ekstrak') }}</button>
                            </form>
                        @else
                            <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fs-3 me-3 text-warning"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">{{ __('QRIS Belum Dikonfigurasi') }}</h6>
                                        <small class="text-dark">{{ __('Sistem belum memiliki referensi QRIS untuk checkout. Silakan upload gambar QRIS.') }}</small>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('admin.settings.qris.upload') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted small">{{ __('UPLOAD GAMBAR QRIS') }}</label>
                                    <input type="file" name="qris_image" class="form-control" accept="image/png, image/jpeg, image/jpg" required>
                                    <small class="text-muted">{{ __('Format: JPG, PNG. Maksimal 2MB. Sistem akan otomatis membaca payload QRIS.') }}</small>
                                </div>
                                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-upload me-2"></i>{{ __('Upload & Ekstrak') }}</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <form action="{{ route('admin.settings.update') }}" method="POST">
                            @csrf
                            <h5 class="fw-bold mb-1"><i class="fas fa-cog text-secondary me-2"></i>{{ __('Pengaturan Payment Lainnya') }}</h5>
                            <p class="text-muted small mb-3">{{ __('Konfigurasi variabel dan parameter tambahan.') }}</p>

                            @php
                                $hasPaymentSettings = false;
                            @endphp
                            @foreach($settings as $key => $val)
                                @if((str_contains(strtolower($key), 'payment') || str_contains(strtolower($key), 'api') || str_contains(strtolower($key), 'pay')) && !str_contains(strtolower($key), 'qris'))
                                @php $hasPaymentSettings = true; @endphp
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted small">{{ strtoupper(str_replace(['_', '.'], ' ', $key)) }}</label>
                                    <input type="text" name="settings[{{ $key }}]" class="form-control" value="{{ $val }}">
                                </div>
                                @endif
                            @endforeach
                            
                            @if(!$hasPaymentSettings)
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle fs-3 mb-2"></i>
                                    <p class="mb-0">{{ __('Tidak ada pengaturan parameter payment tambahan.') }}</p>
                                </div>
                            @endif

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-secondary rounded-pill py-2 fw-bold">
                                    <i class="fas fa-save me-2"></i>{{ __('Simpan Variabel Payment') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: UTILITAS --}}
            <div class="tab-pane fade" id="utils" role="tabpanel">
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1 text-danger"><i class="fas fa-tools me-2"></i>{{ __('Utilitas & Pemeliharaan Sistem') }}</h5>
                        <p class="text-muted small mb-3">{{ __('Jalankan aksi pemeliharaan sistem secara manual jika cron job/scheduler di server hosting Anda tidak aktif.') }}</p>
                        
                        <div class="d-flex flex-column gap-2">
                            <form action="{{ route('admin.settings.run-held-funds') }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Jalankan pencarian dan pelepasan saldo garansi yang telah habis masa berlakunya sekarang?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-warning rounded-pill w-100 fw-bold py-2 text-start px-3 d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-hand-holding-usd me-2"></i>Jalankan Pencairan Saldo Garansi (funds:release-held)</span>
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </form>
                            
                            <form action="{{ route('admin.settings.run-release-expired') }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Jalankan pelepasan stok dari pesanan yang kedaluwarsa sekarang?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary rounded-pill w-100 fw-bold py-2 text-start px-3 d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-hourglass-end me-2"></i>Batalkan Pesanan Expired & Lepas Stok (orders:release-expired)</span>
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
