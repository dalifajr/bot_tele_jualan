@extends('errors.layout')

@section('code', '419')
@section('tone_class', 'm3-tone-warning')
@section('icon', 'fas fa-hourglass-end')
@section('badge_label', __('Sesi Kedaluwarsa'))
@section('title', __('Sesi Halaman Kedaluwarsa'))
@section('heading', __('Sesi Halaman Kedaluwarsa'))

@section('message')
    {{ __('Token keamanan formulir telah habis masa berlakunya karena halaman terlalu lama terbuka. Muat ulang halaman dan coba kembali.') }}
@endsection

@section('actions')
    <button type="button" class="m3-btn m3-btn-primary" onclick="window.location.reload();">
        <i class="fas fa-rotate-right"></i>
        <span>{{ __('Muat Ulang Halaman') }}</span>
    </button>
    <a href="{{ url('/login') }}" class="m3-btn m3-btn-tonal">
        <i class="fas fa-sign-in-alt"></i>
        <span>{{ __('Masuk Ulang') }}</span>
    </a>
@endsection
