@extends('Admin.layout.master')

@section('title', 'تعديل البانر: ' . $banner->title)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">تعديل البانر وعناصره</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.banners.index') }}">البانرات</a></li>
                    <li class="breadcrumb-item active">{{ $banner->title }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-right me-1"></i> رجوع للقائمة
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
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

    <div class="row">
        <!-- بيانات البانر الرئيسية -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">إعدادات البانر</h5>
                </div>
                <div class="card-body pt-3">
                    <form action="{{ route('admin.banners.update', $banner) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="title">عنوان البانر <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $banner->title) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="banner_type_id">مكان ظهور البانر <span class="text-danger">*</span></label>
                            <select class="form-select" id="banner_type_id" name="banner_type_id" required onchange="toggleCategorySelect(this.value)">
                                @foreach ($bannerTypes as $type)
                                    @php
                                        $isMain = ($type->name === 'main_slider');
                                        $label = $isMain ? 'سلايدر رئيسي (الواجهة الرئيسية)' : 'بانر قسم محدد (صفحة القسم)';
                                    @endphp
                                    <option value="{{ $type->id }}" @selected(old('banner_type_id', $banner->banner_type_id) == $type->id)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="section_order">الترتيب <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="section_order" name="section_order" value="{{ old('section_order', $banner->section_order) }}" required>
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-center pt-3">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $banner->is_active))>
                                    <label class="form-check-label fw-semibold" for="is_active">البانر نشط</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="edit_category_wrapper" style="{{ $banner->banner_type_id == 3 ? '' : 'display: none;' }}">
                            <label class="form-label" for="category_id">القسم المستهدف</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">-- اختر القسم --</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id', $banner->category_id) == $cat->id)>
                                        {{ is_array($cat->name) ? ($cat->name['ar'] ?? reset($cat->name)) : $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">سيظهر هذا البانر في أعلى صفحة هذا القسم.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-device-floppy me-1"></i> حفظ إعدادات البانر
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- عناصر وصور البانر -->
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">صور وعناصر البانر ({{ $banner->items->count() }})</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="ti ti-plus me-1"></i> إضافة صورة جديدة
                    </button>
                </div>
                <div class="card-body pt-3">
                    @if($banner->items->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="ti ti-photo-plus fs-1 d-block mb-2 text-secondary"></i>
                            <h6>لا توجد صور مضافة لهذا البانر حتى الآن</h6>
                            <p class="small">اضغط على زر "إضافة صورة جديدة" لإضافة شريحة أو إعلان للبانر.</p>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="ti ti-plus me-1"></i> إضافة صورة الآن
                            </button>
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($banner->items->sortBy('item_order') as $item)
                                <div class="col-md-6 col-12" id="banner-item-card-{{ $item->id }}">
                                    <div class="card border h-100 shadow-none">
                                        <div class="position-relative">
                                            <img src="{{ asset('storage/' . $item->image_url) }}" class="card-img-top" alt="Banner Image" style="height: 140px; object-fit: cover;">
                                            <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }} position-absolute top-0 end-0 m-2">
                                                {{ $item->is_active ? 'نشط' : 'معطل' }}
                                            </span>
                                        </div>
                                        <div class="card-body p-3">
                                            @if($item->link_url)
                                                <div class="text-truncate mb-2" title="{{ $item->link_url }}">
                                                    <i class="ti ti-link text-primary me-1"></i>
                                                    <a href="{{ $item->link_url }}" target="_blank" class="small text-muted text-decoration-none">
                                                        {{ Str::limit($item->link_url, 35) }}
                                                    </a>
                                                </div>
                                            @else
                                                <div class="small text-muted mb-2"><i class="ti ti-link-off me-1"></i> بدون رابط</div>
                                            @endif

                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                                <span class="badge bg-label-secondary">ترتيب: {{ $item->item_order }}</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-item" data-id="{{ $item->id }}">
                                                    <i class="ti ti-trash me-1"></i> حذف
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة عنصر/صورة للبانر -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addItemForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="banner_id" value="{{ $banner->id }}">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title"><i class="ti ti-photo me-2"></i>إضافة صورة للبانر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="item_image">الصورة الأساسية (شاشات الكمبيوتر والتابلت) <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="item_image" name="image" accept="image/*" required>
                        <div class="form-text">الحجم المقترح: 1920x600 للسلايدر الرئيسي</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="mobile_image">صورة الموبايل (اختياري)</label>
                        <input class="form-control" type="file" id="mobile_image" name="mobile_image" accept="image/*">
                        <div class="form-text">صورة مخصصة للهواتف (اختياري)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="link_url">رابط التوجيه عند الضغط (URL)</label>
                        <input type="url" class="form-control" id="link_url" name="link_url" placeholder="https://...">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="item_order">ترتيب الشريحة <span class="text-danger">*</span></label>
                            <input type="number" min="1" class="form-control" id="item_order" name="item_order" value="{{ $banner->items->count() + 1 }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="link_target">فتح الرابط في</label>
                            <select class="form-select" id="link_target" name="link_target">
                                <option value="_self">نفس النافذة</option>
                                <option value="_blank">نافذة جديدة</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveItem">
                        <i class="ti ti-check me-1"></i> حفظ وإضافة الصورة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
function toggleCategorySelect(typeId) {
    const wrapper = document.getElementById('edit_category_wrapper');
    const categorySelect = document.getElementById('category_id');
    if (typeId == 3) {
        wrapper.style.display = 'block';
    } else {
        wrapper.style.display = 'none';
        if (categorySelect) categorySelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // إضافة عنصر بانر جديد via AJAX
    const addItemForm = document.getElementById('addItemForm');
    const btnSaveItem = document.getElementById('btnSaveItem');

    if (addItemForm) {
        addItemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            btnSaveItem.disabled = true;
            btnSaveItem.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري الرفع...';

            const formData = new FormData(this);

            fetch("{{ route('admin.banners.items.store') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnSaveItem.disabled = false;
                btnSaveItem.innerHTML = '<i class="ti ti-check me-1"></i> حفظ وإضافة الصورة';

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'حدث خطأ أثناء حفظ الصورة');
                }
            })
            .catch(err => {
                btnSaveItem.disabled = false;
                btnSaveItem.innerHTML = '<i class="ti ti-check me-1"></i> حفظ وإضافة الصورة';
                alert('حدث خطأ في الاتصال بالسيرفر');
            });
        });
    }

    // حذف عنصر بانر
    document.querySelectorAll('.btn-delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.dataset.id;
            if (!confirm('هل أنت متأكد من حذف هذه الصورة من البانر؟')) return;

            fetch("{{ url('admin/banners/items') }}/" + itemId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('banner-item-card-' + itemId);
                    if (card) card.remove();
                } else {
                    alert('حدث خطأ أثناء الحذف');
                }
            })
            .catch(() => alert('حدث خطأ أثناء الحذف'));
        });
    });
});
</script>
@endsection
