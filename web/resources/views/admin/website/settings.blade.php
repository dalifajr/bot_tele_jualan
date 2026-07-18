@extends('layouts.app')

@section('title', 'Kelola Website')
@section('page_subtitle', 'Website Settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Kelola Website') }}</h4>
        <p class="text-muted mb-0">{{ __('Pengaturan tampilan dan informasi web') }}</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">{{ __('NAMA TOKO / WEBSITE') }}</label>
                        <input type="text" name="settings[app_name]" class="form-control" value="{{ config('app.name', 'Dzulfikrialifajri Store') }}" required>
                        <small class="text-muted">{{ __('Nama toko/website ini akan muncul di sidebar dashboard dan halaman login.') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">PENGUMUMAN WEBSITE (HTML DIIZINKAN)</label>
                        <textarea name="settings[web_announcement]" class="form-control" rows="4">{{ $announcement }}</textarea>
                        <small class="text-muted">{{ __('Teks pengumuman yang muncul pada halaman login dan popup smartphone.') }}</small>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>{{ __('Simpan Pengumuman') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
