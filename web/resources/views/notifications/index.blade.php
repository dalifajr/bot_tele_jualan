@extends('layouts.app')

@section('title', __('Pusat Notifikasi'))

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
                       class="list-group-item list-group-item-action py-2.5 px-3 px-md-3.5 {{ $notification->read_at ? 'bg-light text-muted' : 'bg-white' }} transition-all"
                       onclick="markSingleNotificationAsRead('{{ $notification->id }}')"
                       style="border-left: 3.5px solid {{ $notification->read_at ? 'transparent' : 'var(--bs-primary)' }};">
                        
                        <div class="d-flex w-100 justify-content-between align-items-start gap-2.5">
                            <div class="d-flex gap-2.5 align-items-start min-w-0">
                                <div class="icon-container rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" 
                                     style="width: 36px; height: 36px; min-width: 36px; background-color: rgba(var(--bs-{{ str_contains($notification->data['icon'] ?? '', 'danger') ? 'danger' : (str_contains($notification->data['icon'] ?? '', 'success') ? 'success' : (str_contains($notification->data['icon'] ?? '', 'warning') ? 'warning' : 'primary')) }}-rgb), 0.12);">
                                    <i class="{{ $notification->data['icon'] ?? 'fas fa-bell text-primary' }}" style="font-size: 0.95rem;"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <h6 class="mb-0.5 fw-bold text-truncate {{ $notification->read_at ? 'text-secondary' : 'text-dark' }}" style="font-size: 0.88rem;">
                                        {{ $notification->data['title'] ?? 'Notifikasi' }}
                                    </h6>
                                    <p class="mb-1 {{ $notification->read_at ? 'text-muted' : 'text-body' }}" style="font-size: 0.83rem; line-height: 1.38;">
                                        {{ $notification->data['message'] ?? '' }}
                                    </p>
                                    
                                    @if(($notification->data['type'] ?? '') === 'login_gagal' && isset($notification->data['ip_address']))
                                    <div class="my-1.5" onclick="event.stopPropagation(); event.preventDefault();">
                                        <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="ip_address" value="{{ $notification->data['ip_address'] }}">
                                            <button type="submit" class="btn btn-xs btn-danger rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                                <i class="fas fa-ban me-1"></i>Bukan Saya (Blokir IP)
                                            </button>
                                        </form>
                                    </div>
                                    @endif

                                    <small class="text-muted opacity-75" style="font-size: 0.73rem;">
                                        <i class="far fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                                    </small>
                                </div>
                            </div>
                            
                            @if(!$notification->read_at)
                                <span class="badge bg-primary rounded-pill flex-shrink-0" style="font-size: 0.65rem; padding: 0.25rem 0.55rem;">{{ __('Baru') }}</span>
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
        
        @if($notifications->hasPages())
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-center">
            {{ $notifications->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
