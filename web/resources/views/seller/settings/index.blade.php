@extends('layouts.app')

@section('title', 'Pengaturan Karantina')
@section('page_subtitle', 'Pengaturan')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">{{ __('Pengaturan Akun Seller') }}</h4>
    <p class="text-muted mb-0">{{ __('Konfigurasikan durasi masa karantina stok akun khusus Anda secara mandiri.') }}</p>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-history text-primary me-2"></i>Durasi Karantina (*Simpan Akun*)</h5>
            
            <form action="{{ route('seller.settings.update') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">Durasi Jam Tunggu Karantina (Jam)</label>
                    <div class="input-group">
                        <input type="number" name="seller_save_hours" class="form-control" value="{{ $user->seller_save_hours ?? 80 }}" min="0" max="1000" required>
                        <span class="input-group-text bg-light text-muted">{{ __('jam') }}</span>
                    </div>
                    <div class="form-text mt-2 text-muted">
                        Ini adalah durasi tunggu cooldown karantina ketika Anda mengunggah stok akun baru.
                        Setelah durasi jam tunggu ini habis, status akun akan otomatis berubah menjadi **Ready** (siap dijual).
                    </div>
                </div>

                <div class="bg-warning-subtle text-warning p-3 rounded-4 small mb-4">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>{{ __('Catatan:') }}</strong> 
                    {{ __('Mengubah durasi ini hanya akan memengaruhi stok akun baru yang akan Anda unggah setelah perubahan ini disimpan. Stok akun lama yang sedang dikarantina tidak akan terpengaruh durasi barunya.') }}
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="fas fa-save me-1"></i> {{ __('Simpan Pengaturan') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
