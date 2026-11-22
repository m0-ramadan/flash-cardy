@extends('Admin.layout.master')

@section('title', 'إضافة بانر جديد')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إضافة بانر جديد</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.banners.index') }}">البانرات</a></li>
                    <li class="breadcrumb-item active">إضافة بانر</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-right me-1"></i> رجوع للقائمة
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="alert-heading mb-1"><i class="ti ti-alert-circle me-1"></i> يرجى مراجعة الأخطاء:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">بيانات البانر</h5>
                </div>
                <div class="card-body pt-3">
                    <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <!-- نوع البانر -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">مكان ظهور البانر <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @foreach ($bannerTypes as $type)
                                    @php
                                        $isMain = ($type->name === 'main_slider');
                                        $label = $isMain ? 'سلايدر الرئيسية' : 'بانر قسم محدد';
                                        $desc = $isMain ? 'يظهر في السلايدر المتحرك بأعلى الصفحة الرئيسية' : 'يظهر في أعلى صفحة القسم المحدد بالمتجر';
                                        $icon = $isMain ? 'ti-slideshow' : 'ti-category';
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="form-check custom-option custom-option-basic border rounded p-3 h-100">
                                            <input class="form-check-input" type="radio" name="banner_type_id" id="type_{{ $type->id }}" value="{{ $type->id }}" @checked(old('banner_type_id', 1) == $type->id) onchange="toggleCategorySelect({{ $type->id }})">
                                            <label class="form-check-label w-100" for="type_{{ $type->id }}">
                                                <span class="d-flex align-items-center mb-1">
                                                    <i class="ti {{ $icon }} me-2 text-primary fs-4"></i>
                                                    <strong class="text-dark">{{ $label }}</strong>
                                                </span>
                                                <small class="text-muted d-block">{{ $desc }}</small>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- اختيار القسم (يظهر فقط إذا كان البانر لقسم محدد) -->
                        <div class="mb-3" id="category_select_wrapper" style="display: none;">
                            <label class="form-label" for="category_id">القسم المستهدف <span class="text-danger">*</span></label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                                <option value="">-- اختر القسم الذي سيظهر به البانر --</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                                        {{ is_array($cat->name) ? ($cat->name['ar'] ?? reset($cat->name)) : $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">سيظهر هذا البانر للمستخدمين عند تصفح هذا القسم في الموقع.</small>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- عنوان البانر -->
                        <div class="mb-3">
                            <label class="form-label" for="title">عنوان البانر <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required placeholder="مثال: عروض نهاية الأسبوع / بطاقات بلايستيشن">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- صورة البانر مع معاينة فورية -->
                        <div class="mb-3">
                            <label class="form-label" for="image">صورة البانر <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/*" onchange="previewBannerImage(this)">
                            <small class="text-muted">يفضل مقاس عرضي مناسب (مثل 1200×400 لسلايدر الرئيسية، أو 800×300 لبانر القسم).</small>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="mt-2 text-center" id="image_preview_box" style="display: none;">
                                <img id="preview_img" src="#" alt="معاينة الصورة" class="img-fluid rounded border shadow-sm" style="max-height: 200px; width: 100%; object-fit: cover;">
                            </div>
                        </div>

                        <!-- رابط التوجيه (اختياري) -->
                        <div class="mb-3">
                            <label class="form-label" for="link_url">رابط التحويل عند النقر (اختياري)</label>
                            <input type="text" class="form-control @error('link_url') is-invalid @enderror" id="link_url" name="link_url" value="{{ old('link_url') }}" placeholder="مثال: https://... أو /products/...">
                            <small class="text-muted">عند نقر العميل على البانر، سيتم توجيهه إلى هذا الرابط.</small>
                            @error('link_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="section_order">ترتيب الظهور <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="section_order" name="section_order" value="{{ old('section_order', 1) }}" required>
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                                    <label class="form-check-label fw-semibold" for="is_active">تفعيل البانر (نشط الآن)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i> حفظ ونشر البانر
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
function toggleCategorySelect(typeId) {
    const wrapper = document.getElementById('category_select_wrapper');
    const categorySelect = document.getElementById('category_id');
    // typeId 3 = category_slider
    if (typeId == 3) {
        wrapper.style.display = 'block';
    } else {
        wrapper.style.display = 'none';
        if (categorySelect) categorySelect.value = '';
    }
}

function previewBannerImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview_img').src = e.target.result;
            document.getElementById('image_preview_box').style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('input[name="banner_type_id"]:checked');
    if (checkedRadio) {
        toggleCategorySelect(checkedRadio.value);
    }
});
</script>
@endsection
