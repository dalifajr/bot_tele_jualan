@extends('layouts.app')

@section('title', 'Manajemen Produk')
@section('page_subtitle', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Produk</h4>
        <p class="text-muted mb-0">Kelola katalog produk digital</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($products->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">ID</th>
                        <th class="py-3 border-0">Nama Produk</th>
                        <th class="py-3 border-0">Harga</th>
                        <th class="py-3 border-0">Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td class="px-4 fw-bold text-muted">#{{ $product->id }}</td>
                        <td class="fw-bold text-primary">{{ $product->name }}</td>
                        <td>{{ $product->formatted_price }}</td>
                        <td class="text-secondary small">{{ $product->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $products->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-box text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada produk.</p>
        </div>
        @endif
    </div>
</div>
@endsection
