<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            // =============================================================
            // الحقول متعددة اللغات - كمصفوفات
            // =============================================================
            'name' => 'required|array',
            'name.ar' => 'required|string|max:255',
            'name.*' => 'nullable|string|max:255',

            'description' => 'nullable|array',
            'description.ar' => 'nullable|string',
            'description.*' => 'nullable|string',

            'price_text' => 'nullable|array',
            'price_text.ar' => 'nullable|string|max:255',
            'price_text.*' => 'nullable|string|max:255',

            'meta_title' => 'nullable|array',
            'meta_title.ar' => 'nullable|string|max:255',
            'meta_title.*' => 'nullable|string|max:255',

            'meta_description' => 'nullable|array',
            'meta_description.ar' => 'nullable|string|max:500',
            'meta_description.*' => 'nullable|string|max:500',

            'meta_keywords' => 'nullable|array',
            'meta_keywords.ar' => 'nullable|string|max:255',
            'meta_keywords.*' => 'nullable|string|max:255',

            // =============================================================
            // الحقول الأساسية
            // =============================================================
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'status_id' => 'required|in:1,2,3',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $this->route('id'),
            'sign_currency' => 'nullable|string|max:20',
            'subtitle' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:150',
            'region' => 'nullable|string|max:150',
            'denomination' => 'nullable|string|max:100',
            'validity_text' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',
            'is_best_seller' => 'nullable|boolean',
            'type' => 'nullable|in:product,subscription,gift_card,digital_code,service',
            'inventory_type' => 'nullable|in:physical,digital,service',
            'track_stock' => 'nullable|boolean',
            'max_quantity' => 'nullable|integer|min:0|max:100000',
            'instructions' => 'nullable|string|max:20000',
            'terms' => 'nullable|string|max:20000',
            'url' => 'nullable|url|max:500',
            'external_id' => 'nullable|string|max:255',
            'fulfillment_source' => 'nullable|in:none,local_serials,like4app',
            'fulfillment_mode' => 'nullable|in:automatic,manual',
            'allow_serial_backorder' => 'nullable|boolean',
            'includes_shipping' => 'nullable|boolean',
            'serials' => 'nullable|array|max:500',
            'serials.*.code' => 'required_with:serials|string|max:1000|distinct',
            'serials.*.pin' => 'nullable|string|max:500',
            'serials.*.expires_at' => 'nullable|date',
            'serials.*.notes' => 'nullable|string|max:1000',

            // =============================================================
            // الصور
            // =============================================================
            'image' => ($this->isMethod('post') && !$this->route('id') ? 'required' : 'nullable') . '|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'delete_image' => 'nullable|boolean',

            'additional_images' => 'nullable|array',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'removed_images' => 'nullable|string',

            // =============================================================
            // الخصم
            // =============================================================
            'has_discount' => 'nullable|boolean',
            'discount_type' => 'nullable|required_if:has_discount,1|in:percentage,fixed',
            'discount_value' => 'nullable|required_if:has_discount,1|numeric|min:0',

            // =============================================================
            // خيارات إضافية
            // =============================================================
            'includes_tax' => 'nullable|boolean',

            // =============================================================
            // العروض
            // =============================================================
            'offers' => 'nullable|array',
            'offers.*' => 'exists:offers,id',

            // =============================================================
            // النصوص الإعلانية
            // =============================================================
            'text_ads' => 'nullable|array',
            'text_ads.*.name' => 'nullable|string|max:500',
        ];

        // قواعد إضافية للغات الأخرى (اختياري - يمكن إضافتها ديناميكياً)
        $languages = \App\Models\Language::where('is_active', true)
            ->where('code', '!=', 'ar')
            ->get();

        foreach ($languages as $language) {
            // إضافة قواعد للنصوص الإعلانية لكل لغة
            $rules["text_ads.*.{$language->code}"] = 'nullable|string|max:500';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->boolean('has_discount')) {
                $type = $this->input('discount_type');
                $value = (float) $this->input('discount_value', 0);
                $price = (float) $this->input('price', 0);
                if ($type === 'percentage' && $value > 100) {
                    $validator->errors()->add('discount_value', 'نسبة الخصم لا يمكن أن تتجاوز 100%.');
                }
                if ($type === 'fixed' && $value > $price) {
                    $validator->errors()->add('discount_value', 'الخصم الثابت لا يمكن أن يكون أكبر من سعر المنتج.');
                }
            }
            if ($this->input('fulfillment_source') === 'like4app' && !$this->filled('external_id')) {
                $validator->errors()->add('external_id', 'Provider Product ID مطلوب عند اختيار Like4App.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            // الاسم
            'name.required' => 'حقل الاسم مطلوب',
            'name.array' => 'حقل الاسم يجب أن يكون مصفوفة',
            'name.ar.required' => 'الاسم بالعربية مطلوب',
            'name.ar.max' => 'الاسم بالعربية لا يجب أن يتجاوز 255 حرف',
            'name.*.max' => 'الاسم في إحدى اللغات تجاوز 255 حرف',

            // الوصف
            'description.*.string' => 'الوصف يجب أن يكون نصاً',

            // نص السعر
            'price_text.*.max' => 'نص السعر في إحدى اللغات تجاوز 255 حرف',

            // الحقول الأساسية
            'category_id.required' => 'حقل القسم مطلوب',
            'category_id.exists' => 'القسم المحدد غير موجود',
            'price.required' => 'حقل السعر مطلوب',
            'price.numeric' => 'السعر يجب أن يكون رقماً',
            'price.min' => 'السعر يجب أن يكون أكبر من أو يساوي 0',
            'stock.required' => 'حقل الكمية مطلوب',
            'stock.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
            'stock.min' => 'الكمية يجب أن تكون أكبر من أو يساوي 0',
            'status_id.required' => 'حقل الحالة مطلوب',
            'status_id.in' => 'قيمة الحالة غير صالحة',
            'sku.unique' => 'رمز المنتج (SKU) مستخدم بالفعل',

            // الصور
            'image.image' => 'الملف يجب أن يكون صورة',
            'image.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, gif, webp',
            'image.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',
            'additional_images.*.image' => 'جميع الملفات يجب أن تكون صوراً',
            'additional_images.*.mimes' => 'الصور الإضافية يجب أن تكون من نوع: jpeg, png, jpg, gif, webp',
            'additional_images.*.max' => 'حجم كل صورة إضافية لا يجب أن يتجاوز 5 ميجابايت',

            // الخصم
            'discount_type.required_if' => 'نوع الخصم مطلوب عند تفعيل الخصم',
            'discount_type.in' => 'نوع الخصم يجب أن يكون نسبة مئوية أو قيمة ثابتة',
            'discount_value.required_if' => 'قيمة الخصم مطلوبة عند تفعيل الخصم',
            'discount_value.numeric' => 'قيمة الخصم يجب أن تكون رقماً',
            'discount_value.min' => 'قيمة الخصم يجب أن تكون أكبر من أو تساوي 0',

            // العروض
            'offers.array' => 'العروض يجب أن تكون مصفوفة',
            'offers.*.exists' => 'أحد العروض المحددة غير موجود',

            // النصوص الإعلانية
            'text_ads.array' => 'النصوص الإعلانية يجب أن تكون مصفوفة',
            'text_ads.*.name.max' => 'النص الإعلاني لا يجب أن يتجاوز 500 حرف',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // تحويل القيم المنطقية إلى boolean
        $this->merge([
            'has_discount' => $this->boolean('has_discount'),
            'includes_tax' => $this->boolean('includes_tax'),
            'delete_image' => $this->boolean('delete_image'),
            'track_stock' => $this->boolean('track_stock'),
            'allow_serial_backorder' => $this->boolean('allow_serial_backorder'),
            'includes_shipping' => $this->boolean('includes_shipping'),
            'is_featured' => $this->boolean('is_featured'),
            'is_best_seller' => $this->boolean('is_best_seller'),
        ]);

        // التأكد من أن الحقول المصفوفية موجودة كصفيفات حتى لو كانت فارغة
        $arrayFields = ['name', 'description', 'price_text', 'meta_title', 'meta_description', 'meta_keywords', 'offers', 'text_ads'];

        foreach ($arrayFields as $field) {
            if ($this->has($field) && !is_array($this->get($field))) {
                $this->merge([$field => []]);
            }
        }

        // Empty repeater rows from the dashboard are UI placeholders, not serial records.
        if (is_array($this->input('serials'))) {
            $serials = array_values(array_filter($this->input('serials'), function ($row) {
                return is_array($row) && trim((string)($row['code'] ?? '')) !== '';
            }));
            $this->merge(['serials' => $serials]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name.ar' => 'الاسم بالعربية',
            'name.en' => 'الاسم بالإنجليزية',
            'description.ar' => 'الوصف بالعربية',
            'price_text.ar' => 'نص السعر بالعربية',
            'category_id' => 'القسم',
            'price' => 'السعر',
            'stock' => 'الكمية',
            'status_id' => 'الحالة',
            'sku' => 'رمز المنتج',
            'image' => 'الصورة الرئيسية',
            'additional_images' => 'الصور الإضافية',
            'has_discount' => 'يحتوي على خصم',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'includes_tax' => 'يشمل الضريبة',
            'offers' => 'العروض',
            'text_ads' => 'النصوص الإعلانية',
        ];
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation()
    {
        // تسجيل بيانات التحقق إذا كنت بحاجة لذلك
        if (config('app.debug')) {
            Log::info('Product validation passed', [
                'data' => $this->validated()
            ]);
        }
    }
}
