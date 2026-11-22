<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    /**
     * Display a listing of coupons created by the authenticated seller.
     */
    public function index()
    {
        $sellerId = Auth::id();

        $coupons = Coupon::with('products')
            ->where('user_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $products = Product::where('creator_id', $sellerId)
            ->where('is_suspended', false)
            ->orderBy('name', 'asc')
            ->get();

        $totalCoupons = Coupon::where('user_id', $sellerId)->count();
        $activeCoupons = Coupon::where('user_id', $sellerId)->where('is_active', true)->count();
        $usedQty = Coupon::where('user_id', $sellerId)->sum('used_qty');

        return view('seller.coupons.index', compact(
            'coupons',
            'products',
            'totalCoupons',
            'activeCoupons',
            'usedQty'
        ));
    }

    /**
     * Store a newly created coupon for the authenticated seller.
     */
    public function store(Request $request)
    {
        $sellerId = Auth::id();

        $valueRules = ['required', 'integer', 'min:1'];
        if ($request->input('type') === 'percent') {
            $valueRules[] = 'max:100';
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => $valueRules,
            'min_spend' => 'required|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'qty' => 'required|integer|min:0',
            'allow_multiple_uses' => 'nullable|boolean',
            'max_uses_per_user' => 'nullable|integer|min:0|max:10000',
            'apply_mode' => 'nullable|in:per_order,per_item',
            'apply_per_item' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'scope' => 'required|in:all,specific',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        // Security check: verify that all selected products belong to this seller
        if ($request->input('scope') === 'specific' && !empty($request->input('product_ids'))) {
            $validCount = Product::where('creator_id', $sellerId)
                ->whereIn('id', $request->input('product_ids'))
                ->count();

            if ($validCount !== count($request->input('product_ids'))) {
                return back()->withErrors([
                    'product_ids' => __('Anda hanya dapat memilih produk milik toko Anda sendiri.')
                ])->withInput();
            }
        }

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;
        $allowMultiple = $request->boolean('allow_multiple_uses');
        $validated['allow_multiple_uses'] = $allowMultiple;
        $rawPerUser = isset($validated['max_uses_per_user']) ? (int) $validated['max_uses_per_user'] : 0;
        $validated['max_uses_per_user'] = ($allowMultiple && $rawPerUser > 0) ? $rawPerUser : null;
        if (!empty($validated['apply_mode'])) {
            $validated['apply_mode'] = $validated['apply_mode'];
        } else {
            $validated['apply_mode'] = $request->boolean('apply_per_item')
                ? Coupon::APPLY_PER_ITEM
                : Coupon::APPLY_PER_ORDER;
        }
        unset($validated['apply_per_item']);
        $validated['user_id'] = $sellerId;

        $coupon = Coupon::create($validated);

        if ($request->input('scope') === 'specific' && !empty($request->input('product_ids'))) {
            $coupon->products()->sync($request->input('product_ids'));
        }

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $sellerId,
                'action' => 'seller_coupon_create',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'detail' => "Seller created coupon {$coupon->code} with value {$coupon->value}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('seller.coupons.index')->with('success', __('Kupon toko Anda berhasil dibuat.'));
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(Request $request, $id)
    {
        $sellerId = Auth::id();
        $coupon = Coupon::where('user_id', $sellerId)->findOrFail($id);

        $valueRules = ['required', 'integer', 'min:1'];
        if ($request->input('type') === 'percent') {
            $valueRules[] = 'max:100';
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:coupons,code,' . $coupon->id,
            'type' => 'required|in:fixed,percent',
            'value' => $valueRules,
            'min_spend' => 'required|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'qty' => 'required|integer|min:0',
            'allow_multiple_uses' => 'nullable|boolean',
            'max_uses_per_user' => 'nullable|integer|min:0|max:10000',
            'apply_mode' => 'nullable|in:per_order,per_item',
            'apply_per_item' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'scope' => 'required|in:all,specific',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        // Security check: verify that all selected products belong to this seller
        if ($request->input('scope') === 'specific' && !empty($request->input('product_ids'))) {
            $validCount = Product::where('creator_id', $sellerId)
                ->whereIn('id', $request->input('product_ids'))
                ->count();

            if ($validCount !== count($request->input('product_ids'))) {
                return back()->withErrors([
                    'product_ids' => __('Anda hanya dapat memilih produk milik toko Anda sendiri.')
                ])->withInput();
            }
        }

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;
        $allowMultiple = $request->boolean('allow_multiple_uses');
        $validated['allow_multiple_uses'] = $allowMultiple;
        $rawPerUser = isset($validated['max_uses_per_user']) ? (int) $validated['max_uses_per_user'] : 0;
        $validated['max_uses_per_user'] = ($allowMultiple && $rawPerUser > 0) ? $rawPerUser : null;
        if (!empty($validated['apply_mode'])) {
            $validated['apply_mode'] = $validated['apply_mode'];
        } else {
            $validated['apply_mode'] = $request->boolean('apply_per_item')
                ? Coupon::APPLY_PER_ITEM
                : Coupon::APPLY_PER_ORDER;
        }
        unset($validated['apply_per_item']);

        $coupon->update($validated);

        if ($request->input('scope') === 'specific' && !empty($request->input('product_ids'))) {
            $coupon->products()->sync($request->input('product_ids'));
        } else {
            $coupon->products()->detach();
        }

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $sellerId,
                'action' => 'seller_coupon_update',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'detail' => "Seller updated coupon {$coupon->code}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('seller.coupons.index')->with('success', __('Kupon toko Anda berhasil diperbarui.'));
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy($id)
    {
        $sellerId = Auth::id();
        $coupon = Coupon::where('user_id', $sellerId)->findOrFail($id);
        $code = $coupon->code;

        $coupon->products()->detach();
        $coupon->delete();

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $sellerId,
                'action' => 'seller_coupon_delete',
                'entity_type' => 'coupon',
                'entity_id' => 0,
                'detail' => "Seller deleted coupon {$code}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        return redirect()->route('seller.coupons.index')->with('success', __('Kupon berhasil dihapus.'));
    }
}
