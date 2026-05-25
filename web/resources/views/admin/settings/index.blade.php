@extends('layouts.app')

@section('title', 'Konfigurasi Payment')
@section('page_subtitle', 'Konfigurasi Payment')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Konfigurasi Payment</h4>
        <p class="text-muted mb-0">Kelola pengaturan pembayaran dan kunci API</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Pengaturan API & Payment</h5>
                    @foreach($settings as $key => $val)
                        @if(str_contains(strtolower($key), 'payment') || str_contains(strtolower($key), 'api') || str_contains(strtolower($key), 'pay'))
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">{{ strtoupper(str_replace('_', ' ', $key)) }}</label>
                            <input type="text" name="{{ $key }}" class="form-control" value="{{ $val }}">
                        </div>
                        @endif
                    @endforeach
                    
                    @if(empty($settings))
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-cogs fs-2 mb-2"></i>
                            <p>Pengaturan payment belum tersedia dalam tabel database bot_settings.</p>
                        </div>
                    @endif

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Konfigurasi Waktu Bot Stok</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Waktu Awaiting Benefits ke Ready (Jam)</label>
                        <input type="number" name="github_pack.awaiting_hours" class="form-control" value="{{ $settings['github_pack.awaiting_hours'] ?? 78 }}" min="1" max="720">
                        <div class="form-text">Berapa jam akun ditahan sebelum bisa diverifikasi? Default Bot: 78</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Waktu Simpan Akun ke Siap Diajukan (Jam)</label>
                        <input type="number" name="github_pack.save_hours" class="form-control" value="{{ $settings['github_pack.save_hours'] ?? 80 }}" min="1" max="720">
                        <div class="form-text">Berapa jam akun "Simpan" ditahan sebelum siap? Default Bot: 80</div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Waktu Bot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
