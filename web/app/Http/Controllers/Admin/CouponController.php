<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
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
        $coupons = Coupon::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.coupons.index', compact('coupons'));
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
        ]);

        // Standardize coupon code to uppercase
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;
        
        $coupon = Coupon::create($validated);

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
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->has('is_active') ? true : false;

        $coupon->update($validated);

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
