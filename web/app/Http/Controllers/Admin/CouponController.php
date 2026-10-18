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
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|integer|min:1',
            'min_spend' => 'required|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'qty' => 'required|integer|min:0',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'scope' => 'required|in:all,specific',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        // Standardize coupon code to uppercase
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;
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

        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:coupons,code,' . $coupon->id,
            'type' => 'required|in:fixed,percent',
            'value' => 'required|integer|min:1',
            'min_spend' => 'required|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'qty' => 'required|integer|min:0',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'scope' => 'required|in:all,specific',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;

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
