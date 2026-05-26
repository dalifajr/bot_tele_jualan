@extends('layouts.app')

@section('title', 'Profil Saya')
@section('page_subtitle', 'Profil')

@section('content')
<div class="row g-4">
    <div class="col-lg-8 mx-auto">
        {{-- Profile Header --}}
        <div class="profile-header">
            <div class="profile-avatar">
                {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
            </div>
            <h4 class="fw-bold mb-1">{{ Auth::user()->full_name ?? Auth::user()->username ?? 'User' }}</h4>
            <p class="opacity-75 mb-0">
                @if(Auth::user()->username)
                    <span>@{{ Auth::user()->username }}</span>
                @endif
            </p>
        </div>

        {{-- Account Info --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-user text-primary me-2"></i>Informasi Akun</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-body-secondary rounded-3">
                            <span class="text-muted small d-block mb-1">Telegram ID</span>
                            <span class="fw-bold">{{ Auth::user()->telegram_id }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-body-secondary rounded-3">
                            <span class="text-muted small d-block mb-1">Username</span>
                            <span class="fw-bold">{{ Auth::user()->username ?: '-' }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-body-secondary rounded-3">
                            <span class="text-muted small d-block mb-1">Nama Lengkap</span>
                            <span class="fw-bold">{{ Auth::user()->full_name ?: '-' }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-body-secondary rounded-3">
                            <span class="text-muted small d-block mb-1">Role</span>
                            <span class="badge bg-{{ Auth::user()->role === 'admin' ? 'primary' : 'secondary' }}-subtle text-{{ Auth::user()->role === 'admin' ? 'primary' : 'secondary' }} rounded-pill px-3">
                                {{ ucfirst(Auth::user()->role ?? 'customer') }}
                            </span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 bg-body-secondary rounded-3">
                            <span class="text-muted small d-block mb-1">Bergabung Sejak</span>
                            <span class="fw-bold">{{ Auth::user()->created_at ? Auth::user()->created_at->format('d F Y') : '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Set / Update Password --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-lock text-primary me-2"></i>Atur Sandi (Password)</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if(session('success'))
                    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger small py-2 mb-3">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <p class="small text-muted mb-3">
                    Atur kata sandi agar Anda bisa melakukan <strong>Login Konvensional</strong> menggunakan Username dan Password tanpa harus bergantung pada Telegram.
                </p>
                <form action="{{ route('profile.password.update') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kata Sandi Baru</label>
                            <input type="password" name="password" class="form-control form-control-sm" required placeholder="Minimal 6 karakter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ulangi Sandi Baru</label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">
                                Simpan Sandi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3">
                    @if(config('telegram.bot_username'))
                    <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank"
                       class="quick-action-btn">
                        <div class="qa-icon" style="background: #e1f5fe;">
                            <i class="fab fa-telegram text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Chat via Telegram</div>
                            <small class="text-muted">Beli produk atau hubungi admin</small>
                        </div>
                        <i class="fas fa-external-link-alt text-muted ms-auto"></i>
                    </a>
                    @endif

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="quick-action-btn w-100 border-danger text-start">
                            <div class="qa-icon" style="background: #ffebee;">
                                <i class="fas fa-sign-out-alt text-danger"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-danger">Logout</div>
                                <small class="text-muted">Keluar dari akun ini</small>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
