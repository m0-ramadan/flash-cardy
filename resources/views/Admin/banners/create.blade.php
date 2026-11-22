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
                    <h5 class="card-title mb-0">المعلومات الأساسية للبانر</h5>
                </div>
                <div class="card-body pt-3">
                    <form action="{{ route('admin.banners.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="title">عنوان البانر <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required placeholder="مثال: سلايدر العروض الترويجية الرئيسية">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="banner_type_id">نوع البانر <span class="text-danger">*</span></label>
                                <select class="form-select @error('banner_type_id') is-invalid @enderror" id="banner_type_id" name="banner_type_id" required>
                                    @foreach ($bannerTypes as $type)
                                        @php
                                            $nameAr = match($type->name) {
                                                'main_slider' => 'سلايدر رئيسي (واجهة المتجر الرئيسية)',
                                                'image_card' => 'بطاقة إعلانية / بانر ثابت',
                                                'category_slider' => 'سلايدر أقسام',
                                                'product_slider' => 'سلايدر منتجات',
                                                default => $type->name
                                            };
                                        @endphp
                                        <option value="{{ $type->id }}" @selected(old('banner_type_id', 1) == $type->id)>
                                            {{ $nameAr }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">اختر "سلايدر رئيسي" ليظهر في أعلى الصفحة الرئيسية بالمتجر</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="section_order">ترتيب البانر <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="section_order" name="section_order" value="{{ old('section_order', 1) }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="category_id">ربط بقسم محدد (اختياري)</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">عام (الصفحة الرئيسية)</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                                            {{ is_array($cat->name) ? ($cat->name['ar'] ?? reset($cat->name)) : $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                                    <label class="form-check-label fw-semibold" for="is_active">تفعيل البانر (نشط الآن)</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 mt-2">
                            <i class="ti ti-info-circle me-1"></i> بعد حفظ البانر، ستنتقل مباشرة لشاشة إضافة الصور والروابط والعناصر التابعة له.
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-arrow-left me-1"></i> متابعة وإضافة الصور
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
