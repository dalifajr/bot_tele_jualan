@extends('layouts.app')

@section('title', 'Manajemen Stok')
@section('page_subtitle', 'Stok')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Stok</h4>
        <p class="text-muted mb-0">Kelola stok unit produk digital</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($stockUnits->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">ID</th>
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Konten (Sebagian)</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Ditambahkan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockUnits as $unit)
                    <tr>
                        <td class="px-4 fw-bold text-muted">#{{ $unit->id }}</td>
                        <td>{{ Str::limit($unit->product->name ?? 'Unknown', 25) }}</td>
                        <td><code class="text-dark bg-light px-2 py-1 rounded">{{ Str::limit($unit->content, 20) }}</code></td>
                        <td>
                            @if($unit->is_sold)
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Terjual</span>
                            @else
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Tersedia</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $unit->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $stockUnits->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-cubes text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada stok unit.</p>
        </div>
        @endif
    </div>
</div>
@endsection
