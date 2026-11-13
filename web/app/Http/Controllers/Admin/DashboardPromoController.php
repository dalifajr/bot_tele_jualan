<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardPromo;
use Illuminate\Http\Request;

class DashboardPromoController extends Controller
{
    public function index()
    {
        $promos = DashboardPromo::orderBy('order_index', 'asc')->orderBy('id', 'asc')->paginate(15);
        return view('admin.promos.index', compact('promos'));
    }

    public function create()
    {
        return view('admin.promos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'required|string|max:30',
            'description' => 'nullable|string|max:500',
            'icon' => 'required|string|max:100',
            'button_text' => 'required|string|max:50',
            'button_url' => 'required|string|max:255',
            'gradient_theme' => 'required|in:blue,green,cyan,purple,amber,red',
            'order_index' => 'required|integer|min:0|max:999',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        DashboardPromo::create($validated);

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil ditambahkan.'));
    }

    public function edit(DashboardPromo $promo)
    {
        return view('admin.promos.edit', compact('promo'));
    }

    public function update(Request $request, DashboardPromo $promo)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'required|string|max:30',
            'description' => 'nullable|string|max:500',
            'icon' => 'required|string|max:100',
            'button_text' => 'required|string|max:50',
            'button_url' => 'required|string|max:255',
            'gradient_theme' => 'required|in:blue,green,cyan,purple,amber,red',
            'order_index' => 'required|integer|min:0|max:999',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $promo->update($validated);

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil diperbarui.'));
    }

    public function toggleStatus(DashboardPromo $promo)
    {
        $promo->update(['is_active' => !$promo->is_active]);

        return redirect()->back()
            ->with('success', __('Status promo berhasil diubah.'));
    }

    public function destroy(DashboardPromo $promo)
    {
        $promo->delete();

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil dihapus.'));
    }
}
