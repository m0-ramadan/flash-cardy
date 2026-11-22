@extends('Admin.layout.master')

@section('title', 'تفاصيل المنتج')

@section('css')
    <style>
        .product-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }

        .additional-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .additional-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
            border: 2px solid #e9ecef;
        }

        .additional-image:hover {
            transform: scale(1.05);
            border-color: #696cff;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .info-value {
            color: #212529;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .tag-badge {
            background: #e7e7ff;
            color: #696cff;
            padding: 5px 12px;
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
            border: 1px solid #d4d4ff;
        }

        .price-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
        }

        .original-price {
            text-decoration: line-through;
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }

        .final-price {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .discount-badge {
            background: rgba(220, 53, 69, 0.2);
            color: #fff;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 14px;
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .language-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .language-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            color: #6c757d;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }

        .language-tab.active {
            color: #696cff;
            border-bottom-color: #696cff;
            background: rgba(105, 108, 255, 0.1);
        }

        .language-tab:hover:not(.active) {
            color: #212529;
            border-bottom-color: #adb5bd;
        }

        .language-content {
            display: none;
        }

        .language-content.active {
            display: block;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.index') }}">الرئيسية</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.products.index') }}">المنتجات</a>
                </li>
                <li class="breadcrumb-item active">تفاصيل المنتج</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-package me-2"></i>
                            تفاصيل المنتج: {{ $product->getTranslatedValue('name', 'ar') }}
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-edit me-1"></i> تعديل
                            </a>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-arrow-right me-1"></i> رجوع
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- الرسائل -->
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="row">
                            <!-- الصور -->
                            <div class="col-md-5 mb-4">
                                <div class="info-card">
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-image me-2"></i>الصورة الرئيسية
                                    </h6>
                                    @php
                                        $primaryImage =
                                            $product->images->where('type', 'main')->first() ??
                                            $product->images->first();
                                    @endphp
                                    @if ($primaryImage)
                                        <img src="{{ asset('storage/' . $primaryImage->path) }}"
                                            alt="{{ $product->getTranslatedValue('name', 'ar') }}" class="product-image">
                                    @elseif($product->image_path)
                                        <img src="{{ asset('storage/' . $product->image_path) }}"
                                            alt="{{ $product->getTranslatedValue('name', 'ar') }}" class="product-image">
                                    @else
                                        <div class="text-center py-5 border rounded"
                                            style="background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.1) !important;">
                                            <i class="fas fa-image fa-4x text-muted mb-3"></i>
                                            <p class="text-muted">لا توجد صورة رئيسية</p>
                                        </div>
                                    @endif

                                    <!-- الصور الإضافية -->
                                    @php
                                        $additionalImages = $product->images->where('type', 'additional');
                                    @endphp
                                    @if ($additionalImages->count() > 0)
                                        <div class="additional-images">
                                            @foreach ($additionalImages as $image)
                                                <img src="{{ asset('storage/' . $image->path) }}" alt="صورة إضافية"
                                                    class="additional-image"
                                                    onclick="viewImage('{{ asset('storage/' . $image->path) }}')"
                                                    data-bs-toggle="tooltip" title="اضغط للتكبير">
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- المعلومات الأساسية -->
                            <div class="col-md-7">
                                <div class="info-card">
                                    <!-- تبديل اللغات -->
                                    <div class="language-tabs">
                                        <button class="language-tab active" data-lang="ar">
                                            <i class="fas fa-flag me-1"></i>العربية
                                        </button>
                                        @php
                                            $languages = \App\Models\Language::where('is_active', 1)->get();
                                        @endphp
                                        @foreach ($languages as $language)
                                            @if ($language->code != 'ar')
                                                <button class="language-tab" data-lang="{{ $language->code }}">
                                                    <i class="fas fa-language me-1"></i>{{ $language->name }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>

                                    <!-- المحتوى العربي -->
                                    <div class="language-content active" data-lang="ar">
                                        <h4 class="mb-3 text-dark">{{ $product->getTranslatedValue('name', 'ar') }}</h4>

                                        @if ($product->getTranslatedValue('description', 'ar'))
                                            <div class="mb-4">
                                                <h6 class="info-label">الوصف:</h6>
                                                <div class="info-value p-3"
                                                    style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                                    {!! nl2br(e($product->getTranslatedValue('description', 'ar'))) !!}
                                                </div>
                                            </div>
                                        @endif

                                        @if ($product->price_text)
                                            @php
                                                $priceText = json_decode($product->price_text, true);
                                            @endphp
                                            @if (isset($priceText['ar']) && !empty($priceText['ar']))
                                                <div class="mb-4">
                                                    <h6 class="info-label">نص السعر:</h6>
                                                    <div class="info-value p-3"
                                                        style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                                        {{ $priceText['ar'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    <!-- محتوى اللغات الأخرى -->
                                    @foreach ($languages as $language)
                                        @if ($language->code != 'ar')
                                            <div class="language-content" data-lang="{{ $language->code }}">
                                                <h4 class="mb-3 text-dark">
                                                    {{ $product->getTranslatedValue('name', $language->code) ?: 'لا يوجد اسم' }}
                                                </h4>

                                                @php
                                                    $description = $product->getTranslatedValue(
                                                        'description',
                                                        $language->code,
                                                    );
                                                @endphp
                                                @if ($description)
                                                    <div class="mb-4">
                                                        <h6 class="info-label">الوصف:</h6>
                                                        <div class="info-value p-3"
                                                            style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                                            {!! nl2br(e($description)) !!}
                                                        </div>
                                                    </div>
                                                @endif

                                                @if ($product->price_text)
                                                    @php
                                                        $priceText = json_decode($product->price_text, true);
                                                    @endphp
                                                    @if (isset($priceText[$language->code]) && !empty($priceText[$language->code]))
                                                        <div class="mb-4">
                                                            <h6 class="info-label">نص السعر:</h6>
                                                            <div class="info-value p-3"
                                                                style="background: rgba(0,0,0,0.2); border-radius: 8px;">
                                                                {{ $priceText[$language->code] }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach

                                    <!-- السعر -->
                                    <div class="price-box mb-3">
                                        @if ($product->has_discount && $product->discount)
                                            <div class="original-price">{{ number_format($product->price, 2) }} ج.م</div>
                                            <div class="final-price">{{ number_format($product->final_price, 2) }} ج.م
                                            </div>
                                            <div class="discount-badge">
                                                @if ($product->discount->discount_type == 'percentage')
                                                    <i
                                                        class="fas fa-percent me-1"></i>{{ $product->discount->discount_value }}%
                                                    خصم
                                                @else
                                                    <i
                                                        class="fas fa-tag me-1"></i>{{ number_format($product->discount->discount_value, 2) }}
                                                    ج.م خصم
                                                @endif
                                            </div>
                                        @else
                                            <div class="final-price">{{ number_format($product->price, 2) }} ج.م</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="info-card">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                        <h6 class="info-label mb-0"><i class="fas fa-key me-2"></i>بيانات الاشتراك
                                            والتنفيذ الرقمي</h6>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="#product-serials-section"><i
                                                    class="fas fa-barcode me-1"></i>عرض الأكواد
                                                ({{ number_format($serialStats['total'] ?? 0) }})</a>
                                            <a class="btn btn-sm btn-outline-info"
                                                href="{{ route('admin.products.serials.index', $product) }}"><i
                                                    class="fas fa-cog me-1"></i>إدارة الأكواد</a>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">النوع</small><strong>{{ $product->type ?: 'product' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">البراند</small><strong>{{ $product->brand ?: '—' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">المنطقة</small><strong>{{ $product->region ?: '—' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">القيمة/الفئة</small><strong>{{ $product->denomination ?: '—' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">مصدر
                                                التنفيذ</small><strong>{{ $product->fulfillment_source ?: 'none' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">طريقة
                                                الإرسال</small><strong>{{ $product->fulfillment_mode === 'automatic' ? 'تلقائي' : 'يدوي' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">أكواد متاحة</small><strong
                                                class="text-success">{{ number_format($serialStats['available'] ?? 0) }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">إجمالي
                                                الأكواد</small><strong>{{ number_format($serialStats['total'] ?? 0) }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">Provider
                                                ID</small><strong>{{ $product->external_id ?: '—' }}</strong></div>
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">الصلاحية</small><strong>{{ $product->validity_text ?: 'حسب الكود' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small
                                                class="text-muted d-block">مميز</small><strong>{{ $product->is_featured ? 'نعم' : 'لا' }}</strong>
                                        </div>
                                        <div class="col-md-2"><small class="text-muted d-block">الأكثر
                                                مبيعاً</small><strong>{{ $product->is_best_seller ? 'نعم' : 'لا' }}</strong>
                                        </div>
                                    </div>
                                    @if ($product->instructions || $product->terms)
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6"><small class="text-muted d-block">تعليمات
                                                    الاستخدام</small>
                                                <div>{{ $product->instructions ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6"><small class="text-muted d-block">الشروط</small>
                                                <div>{{ $product->terms ?: '—' }}</div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- بطاقة الأكواد والبطاقات الرقمية للمنتج -->
                        <div class="row mt-4" id="product-serials-section">
                            <div class="col-12">
                                <div class="info-card">
                                    <!-- الهيدر وأزرار الإجراءات -->
                                    <div
                                        class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary border-opacity-25 gap-2">
                                        <div>
                                            <h5 class="mb-1 text-dark fw-bold">
                                                <i class="fas fa-barcode text-primary me-2"></i>الأكواد والبطاقات الرقمية
                                                الخاصة بالمنتج
                                            </h5>
                                            <small class="text-muted">عرض وتتبع جميع الأكواد التسلسلية (Serials / PINs)
                                                وحالتها وتفاصيل استهلاكها</small>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#addSingleSerialModal">
                                                <i class="fas fa-plus me-1"></i>إضافة كود مفرد
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                data-bs-target="#addBulkSerialsModal">
                                                <i class="fas fa-file-import me-1"></i>إضافة أكواد متعددة (Bulk)
                                            </button>
                                            <a href="{{ route('admin.products.serials.index', $product) }}"
                                                class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-external-link-alt me-1"></i>إدارة الأكواد المتقدمة
                                            </a>
                                        </div>
                                    </div>

                                    <!-- إحصائيات الأكواد سريعة -->
                                    <div class="row g-2 mb-4">
                                        <div class="col-6 col-md-2">
                                            <div class="p-2 rounded text-center"
                                                style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                                <small class="text-muted d-block">إجمالي الأكواد</small>
                                                <h4 class="mb-0 text-dark fw-bold">
                                                    {{ number_format($serialStats['total'] ?? 0) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <div class="p-2 rounded text-center"
                                                style="background: rgba(40, 167, 69, 0.15); border: 1px solid rgba(40, 167, 69, 0.3);">
                                                <small class="text-success d-block fw-semibold">أكواد متاحة للبيع</small>
                                                <h4 class="mb-0 text-success fw-bold">
                                                    {{ number_format($serialStats['available'] ?? 0) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <div class="p-2 rounded text-center"
                                                style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                                                <small class="text-info d-block fw-semibold">مباعة / مسلمة للعملاء</small>
                                                <h4 class="mb-0 text-info fw-bold">
                                                    {{ number_format($serialStats['assigned'] ?? 0) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <div class="p-2 rounded text-center"
                                                style="background: rgba(220, 53, 69, 0.15); border: 1px solid rgba(220, 53, 69, 0.3);">
                                                <small class="text-danger d-block fw-semibold">منتهية الصلاحية</small>
                                                <h4 class="mb-0 text-danger fw-bold">
                                                    {{ number_format($serialStats['expired'] ?? 0) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <div class="p-2 rounded text-center"
                                                style="background: rgba(108, 117, 125, 0.15); border: 1px solid rgba(108, 117, 125, 0.3);">
                                                <small class="text-secondary d-block fw-semibold">معطلة</small>
                                                <h4 class="mb-0 text-secondary fw-bold">
                                                    {{ number_format($serialStats['disabled'] ?? 0) }}</h4>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- أدوات التصفية والبحث السريع -->
                                    <form method="GET"
                                        action="{{ route('admin.products.show', $product->id) }}#product-serials-section"
                                        class="row g-2 mb-3 align-items-center">
                                        <div class="col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i
                                                        class="fas fa-search"></i></span>
                                                <input type="text" name="serial_search"
                                                    value="{{ request('serial_search') }}"
                                                    class="form-control form-control-sm form-control-sm"
                                                    placeholder="ابحث برقم الكود، PIN، الدفعة، رقم الطلب، أو ملاحظة...">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="serial_status"
                                                class="form-select form-select-sm form-control-sm">
                                                <option value="">-- تصفية حسب الحالة (الكل) --</option>
                                                <option value="available" @selected(request('serial_status') === 'available')>متاح فقط</option>
                                                <option value="delivered" @selected(request('serial_status') === 'delivered')>تم التسليم</option>
                                                <option value="assigned" @selected(request('serial_status') === 'assigned')>محجوز للطلب</option>
                                                <option value="expired" @selected(request('serial_status') === 'expired')>منتهي الصلاحية
                                                </option>
                                                <option value="disabled" @selected(request('serial_status') === 'disabled')>معطل</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="serial_expires"
                                                class="form-select form-select-sm form-control-sm">
                                                <option value="">-- تصفية الصلاحية --</option>
                                                <option value="soon" @selected(request('serial_expires') === 'soon')>ينتهي قريباً (خلال 30
                                                    يوم)</option>
                                                <option value="expired" @selected(request('serial_expires') === 'expired')>منتهي فعلياً</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex gap-1">
                                            <button type="submit" class="btn btn-sm btn-primary w-100"><i
                                                    class="fas fa-filter me-1"></i>تصفية</button>
                                            @if (request()->filled('serial_search') || request()->filled('serial_status') || request()->filled('serial_expires'))
                                                <a href="{{ route('admin.products.show', $product->id) }}#product-serials-section"
                                                    class="btn btn-sm btn-outline-secondary" title="إعادة تعيين"><i
                                                        class="fas fa-undo"></i></a>
                                            @endif
                                        </div>
                                    </form>

                                    <!-- جدول الأكواد وتفاصيلها -->
                                    <div class="table-responsive rounded border border-secondary border-opacity-25">
                                        <table class="table table-hover align-middle mb-0"
                                            style="background: transparent;">
                                            <thead
                                                style="background: rgba(0,0,0,0.3); color: rgba(255,255,255,0.8); font-size: 13px;">
                                                <tr>
                                                    <th>#</th>
                                                    <th>الكود الرقمي (Code)</th>
                                                    <th>الرقم السري (PIN)</th>
                                                    <th>الحالة</th>
                                                    <th>الصلاحية</th>
                                                    <th>الطلب المرتبط</th>
                                                    <th>تاريخ الاستهلاك</th>
                                                    <th>الدفعة / المصدر</th>
                                                    <th>ملاحظات</th>
                                                    <th>تاريخ الإضافة</th>
                                                    <th class="text-center">إجراءات</th>
                                                </tr>
                                            </thead>
                                            <tbody style="font-size: 13px;">
                                                @forelse($serials as $serial)
                                                    <tr>
                                                        <td><span class="text-muted">#{{ $serial->id }}</span></td>
                                                        <td dir="ltr" class="text-start">
                                                            <div class="d-flex align-items-center gap-1">
                                                                <code
                                                                    class="serial-code font-monospace fw-bold text-info fs-6"
                                                                    id="serial-code-{{ $serial->id }}"
                                                                    data-masked="{{ $serial->masked_code }}"
                                                                    data-full="{{ $serial->code }}">{{ $serial->masked_code }}</code>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-info py-0 px-1 toggle-code-btn"
                                                                    data-target="#serial-code-{{ $serial->id }}"
                                                                    title="إظهار / إخفاء الكود">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-secondary py-0 px-1 copy-text-btn"
                                                                    data-clipboard-text="{{ $serial->code }}"
                                                                    title="نسخ الكود">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td dir="ltr" class="text-start">
                                                            @if ($serial->pin)
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <code
                                                                        class="text-warning fw-bold font-monospace">{{ $serial->pin }}</code>
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-outline-secondary py-0 px-1 copy-text-btn"
                                                                        data-clipboard-text="{{ $serial->pin }}"
                                                                        title="نسخ PIN">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                </div>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->status === 'available')
                                                                <span class="badge bg-success"><i
                                                                        class="fas fa-check-circle me-1"></i>متاح</span>
                                                            @elseif($serial->status === 'delivered')
                                                                <span class="badge bg-primary"><i
                                                                        class="fas fa-paper-plane me-1"></i>تم
                                                                    التسليم</span>
                                                            @elseif($serial->status === 'assigned')
                                                                <span class="badge bg-warning text-dark"><i
                                                                        class="fas fa-clock me-1"></i>محجوز</span>
                                                            @elseif($serial->status === 'expired')
                                                                <span class="badge bg-danger"><i
                                                                        class="fas fa-calendar-times me-1"></i>منتهي</span>
                                                            @elseif($serial->status === 'disabled')
                                                                <span class="badge bg-secondary"><i
                                                                        class="fas fa-ban me-1"></i>معطل</span>
                                                            @else
                                                                <span class="badge bg-dark">{{ $serial->status }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->expires_at)
                                                                <span
                                                                    class="{{ $serial->expires_at->isPast() ? 'text-danger fw-bold' : ($serial->expires_at->diffInDays(now()) < 30 ? 'text-warning' : 'text-white') }}">
                                                                    <i
                                                                        class="far fa-calendar-alt me-1"></i>{{ $serial->expires_at->format('Y-m-d') }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted">دائم / غير محدد</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->order)
                                                                <a href="{{ route('admin.orders.show', $serial->order_id) }}"
                                                                    class="badge bg-dark text-info text-decoration-none border border-info border-opacity-25"
                                                                    target="_blank">
                                                                    <i
                                                                        class="fas fa-shopping-bag me-1"></i>{{ $serial->order->order_number ?? '#' . $serial->order_id }}
                                                                </a>
                                                                @if ($serial->order->customer_name)
                                                                    <div class="small text-muted mt-1">
                                                                        {{ $serial->order->customer_name }}</div>
                                                                @endif
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->delivered_at)
                                                                <div><i
                                                                        class="fas fa-check text-success me-1"></i>{{ $serial->delivered_at->format('Y-m-d') }}
                                                                </div>
                                                                <small
                                                                    class="text-muted">{{ $serial->delivered_at->format('h:i A') }}</small>
                                                            @elseif($serial->assigned_at)
                                                                <div><i
                                                                        class="fas fa-clock text-warning me-1"></i>{{ $serial->assigned_at->format('Y-m-d') }}
                                                                </div>
                                                                <small
                                                                    class="text-muted">{{ $serial->assigned_at->format('h:i A') }}</small>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->batch_reference)
                                                                <code class="text-white-50"
                                                                    style="font-size: 11px;">{{ $serial->batch_reference }}</code>
                                                            @endif
                                                            @if ($serial->source)
                                                                <span class="badge bg-secondary opacity-75 d-block mt-1"
                                                                    style="font-size: 10px;">{{ $serial->source }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($serial->notes)
                                                                <span
                                                                    title="{{ $serial->notes }}">{{ Str::limit($serial->notes, 30) }}</span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div>
                                                                {{ $serial->created_at ? $serial->created_at->format('Y-m-d') : '—' }}
                                                            </div>
                                                            @if ($serial->creator)
                                                                <small
                                                                    class="text-muted">{{ $serial->creator->name }}</small>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if (!in_array($serial->status, ['assigned', 'delivered']))
                                                                <form method="POST"
                                                                    action="{{ route('admin.products.serials.destroy', [$product, $serial]) }}"
                                                                    class="d-inline"
                                                                    onsubmit="return confirm('هل أنت متأكد من حذف هذا الكود غير المستخدم؟');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                        class="btn btn-sm btn-outline-danger py-0 px-2"
                                                                        title="حذف الكود">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="badge bg-secondary"
                                                                    title="لا يمكن حذف كود تم بيعه أو تسليمه"><i
                                                                        class="fas fa-lock"></i></span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="11" class="text-center py-5">
                                                            <div class="text-muted">
                                                                <i class="fas fa-barcode fa-3x mb-3 opacity-25"></i>
                                                                <p class="mb-2">لا توجد أكواد تسلسلية مسجلة لهذا المنتج
                                                                    حتى الآن.</p>
                                                                <button type="button" class="btn btn-sm btn-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#addBulkSerialsModal">
                                                                    <i class="fas fa-plus me-1"></i>إضافة أكواد الآن
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- التصفح Pagination -->
                                    @if ($serials->hasPages())
                                        <div
                                            class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                                            <small class="text-muted">عرض {{ $serials->firstItem() }} إلى
                                                {{ $serials->lastItem() }} من إجمالي {{ $serials->total() }} كود</small>
                                            <div>
                                                {{ $serials->fragment('product-serials-section')->links() }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- التفاصيل الإضافية -->
                        <div class="row mt-4">
                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-info-circle me-2"></i>معلومات أساسية
                                    </h6>

                                    <div class="mb-3">
                                        <span class="info-label">القسم:</span>
                                        <div class="info-value">
                                            @if ($product->category)
                                                <a href="{{ route('admin.categories.show', $product->category_id) }}"
                                                    class="text-white">
                                                    <i class="fas fa-folder me-1"></i>{{ $product->category->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">غير محدد</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <span class="info-label">الكمية المتاحة:</span>
                                        <div class="info-value">
                                            @if ($product->stock > 0)
                                                <span class="badge bg-success">{{ $product->stock }} قطعة</span>
                                            @else
                                                <span class="badge bg-danger">غير متوفر</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <span class="info-label">SKU:</span>
                                        <div class="info-value">
                                            <code>{{ $product->sku ?: 'غير محدد' }}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-toggle-on me-2"></i>الحالة والإعدادات
                                    </h6>

                                    <div class="mb-3">
                                        <span class="info-label">حالة المنتج:</span>
                                        <div class="info-value">
                                            @php
                                                $statusMap = [
                                                    1 => ['text' => 'نشط', 'class' => 'status-active'],
                                                    2 => ['text' => 'غير نشط', 'class' => 'status-inactive'],
                                                    3 => ['text' => 'مسودة', 'class' => 'status-draft'],
                                                ];
                                                $status = $statusMap[$product->status_id] ?? $statusMap[2];
                                            @endphp
                                            <span class="status-badge {{ $status['class'] }}">
                                                <i
                                                    class="fas {{ $product->status_id == 1 ? 'fa-check-circle' : ($product->status_id == 2 ? 'fa-times-circle' : 'fa-clock') }} me-1"></i>
                                                {{ $status['text'] }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <span class="info-label">يشمل الضريبة:</span>
                                        <div class="info-value">
                                            @if ($product->includes_tax)
                                                <span class="badge bg-info">نعم</span>
                                            @else
                                                <span class="badge bg-secondary">لا</span>
                                            @endif
                                        </div>
                                    </div>


                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-calendar me-2"></i>التواريخ
                                    </h6>

                                    <div class="mb-3">
                                        <span class="info-label">تاريخ الإنشاء:</span>
                                        <div class="info-value">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            {{ $product->created_at->format('Y-m-d') }}
                                            <br>
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                {{ $product->created_at->format('h:i A') }}
                                            </small>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <span class="info-label">آخر تحديث:</span>
                                        <div class="info-value">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            {{ $product->updated_at->format('Y-m-d') }}
                                            <br>
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                {{ $product->updated_at->format('h:i A') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- التقييمات -->
                        @if ($product->reviews && $product->reviews->count() > 0)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="info-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="info-label mb-0">
                                                <i class="fas fa-star me-2"></i>التقييمات
                                            </h6>
                                            <span class="badge bg-primary">{{ $product->reviews->count() }} تقييم</span>
                                        </div>

                                        @php
                                            $totalRating = $product->reviews->avg('rating') ?? 0;
                                        @endphp
                                        <div class="text-center mb-4">
                                            <div class="display-4 text-white">{{ number_format($totalRating, 1) }}</div>
                                            <div class="star-rating mb-2">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= floor($totalRating))
                                                        <i class="fas fa-star"></i>
                                                    @elseif($i - 0.5 <= $totalRating)
                                                        <i class="fas fa-star-half-alt"></i>
                                                    @else
                                                        <i class="far fa-star"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                            <small class="text-muted">من أصل 5 نجوم</small>
                                        </div>

                                        @foreach ($product->reviews as $review)
                                            <div class="review-item">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div>
                                                        <strong
                                                            class="text-white">{{ $review->user->name ?? 'مستخدم' }}</strong>
                                                        <div class="star-rating">
                                                            @for ($i = 1; $i <= 5; $i++)
                                                                @if ($i <= $review->rating)
                                                                    <i class="fas fa-star"></i>
                                                                @else
                                                                    <i class="far fa-star"></i>
                                                                @endif
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar-alt me-1"></i>
                                                        {{ $review->created_at->format('Y-m-d') }}
                                                    </small>
                                                </div>
                                                <p class="mb-0 text-white-50">{{ $review->comment }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- النصوص الإعلانية -->
                        @if ($product->adsText && $product->adsText->count() > 0)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="info-card">
                                        <h6 class="info-label mb-3">
                                            <i class="fas fa-ad me-2"></i>النصوص الإعلانية
                                        </h6>

                                        <!-- تبادل اللغات للنصوص الإعلانية -->
                                        <div class="language-tabs mb-3">
                                            <button class="language-tab active" data-ads-lang="ar">العربية</button>
                                            @foreach ($languages as $language)
                                                @if ($language->code != 'ar')
                                                    <button class="language-tab" data-ads-lang="{{ $language->code }}">
                                                        {{ $language->name }}
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>

                                        <!-- النصوص الإعلانية العربية -->
                                        <div class="text-ads-container language-ads-content active" data-lang="ar">
                                            @foreach ($product->adsText as $textAd)
                                                @php
                                                    $translations = $textAd->getTranslations('name');
                                                    if (!is_array($translations) || empty($translations)) {
                                                        $raw = $textAd->getRawOriginal('name');
                                                        $decoded = is_string($raw) ? json_decode($raw, true) : null;
                                                        $adText = is_array($decoded)
                                                            ? $decoded
                                                            : ['ar' => (string) $textAd->name];
                                                    } else {
                                                        $adText = $translations;
                                                    }
                                                @endphp
                                                <div class="text-ad-item">
                                                    <i class="fas fa-quote-right me-2"
                                                        style="color: var(--primary-color);"></i>
                                                    {{ $adText['ar'] ?? 'لا يوجد نص' }}
                                                </div>
                                            @endforeach
                                        </div>

                                        <!-- النصوص الإعلانية بلغات أخرى -->
                                        @foreach ($languages as $language)
                                            @if ($language->code != 'ar')
                                                <div class="text-ads-container language-ads-content"
                                                    data-lang="{{ $language->code }}">
                                                    @foreach ($product->adsText as $textAd)
                                                        @php
                                                            $translations = $textAd->getTranslations('name');
                                                            if (!is_array($translations) || empty($translations)) {
                                                                $raw = $textAd->getRawOriginal('name');
                                                                $decoded = is_string($raw)
                                                                    ? json_decode($raw, true)
                                                                    : null;
                                                                $adText = is_array($decoded)
                                                                    ? $decoded
                                                                    : ['ar' => (string) $textAd->name];
                                                            } else {
                                                                $adText = $translations;
                                                            }
                                                        @endphp
                                                        <div class="text-ad-item">
                                                            <i class="fas fa-quote-right me-2"
                                                                style="color: var(--primary-color);"></i>
                                                            {{ $adText[$language->code] ?? 'لا يوجد نص' }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- العروض -->
                        @if ($product->offers && $product->offers->count() > 0)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="info-card">
                                        <h6 class="info-label mb-3">
                                            <i class="fas fa-tags me-2"></i>العروض
                                        </h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($product->offers as $offer)
                                                <span class="tag-badge">
                                                    <i class="fas fa-tag me-1"></i>
                                                    {{ $offer->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- جميع الصور -->
                        @if ($product->images && $product->images->count() > 0)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="info-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="info-label mb-0">
                                                <i class="fas fa-images me-2"></i>معرض الصور
                                            </h6>
                                            <span class="badge bg-primary">{{ $product->images->count() }} صورة</span>
                                        </div>
                                        <div class="row">
                                            @foreach ($product->images as $image)
                                                <div class="col-md-3 col-sm-6">
                                                    <div class="image-gallery-item">
                                                        <img src="{{ asset('storage/' . $image->path) }}"
                                                            alt="صورة المنتج"
                                                            onclick="viewImage('{{ asset('storage/' . $image->path) }}')"
                                                            style="cursor: pointer;">
                                                        @if ($image->is_primary || $image->type == 'main')
                                                            <span class="primary-badge">
                                                                <i class="fas fa-star"></i> رئيسية
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- SEO -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="info-card">
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-chart-line me-2"></i>معلومات SEO
                                    </h6>

                                    <!-- تبادل لغات SEO -->
                                    <div class="language-tabs mb-3">
                                        <button class="language-tab active" data-seo-lang="ar">العربية</button>
                                        @foreach ($languages as $language)
                                            @if ($language->code != 'ar')
                                                <button class="language-tab" data-seo-lang="{{ $language->code }}">
                                                    {{ $language->name }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>

                                    <!-- محتوى SEO العربي -->
                                    <div class="seo-content active" data-seo-lang="ar">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <span class="info-label">Meta Title:</span>
                                                <div class="info-value p-2"
                                                    style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                    {{ $product->getTranslatedValue('meta_title', 'ar') ?: 'لا يوجد' }}
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <span class="info-label">Meta Description:</span>
                                                <div class="info-value p-2"
                                                    style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                    {{ $product->getTranslatedValue('meta_description', 'ar') ?: 'لا يوجد' }}
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <span class="info-label">Meta Keywords:</span>
                                                <div class="info-value p-2"
                                                    style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                    {{ $product->getTranslatedValue('meta_keywords', 'ar') ?: 'لا يوجد' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- محتوى SEO للغات الأخرى -->
                                    @foreach ($languages as $language)
                                        @if ($language->code != 'ar')
                                            <div class="seo-content" data-seo-lang="{{ $language->code }}"
                                                style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <span class="info-label">Meta Title:</span>
                                                        <div class="info-value p-2"
                                                            style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                            {{ $product->getTranslatedValue('meta_title', $language->code) ?: 'لا يوجد' }}
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <span class="info-label">Meta Description:</span>
                                                        <div class="info-value p-2"
                                                            style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                            {{ $product->getTranslatedValue('meta_description', $language->code) ?: 'لا يوجد' }}
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <span class="info-label">Meta Keywords:</span>
                                                        <div class="info-value p-2"
                                                            style="background: rgba(0,0,0,0.2); border-radius: 5px;">
                                                            {{ $product->getTranslatedValue('meta_keywords', $language->code) ?: 'لا يوجد' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal لعرض الصورة -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: var(--dark-card); border: 1px solid rgba(255,255,255,0.1);">
                <div class="modal-body p-0">
                    <img id="modalImage" src="" alt="صورة المنتج" class="img-fluid w-100">
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة كود مفرد -->
    <div class="modal fade" id="addSingleSerialModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--dark-card); border: 1px solid rgba(255,255,255,0.1);">
                <form method="POST" action="{{ route('admin.products.serials.store', $product) }}">
                    @csrf
                    <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h5 class="modal-title text-white"><i class="fas fa-key me-2 text-primary"></i>إضافة كود تسلسلي
                            جديد</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white required">الكود الرقمي (Code)</label>
                            <input type="text" name="serials[0][code]" required
                                class="form-control form-control-sm" dir="ltr"
                                placeholder="مثال: ABCD-1234-EFGH-5678">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">رقم PIN (إن وجد)</label>
                            <input type="text" name="serials[0][pin]"
                                class="form-control form-control-sm" dir="ltr"
                                placeholder="مثال: 1234">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">تاريخ الانتهاء (الصلاحية)</label>
                            <input type="datetime-local" name="serials[0][expires_at]"
                                class="form-control form-control-sm">
                            <small class="text-muted">اتركه فارغاً إذا كان الكود دائم الصلاحية</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">ملاحظات</label>
                            <textarea name="serials[0][notes]" rows="2" class="form-control form-control-sm"
                                placeholder="أي ملاحظات حول الكود أو مصدره..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ الكود</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal إضافة أكواد متعددة Bulk -->
    <div class="modal fade" id="addBulkSerialsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: var(--dark-card); border: 1px solid rgba(255,255,255,0.1);">
                <form method="POST" action="{{ route('admin.products.serials.bulk-text', $product) }}">
                    @csrf
                    <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h5 class="modal-title text-white"><i class="fas fa-file-import me-2 text-success"></i>إضافة أكواد
                            متعددة (Bulk Text)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2"
                            style="background: rgba(13,110,253,0.15); border-color: rgba(13,110,253,0.3); color: #fff;">
                            <i class="fas fa-info-circle me-1"></i>أدخل كود واحد في كل سطر. التنسيق:
                            <code>CODE|YYYY-MM-DD|PIN|ملاحظة</code><br>
                            التاريخ وPIN والملاحظة اختيارية. مثال:<br>
                            <code>CARD-12345|2027-12-31|9988|دفعة سبتمبر</code><br>
                            <code>CARD-67890</code>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white required">نص الأكواد</label>
                            <textarea name="serials_text" required class="form-control form-control-sm font-monospace"
                                rows="10" dir="ltr" placeholder="CODE-001|2027-12-31|1234|دفعة 1&#10;CODE-002|2027-12-31&#10;CODE-003"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i>استيراد
                            الأكواد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // تفعيل tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // تبديل المحتوى بين اللغات للمنتج
            $('.language-tab[data-lang]').on('click', function() {
                const lang = $(this).data('lang');

                // إزالة النشط من جميع الألسنة
                $('.language-tab[data-lang]').removeClass('active');
                $(this).addClass('active');

                // إخفاء جميع المحتويات
                $('.language-content[data-lang]').removeClass('active');
                $('.language-content[data-lang="' + lang + '"]').addClass('active');
            });

            // تبديل النصوص الإعلانية بين اللغات
            $('[data-ads-lang]').on('click', function() {
                const lang = $(this).data('ads-lang');

                // إزالة النشط من جميع الألسنة
                $('[data-ads-lang]').removeClass('active');
                $(this).addClass('active');

                // إخفاء جميع المحتويات
                $('.language-ads-content').removeClass('active');
                $('.language-ads-content[data-lang="' + lang + '"]').addClass('active');
            });

            // تبديل محتوى SEO بين اللغات
            $('[data-seo-lang]').on('click', function() {
                const lang = $(this).data('seo-lang');

                // إزالة النشط من جميع الألسنة
                $('[data-seo-lang]').removeClass('active');
                $(this).addClass('active');

                // إخفاء جميع المحتويات
                $('.seo-content').hide();
                $('.seo-content[data-seo-lang="' + lang + '"]').show();
            });

            // إظهار وإخفاء الكود الرقمي
            $(document).on('click', '.toggle-code-btn', function() {
                const target = $($(this).data('target'));
                const isMasked = target.text().trim() === String(target.data('masked')).trim();
                if (isMasked) {
                    target.text(target.data('full'));
                    $(this).html('<i class="fas fa-eye-slash"></i>');
                } else {
                    target.text(target.data('masked'));
                    $(this).html('<i class="fas fa-eye"></i>');
                }
            });

            // نسخ النص إلى الحافظة
            $(document).on('click', '.copy-text-btn', function() {
                const text = $(this).attr('data-clipboard-text');
                if (!text) return;
                const btn = $(this);
                const originalHtml = btn.html();
                navigator.clipboard.writeText(text).then(function() {
                    btn.html('<i class="fas fa-check text-success"></i>');
                    setTimeout(function() {
                        btn.html(originalHtml);
                    }, 1500);
                }).catch(function() {
                    // Fallback for older browsers
                    const tempInput = document.createElement("input");
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand("copy");
                    document.body.removeChild(tempInput);
                    btn.html('<i class="fas fa-check text-success"></i>');
                    setTimeout(function() {
                        btn.html(originalHtml);
                    }, 1500);
                });
            });

            // عرض الصور الكبيرة
            window.viewImage = function(src) {
                $('#modalImage').attr('src', src);
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            };

            // إغلاق التنبيهات تلقائياً
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
@endsection
