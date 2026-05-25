@extends('layouts.app')

@section('title', 'Kelola Website')
@section('page_subtitle', 'Website Settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Kelola Website</h4>
        <p class="text-muted mb-0">Pengaturan tampilan dan informasi web</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="#" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">NAMA TOKO / WEBSITE</label>
                        <input type="text" name="app_name" class="form-control" value="{{ config('app.name', 'Dzulfikrialifajri Store') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">URL WEBSITE</label>
                        <input type="text" name="app_url" class="form-control" value="{{ config('app.url') }}" readonly>
                        <small class="text-muted">URL tidak dapat diubah dari panel ini.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small">TELEGRAM BOT USERNAME</label>
                        <input type="text" name="bot_username" class="form-control" value="{{ config('telegram.bot_username') }}">
                        <small class="text-muted">Username bot akan digunakan untuk tombol bantuan mengambang di pojok layar.</small>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold disabled" onclick="Swal.fire('Fitur update env masih dalam tahap pengembangan.')">
                            <i class="fas fa-save me-2"></i>Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
