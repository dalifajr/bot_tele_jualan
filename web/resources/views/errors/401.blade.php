@extends('errors.layout')

@section('code', '401')
@section('tone_class', 'm3-tone-secondary')
@section('icon', 'fas fa-user-lock')
@section('badge_label', __('Autentikasi Diperlukan'))
@section('title', __('Autentikasi Diperlukan'))
@section('heading', __('Masuk Diperlukan'))

@section('message')
    {{ __('Anda perlu masuk ke akun terlebih dahulu untuk mengakses halaman atau sumber daya ini.') }}
@endsection

@section('actions')
    <a href="{{ url('/login') }}" class="m3-btn m3-btn-primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>{{ __('Masuk Sekarang') }}</span>
    </a>
    <a href="{{ url('/') }}" class="m3-btn m3-btn-tonal">
        <i class="fas fa-home"></i>
        <span>{{ __('Kembali ke Beranda') }}</span>
    </a>
@endsection
