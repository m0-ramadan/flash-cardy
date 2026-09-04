<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::latest()->paginate(10);

        return view('Admin.payment-methods.index', compact('paymentMethods'));
    }

    public function create()
    {
        return view('Admin.payment-methods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255|unique:payment_methods,key',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'icon_class' => ['nullable', 'string', 'max:255', 'regex:/^(fas|far|fab|fal|fad|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+$/i'],
            'is_active' => 'boolean',
            'is_payment' => 'boolean',
            'is_wallet' => 'boolean',
        ]);

        // معالجة رفع الصورة
        if ($request->hasFile('icon')) {
            $fileName = time().'_'.$request->icon->getClientOriginalName();
            $request->icon->storeAs('payment-methods', $fileName, 'public');
            $validated['icon'] = $fileName;
        } elseif ($request->filled('icon_class')) {
            $validated['icon'] = trim($request->input('icon_class'));
        }

        unset($validated['icon_class']);

        // تعيين القيم الافتراضية
        $validated['is_active'] = $request->has('is_active');
        $validated['is_payment'] = $request->has('is_payment');
        $validated['is_wallet'] = $request->has('is_wallet');

        PaymentMethod::create($validated);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'تم إضافة وسيلة الدفع بنجاح.');
    }

    public function show(PaymentMethod $paymentMethod)
    {
        return view('Admin.payment-methods.show', compact('paymentMethod'));
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('Admin.payment-methods.edit', compact('paymentMethod'));
    }

    /**
     * Update the specified payment method in storage.
     */
    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        // التحقق من صحة البيانات
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255|unique:payment_methods,key,'.$paymentMethod->id,
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'icon_class' => ['nullable', 'string', 'max:255', 'regex:/^(fas|far|fab|fal|fad|fa-solid|fa-regular|fa-brands)\s+fa-[a-z0-9-]+$/i'],
            'is_active' => 'boolean',
            'is_payment' => 'boolean',
            'is_wallet' => 'boolean',
        ]);

        try {
            // معالجة رفع الصورة الجديدة
            if ($request->hasFile('icon')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($paymentMethod->hasImageIcon() && Storage::disk('public')->exists('payment-methods/'.$paymentMethod->icon)) {
                    Storage::disk('public')->delete('payment-methods/'.$paymentMethod->icon);
                }

                // رفع الصورة الجديدة
                $fileName = time().'_'.uniqid().'.'.$request->icon->getClientOriginalExtension();
                $request->icon->storeAs('payment-methods', $fileName, 'public');
                $validated['icon'] = $fileName;
            } elseif ($request->filled('icon_class')) {
                if ($paymentMethod->hasImageIcon() && Storage::disk('public')->exists('payment-methods/'.$paymentMethod->icon)) {
                    Storage::disk('public')->delete('payment-methods/'.$paymentMethod->icon);
                }

                $validated['icon'] = trim($request->input('icon_class'));
            } else {
                // الاحتفاظ بالصورة القديمة إذا لم يتم رفع صورة جديدة
                unset($validated['icon']);
            }

            unset($validated['icon_class']);
            $validated['is_active'] = $request->has('is_active');
            $validated['is_payment'] = $request->has('is_payment');
            $validated['is_wallet'] = $request->has('is_wallet');

            // تحديث وسيلة الدفع
            $paymentMethod->update($validated);

            return redirect()->route('admin.payment-methods.index')
                ->with('success', 'تم تحديث وسيلة الدفع بنجاح.');
        } catch (\Exception $e) {
            Log::error('Error updating payment method: '.$e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'حدث خطأ أثناء تحديث وسيلة الدفع.');
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->hasImageIcon() && Storage::disk('public')->exists('payment-methods/'.$paymentMethod->icon)) {
            Storage::disk('public')->delete('payment-methods/'.$paymentMethod->icon);
        }

        $paymentMethod->delete();

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'تم حذف وسيلة الدفع بنجاح.');
    }

    // public function toggleStatus(PaymentMethod $paymentMethod)
    // {
    //     $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);

    //     return redirect()->back()->with('success', 'تم تغيير الحالة بنجاح');
    // }
    /**
     * Toggle payment method status (active/inactive)
     */
    public function toggleStatus(PaymentMethod $paymentMethod)
    {
        try {
            $paymentMethod->update([
                'is_active' => ! $paymentMethod->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير الحالة بنجاح',
                'is_active' => $paymentMethod->is_active,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling payment method status: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير الحالة',
            ], 500);
        }
    }
}
