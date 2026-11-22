<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    /**
     * Display a listing of coupons.
     */
    public function index()
    {
        $coupons = Coupon::with(['creator', 'products'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $products = Product::where('is_suspended', false)
            ->orderBy('name', 'asc')
            ->get();

        return view('admin.coupons.index', compact('coupons', 'products'));
    }

    /**
     * Store a newly created coupon in storage.
     */
    public function store(Request $request)
    {
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

        // Standardize coupon code to uppercase
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
        $validated['user_id'] = Auth::id();

        $coupon = Coupon::create($validated);

        if ($request->input('scope') === 'specific' && $request->has('product_ids')) {
            $coupon->products()->sync($request->input('product_ids', []));
        }

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'coupon_create',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'detail' => "Created coupon {$coupon->code} with value {$coupon->value}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('admin.coupons.index')->with('success', __('Kupon berhasil dibuat.'));
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

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

        if ($request->input('scope') === 'specific') {
            $coupon->products()->sync($request->input('product_ids', []));
        } else {
            $coupon->products()->detach();
        }

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'coupon_update',
                'entity_type' => 'coupon',
                'entity_id' => $coupon->id,
                'detail' => "Updated coupon {$coupon->code}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('admin.coupons.index')->with('success', __('Kupon berhasil diperbarui.'));
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $code = $coupon->code;
        $coupon->products()->detach();
        $coupon->delete();

        // Audit Log
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'coupon_delete',
                'entity_type' => 'coupon',
                'entity_id' => 0,
                'detail' => "Deleted coupon {$code}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        return redirect()->route('admin.coupons.index')->with('success', __('Kupon berhasil dihapus.'));
    }
}
