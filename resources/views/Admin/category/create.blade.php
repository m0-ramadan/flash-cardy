@extends('Admin.layout.master')

@section('title', 'إضافة قسم جديد')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إضافة قسم جديد</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">الأقسام</a></li>
                    <li class="breadcrumb-item active">إضافة قسم</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-right me-1"></i> رجوع للقائمة
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="alert-heading mb-1"><i class="ti ti-alert-circle me-1"></i> يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <!-- تفاصيل القسم الأساسية -->
            <div class="col-lg-8">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">بيانات القسم</h5>
                    </div>
                    <div class="card-body pt-3">
                        <!-- تبويبات اللغات -->
                        <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#cat-lang-ar" role="tab">
                                    العربية (الأساسية) <span class="text-danger">*</span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#cat-lang-en" role="tab">
                                    English (اختياري)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content border-0 p-0">
                            <!-- العربية -->
                            <div class="tab-pane fade show active" id="cat-lang-ar" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label" for="name_ar">اسم القسم بالعربية <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name.ar') is-invalid @enderror" id="name_ar" name="name[ar]" value="{{ old('name.ar') }}" required placeholder="مثال: بطاقات بلايستيشن">
                                    @error('name.ar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="desc_ar">وصف القسم بالعربية</label>
                                    <textarea class="form-control" id="desc_ar" name="description[ar]" rows="3" placeholder="وصف مختصر للقسم يظهر للمستخدمين">{{ old('description.ar') }}</textarea>
                                </div>
                            </div>

                            <!-- الإنجليزية -->
                            <div class="tab-pane fade" id="cat-lang-en" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label" for="name_en">Category Name (English)</label>
                                    <input type="text" class="form-control" id="name_en" name="name[en]" value="{{ old('name.en') }}" placeholder="e.g. PlayStation Cards">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="desc_en">Description (English)</label>
                                    <textarea class="form-control" id="desc_en" name="description[en]" rows="3" placeholder="Brief description of category">{{ old('description.en') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="parent_id">القسم الرئيسي</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">قسم رئيسي (بدون أب)</option>
                                    @foreach ($parentCategories as $parent)
                                        <option value="{{ $parent->id }}" @selected(old('parent_id', request('parent_id', $parentId ?? '')) == $parent->id)>
                                            {{ is_array($parent->name) ? ($parent->name['ar'] ?? reset($parent->name)) : $parent->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">اتركه فارغاً إذا كان هذا القسم قسماً رئيسياً</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="slug">الرابط المخصص (Slug)</label>
                                <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug') }}" placeholder="توليد تلقائي إن ترك فارغاً">
                                <div class="form-text">يُترك فارغاً للتوليد التلقائي من الاسم</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إعدادات القسم والصور -->
            <div class="col-lg-4">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">الحالة والظهور</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="form-label" for="status_id">حالة القسم</label>
                            <select class="form-select" id="status_id" name="status_id" required>
                                <option value="1" @selected(old('status_id', 1) == 1)>نشط (مفعل)</option>
                                <option value="2" @selected(old('status_id') == 2)>معطل (غير نشط)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="order">ترتيب الظهور</label>
                            <input type="number" class="form-control" id="order" name="order" value="{{ old('order', 0) }}" min="0">
                            <div class="form-text">الأرقام الأقل تظهر أولاً</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="appear_in_home" name="appear_in_home" value="1" @checked(old('appear_in_home', true))>
                            <label class="form-check-label fw-semibold" for="appear_in_home">عرض في الصفحة الرئيسية</label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">صورة القسم</h5>
                    </div>
                    <div class="card-body pt-3 text-center">
                        <div class="mb-3">
                            <img id="image_preview" src="{{ asset('dashboard/assets/img/illustrations/page-pricing-enterprise.png') }}" alt="Preview" class="rounded mb-2" style="max-height: 120px; max-width: 100%; object-fit: contain; border: 1px dashed #d9dee3; padding: 5px;">
                            <input class="form-control" type="file" id="image" name="image" accept="image/*" onchange="previewImage(this, 'image_preview')">
                            <div class="form-text">الصورة الرئيسية للقسم (PNG, JPG, WebP)</div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="ti ti-check me-1"></i> حفظ القسم
                    </button>
                    <button type="submit" name="save_and_add_another" value="1" class="btn btn-outline-primary">
                        <i class="ti ti-plus me-1"></i> حفظ وإضافة قسم آخر
                    </button>
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
</script>
@endsection
