@extends('Admin.layout.master')

@section('title', 'تعديل المنتج: ' . (is_array($product->name) ? ($product->name['ar'] ?? reset($product->name)) : $product->name))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">تعديل المنتج</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">المنتجات</a></li>
                    <li class="breadcrumb-item active">{{ is_array($product->name) ? ($product->name['ar'] ?? reset($product->name)) : $product->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products.show', $product->id) }}" class="btn btn-outline-info">
                <i class="ti ti-eye me-1"></i> تفاصيل المنتج والأكواد
            </a>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-right me-1"></i> رجوع للقائمة
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="alert-heading mb-1"><i class="ti ti-alert-circle me-1"></i> يرجى مراجعة الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        {{-- ProductController update is POST on /admin/products/update/{id} as per routes --}}

        @php
            $nameAr = old('name.ar', is_array($product->name) ? ($product->name['ar'] ?? '') : (method_exists($product, 'getTranslation') ? $product->getTranslation('name', 'ar') : ''));
            $nameEn = old('name.en', is_array($product->name) ? ($product->name['en'] ?? '') : (method_exists($product, 'getTranslation') ? $product->getTranslation('name', 'en') : ''));
            $descAr = old('description.ar', is_array($product->description) ? ($product->description['ar'] ?? '') : (method_exists($product, 'getTranslation') ? $product->getTranslation('description', 'ar') : ''));
            $descEn = old('description.en', is_array($product->description) ? ($product->description['en'] ?? '') : (method_exists($product, 'getTranslation') ? $product->getTranslation('description', 'en') : ''));
            $hasDiscount = old('has_discount', $product->has_discount || ($product->discount && $product->discount->is_active));
            $discountType = old('discount_type', $product->discount?->discount_type ?? 'percentage');
            $discountValue = old('discount_value', $product->discount?->discount_value ?? 0);
        @endphp

        <div class="row">
            <div class="col-lg-8">
                <!-- بيانات المنتج والاسم -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">بيانات المنتج</h5>
                    </div>
                    <div class="card-body pt-3">
                        <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#prod-lang-ar" role="tab">
                                    العربية (الأساسية) <span class="text-danger">*</span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#prod-lang-en" role="tab">
                                    English (اختياري)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content border-0 p-0">
                            <!-- العربية -->
                            <div class="tab-pane fade show active" id="prod-lang-ar" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label" for="name_ar">اسم المنتج بالعربية <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name.ar') is-invalid @enderror" id="name_ar" name="name[ar]" value="{{ $nameAr }}" required placeholder="مثال: بطاقة بلايستيشن سعودي 50$">
                                    @error('name.ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="desc_ar">وصف المنتج بالعربية</label>
                                    <textarea class="form-control" id="desc_ar" name="description[ar]" rows="4" placeholder="اكتب تفاصيل ومميزات المنتج">{{ $descAr }}</textarea>
                                </div>
                            </div>

                            <!-- الإنجليزية -->
                            <div class="tab-pane fade" id="prod-lang-en" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label" for="name_en">Product Name (English)</label>
                                    <input type="text" class="form-control" id="name_en" name="name[en]" value="{{ $nameEn }}" placeholder="e.g. PlayStation Saudi Card 50$">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="desc_en">Product Description (English)</label>
                                    <textarea class="form-control" id="desc_en" name="description[en]" rows="4" placeholder="Product description and details">{{ $descEn }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- التسعير والخصومات -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">التسعير والخصومات</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="price">السعر الأساسي <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price', $product->price) }}" required>
                                    <span class="input-group-text">{{ config('commerce.base_currency', 'ر.س') }}</span>
                                </div>
                                @error('price')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="sku">رمز المنتج (SKU)</label>
                                <input type="text" class="form-control @error('sku') is-invalid @enderror" id="sku" name="sku" value="{{ old('sku', $product->sku) }}">
                                @error('sku')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- إعدادات الخصم -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="has_discount" name="has_discount" value="1" @checked($hasDiscount) onchange="toggleDiscountFields(this)">
                            <label class="form-check-label fw-semibold" for="has_discount">تفعيل خصم على هذا المنتج</label>
                        </div>

                        <div id="discount_fields" class="row bg-label-secondary p-3 rounded mb-2" style="{{ $hasDiscount ? '' : 'display: none;' }}">
                            <div class="col-md-6 mb-2">
                                <label class="form-label" for="discount_type">نوع الخصم</label>
                                <select class="form-select" id="discount_type" name="discount_type">
                                    <option value="percentage" @selected($discountType == 'percentage')>نسبة مئوية (%)</option>
                                    <option value="fixed" @selected($discountType == 'fixed')>مبلغ ثابت</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label" for="discount_value">قيمة الخصم</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="discount_value" name="discount_value" value="{{ $discountValue }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- التسليم الرقمي والأكواد -->
                @include('Admin.product._fulfillment_fields', ['product' => $product])
            </div>

            <!-- العمود الجانبي: القسم، المخزون، والصور -->
            <div class="col-lg-4">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">القسم والمخزون</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="form-label" for="category_id">القسم <span class="text-danger">*</span></label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>
                                        {{ is_array($cat->name) ? ($cat->name['ar'] ?? reset($cat->name)) : $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="stock">الكمية بالمخزون <span class="text-danger">*</span></label>
                            <input type="number" min="0" class="form-control @error('stock') is-invalid @enderror" id="stock" name="stock" value="{{ old('stock', $product->stock) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="status_id">حالة المنتج</label>
                            <select class="form-select" id="status_id" name="status_id" required>
                                <option value="1" @selected(old('status_id', $product->status_id) == 1)>نشط (متاح للشراء)</option>
                                <option value="2" @selected(old('status_id', $product->status_id) == 2)>معطل (مخفي)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- صورة المنتج -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">صورة المنتج</h5>
                    </div>
                    <div class="card-body pt-3 text-center">
                        <div class="mb-3">
                            @php
                                $mainImg = $product->primaryImage?->path ?? $product->image_path;
                                $imgSrc = $mainImg ? asset('storage/' . $mainImg) : asset('dashboard/assets/img/illustrations/page-pricing-standard.png');
                            @endphp
                            <img id="main_image_preview" src="{{ $imgSrc }}" alt="Product Image" class="rounded mb-2" style="max-height: 140px; max-width: 100%; object-fit: contain; border: 1px dashed #d9dee3; padding: 5px;">
                            <input class="form-control" type="file" id="image" name="image" accept="image/*" onchange="previewImage(this, 'main_image_preview')">
                            <div class="form-text">تغيير الصورة الأساسية للمنتج</div>
                        </div>

                        <div class="border-top pt-3 text-start">
                            <label class="form-label" for="additional_images">إضافة صور للمعرض</label>
                            <input class="form-control" type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                        </div>
                    </div>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="ti ti-device-floppy me-1"></i> حفظ التعديلات
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
                        إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('js')
<script>
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleDiscountFields(checkbox) {
    const fields = document.getElementById('discount_fields');
    if (checkbox.checked) {
        fields.style.display = '';
    } else {
        fields.style.display = 'none';
    }
}
</script>
@endsection
