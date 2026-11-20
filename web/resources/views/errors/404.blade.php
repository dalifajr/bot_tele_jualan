@extends('errors.layout')

@php
    $reqPath = request()->getPathInfo() ?: '/';
@endphp

@section('code', '404')
@section('tone_class', 'm3-tone-primary')
@section('icon', 'fas fa-compass')
@section('badge_label', __('Tidak Ditemukan'))
@section('title', __('Halaman Tidak Ditemukan'))
@section('heading', __('Halaman Tidak Ditemukan'))

@section('message')
    {{ __('Maaf, halaman atau produk yang Anda cari tidak tersedia, telah dipindahkan, atau tautannya sudah tidak berlaku.') }}
@endsection

@section('detail_panel')
    <div class="m3-err-detail">
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('URL Diminta') }}</span>
            <span class="m3-err-detail-val m3-mono">{{ \Illuminate\Support\Str::limit($reqPath, 48) }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <a href="{{ url('/') }}" class="m3-btn m3-btn-primary">
        <i class="fas fa-home"></i>
        <span>{{ __('Kembali ke Beranda') }}</span>
    </a>
    <a href="{{ url('/catalog') }}" class="m3-btn m3-btn-tonal">
        <i class="fas fa-store"></i>
        <span>{{ __('Jelajahi Katalog') }}</span>
    </a>
    <button type="button" class="m3-btn m3-btn-outlined" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ url('/') }}'; }">
        <i class="fas fa-arrow-left"></i>
        <span>{{ __('Halaman Sebelumnya') }}</span>
    </button>
@endsection
