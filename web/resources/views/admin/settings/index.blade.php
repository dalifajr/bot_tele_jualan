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
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="#" method="POST">
                    @csrf
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
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold disabled">
                            <i class="fas fa-save me-2"></i>Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
