<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardPromo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardPromoController extends Controller
{
    public function index()
    {
        $promos = DashboardPromo::ordered()->get();
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
            'order_index' => 'nullable|integer|min:0|max:999',
            'placement' => 'nullable|in:top,bottom',
            'is_active' => 'sometimes|boolean',
        ];

        if ($isUpdate) {
            $rules['remove_image'] = 'nullable|boolean';
        }

        return $rules;
    }

    /**
     * Normalize all promo order_index values into a clean 1..N sequence.
     */
    protected function normalizeOrderIndexes(): void
    {
        $all = DashboardPromo::ordered()->lockForUpdate()->get(['id', 'order_index']);
        foreach ($all as $idx => $item) {
            $expected = $idx + 1;
            if ((int) $item->order_index !== $expected) {
                DashboardPromo::where('id', $item->id)->update(['order_index' => $expected]);
            }
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->promoValidationRules(false));

        $placement = $validated['placement'] ?? 'bottom';
        unset($validated['placement']);

        $validated['is_active'] = $request->has('is_active');

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('promos', 'public');
        }

        DB::transaction(function () use ($validated, $placement) {
            $hasExplicitOrder = isset($validated['order_index']) && $validated['order_index'] !== '';

            if (!$hasExplicitOrder) {
                $this->normalizeOrderIndexes();
                if ($placement === 'top') {
                    DashboardPromo::query()->increment('order_index');
                    $validated['order_index'] = 1;
                } else {
                    $maxOrder = (int) (DashboardPromo::max('order_index') ?? 0);
                    $validated['order_index'] = $maxOrder + 1;
                }
            }

            DashboardPromo::create($validated);

            if (!$hasExplicitOrder) {
                $this->normalizeOrderIndexes();
            }
        });

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil ditambahkan.'));
    }

    public function edit(DashboardPromo $promo)
    {
        $orderedIds = DashboardPromo::ordered()->pluck('id')->all();
        $posIndex = array_search($promo->id, $orderedIds, true);
        $currentPosition = $posIndex === false ? 1 : ($posIndex + 1);
        $totalPromos = count($orderedIds);

        return view('admin.promos.edit', compact('promo', 'currentPosition', 'totalPromos'));
    }

    public function update(Request $request, DashboardPromo $promo)
    {
        $validated = $request->validate($this->promoValidationRules(true));

        unset($validated['placement']);
        if (!array_key_exists('order_index', $validated) || $validated['order_index'] === null || $validated['order_index'] === '') {
            unset($validated['order_index']);
        }

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

    public function move(Request $request, DashboardPromo $promo)
    {
        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $direction = $validated['direction'];

        $orderedIds = DB::transaction(function () use ($promo, $direction) {
            $items = DashboardPromo::ordered()->lockForUpdate()->get(['id', 'order_index'])->values();
            $currentIndex = $items->search(fn ($item) => (int) $item->id === (int) $promo->id);

            if ($currentIndex !== false) {
                $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

                if ($targetIndex >= 0 && $targetIndex < $items->count()) {
                    $ids = $items->pluck('id')->all();
                    $temp = $ids[$currentIndex];
                    $ids[$currentIndex] = $ids[$targetIndex];
                    $ids[$targetIndex] = $temp;

                    foreach ($ids as $pos => $id) {
                        DashboardPromo::where('id', $id)->update(['order_index' => $pos + 1]);
                    }

                    return $ids;
                }
            }

            // Still normalize even at boundary so legacy duplicate order_index gets cleaned up
            $ids = $items->pluck('id')->all();
            foreach ($ids as $pos => $id) {
                DashboardPromo::where('id', $id)->update(['order_index' => $pos + 1]);
            }

            return $ids;
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Urutan promo berhasil diperbarui.'),
                'ordered_ids' => $orderedIds,
            ]);
        }

        return redirect()->route('admin.promos.index')
            ->with('success', __('Urutan promo berhasil diperbarui.'));
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|distinct|exists:dashboard_promos,id',
        ]);

        $submittedIds = array_map('intval', $validated['ordered_ids']);

        $finalIds = DB::transaction(function () use ($submittedIds) {
            $existingIds = DashboardPromo::ordered()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $remainingIds = array_values(array_diff($existingIds, $submittedIds));
            $mergedIds = array_values(array_merge($submittedIds, $remainingIds));

            foreach ($mergedIds as $pos => $id) {
                DashboardPromo::where('id', $id)->update(['order_index' => $pos + 1]);
            }

            return $mergedIds;
        });

        return response()->json([
            'status' => 'success',
            'message' => __('Urutan promo berhasil disimpan.'),
            'ordered_ids' => $finalIds,
        ]);
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

        DB::transaction(function () use ($promo) {
            $promo->delete();
            $this->normalizeOrderIndexes();
        });

        return redirect()->route('admin.promos.index')
            ->with('success', __('Kartu promo dashboard berhasil dihapus.'));
    }
}
