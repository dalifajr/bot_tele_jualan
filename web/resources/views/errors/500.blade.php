@extends('errors.layout')

@php
    $botUsername = config('telegram.bot_username');
    $incidentId = 'ERR-' . now()->format('Ymd-His');
@endphp

@section('code', '500')
@section('tone_class', 'm3-tone-error')
@section('icon', 'fas fa-server')
@section('badge_label', __('Kesalahan Server'))
@section('title', __('Kesalahan Internal Server'))
@section('heading', __('Terjadi Kendala di Server'))

@section('message')
    {{ __('Maaf, server sedang mengalami gangguan tak terduga saat memproses permintaan Anda. Tim kami sedang menanganinya — silakan muat ulang beberapa saat lagi.') }}
@endsection

@section('detail_panel')
    <div class="m3-err-detail">
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('Kode Referensi') }}</span>
            <span class="m3-err-detail-val m3-mono">{{ $incidentId }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <button type="button" class="m3-btn m3-btn-primary" onclick="window.location.reload();">
        <i class="fas fa-rotate-right"></i>
        <span>{{ __('Coba Muat Ulang') }}</span>
    </button>
    <a href="{{ url('/') }}" class="m3-btn m3-btn-tonal">
        <i class="fas fa-home"></i>
        <span>{{ __('Kembali ke Beranda') }}</span>
    </a>
    @if($botUsername)
        <a href="https://t.me/{{ ltrim($botUsername, '@') }}" target="_blank" rel="noopener noreferrer" class="m3-btn m3-btn-outlined">
            <i class="fab fa-telegram-plane"></i>
            <span>{{ __('Laporkan ke Admin') }}</span>
        </a>
    @endif
@endsection
