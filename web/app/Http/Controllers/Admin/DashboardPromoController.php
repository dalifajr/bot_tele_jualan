<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardPromo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    protected function promoValidationRules(bool $isUpdate = false): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => ['required', 'string', 'max:30', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'description' => 'nullable|string|max:500',
            'icon' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s\-_]+$/'],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'button_text' => 'required|string|max:50',
            'button_url' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $trimmed = trim((string) $value);
                    $isRelative = str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//');
                    $isHttp = (bool) preg_match('/^https?:\/\/.+/i', $trimmed);
                    if (!$isRelative && !$isHttp) {
                        $fail(__('URL tombol harus diawali dengan /, http://, atau https://.'));
                    }
                },
            ],
            'gradient_theme' => 'required|in:blue,green,cyan,purple,amber,red',
            'order_index' => 'required|integer|min:0|max:999',
            'is_active' => 'sometimes|boolean',
        ];

        if ($isUpdate) {
            $rules['remove_image'] = 'nullable|boolean';
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->promoValidationRules(false));

        $validated['is_active'] = $request->has('is_active');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('promos', 'public');
        }

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
        $validated = $request->validate($this->promoValidationRules(true));

        $validated['is_active'] = $request->has('is_active');

        if ($request->boolean('remove_image')) {
            if ($promo->image) {
                Storage::disk('public')->delete($promo->image);
            }
            $validated['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($promo->image) {
                Storage::disk('public')->delete($promo->image);
            }
            $validated['image'] = $request->file('image')->store('promos', 'public');
        }

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
        if ($promo->image) {
            Storage::disk('public')->delete($promo->image);
        }

        $promo->delete();

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil dihapus.'));
    }
}
