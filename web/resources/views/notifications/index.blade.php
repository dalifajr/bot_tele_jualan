@extends('layouts.app')

@section('title', 'Pusat Notifikasi')

@section('content')
<div class="container py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-bell me-2"></i>{{ __('Pusat Notifikasi') }}</h4>
            <p class="text-muted mb-0">{{ __('Semua pemberitahuan akun Anda') }}</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            @if(Auth::user()->unreadNotifications->count() > 0)
            <button onclick="markAllNotificationsRead()" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-check-double me-2"></i>{{ __('Tandai Semua Dibaca') }}
            </button>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4" style="border-radius: 1rem;">
        <div class="card-body p-0">
            <div class="list-group list-group-flush rounded-4">
                @forelse($notifications as $notification)
                    <a href="{{ $notification->data['url'] ?? '#' }}" 
                       class="list-group-item list-group-item-action py-4 px-4 {{ $notification->read_at ? 'bg-light text-muted' : 'bg-white' }}"
                       onclick="markSingleNotificationAsRead('{{ $notification->id }}')"
                       style="border-left: 4px solid {{ $notification->read_at ? 'transparent' : 'var(--bs-primary)' }};">
                        
                        <div class="d-flex w-100 justify-content-between align-items-start gap-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="icon-container rounded-circle d-flex align-items-center justify-content-center mt-1" 
                                     style="width: 48px; height: 48px; background-color: rgba(var(--bs-{{ str_contains($notification->data['icon'] ?? '', 'danger') ? 'danger' : (str_contains($notification->data['icon'] ?? '', 'success') ? 'success' : (str_contains($notification->data['icon'] ?? '', 'warning') ? 'warning' : 'primary')) }}-rgb), 0.1);">
                                    <i class="{{ $notification->data['icon'] ?? 'fas fa-bell text-primary' }} fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold {{ $notification->read_at ? 'text-secondary' : 'text-dark' }}">
                                        {{ $notification->data['title'] ?? 'Notifikasi' }}
                                    </h6>
                                    <p class="mb-1 {{ $notification->read_at ? 'text-muted' : 'text-body' }}" style="font-size: 0.95rem;">
                                        {{ $notification->data['message'] ?? '' }}
                                    </p>
                                    
                                    @if(($notification->data['type'] ?? '') === 'login_gagal' && isset($notification->{{ __('data[\'ip_address\']))') }}
                                    <div class="mt-2 mb-2" onclick="event.stopPropagation(); event.preventDefault();">
                                        <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $notification->data['ip_address'] }}">
                                            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3" style="font-size: 0.8rem;">
                                                <i class="fas fa-ban me-1"></i>{{ __('Bukan Saya (Blokir IP)') }}
                                            </button>
                                        </form>
                                    </div>
                                    @endif

                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                                    </small>
                                </div>
                            </div>
                            
                            @if(!$notification->{{ __('read_at)') }}
                                <span class="badge bg-primary rounded-pill">{{ __('Baru') }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-bell-slash fs-1 mb-3 text-secondary" style="opacity: 0.5;"></i>
                        <h5>{{ __('Belum ada notifikasi') }}</h5>
                        <p>{{ __('Anda belum menerima pemberitahuan apa pun.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
        
        @if($notifications->{{ __('hasPages())') }}
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-center">
            {{ $notifications->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
