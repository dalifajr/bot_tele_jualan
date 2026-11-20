@extends('errors.layout')

@php
    $botUsername = config('telegram.bot_username');
    $rawMessage = trim((string) (isset($exception) ? $exception->getMessage() : ''));
@endphp

@section('code', '503')
@section('tone_class', 'm3-tone-warning')
@section('icon', 'fas fa-screwdriver-wrench')
@section('badge_label', __('Pemeliharaan'))
@section('title', __('Layanan Tidak Tersedia'))
@section('heading', __('Layanan Sedang Pemeliharaan'))

@section('message')
    {{ $rawMessage !== '' ? $rawMessage : __('Website sedang dalam pemeliharaan terjadwal untuk peningkatan kualitas layanan. Silakan kembali beberapa saat lagi.') }}
@endsection

@section('actions')
    <button type="button" class="m3-btn m3-btn-primary" onclick="window.location.reload();">
        <i class="fas fa-rotate-right"></i>
        <span>{{ __('Muat Ulang Halaman') }}</span>
    </button>
    @if($botUsername)
        <a href="https://t.me/{{ ltrim($botUsername, '@') }}" target="_blank" rel="noopener noreferrer" class="m3-btn m3-btn-tonal">
            <i class="fab fa-telegram-plane"></i>
            <span>{{ __('Buka Bot Telegram') }}</span>
        </a>
    @endif
@endsection
