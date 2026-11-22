@extends('Admin.layout.master')

@section('title', 'إدارة الأقسام')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إدارة الأقسام</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الأقسام</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> إضافة قسم جديد
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- فلترة وبحث -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.categories.index') }}" method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="search">بحث باسم القسم أو الرابط</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="اكتب اسم القسم...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="parent_id">القسم الرئيسي</label>
                    <select class="form-select" id="parent_id" name="parent_id">
                        <option value="">جميع الأقسام</option>
                        <option value="null" @selected(request('parent_id') === 'null')>الأقسام الرئيسية فقط</option>
                        @foreach ($parentCategories as $parent)
                            <option value="{{ $parent->id }}" @selected(request('parent_id') == $parent->id)>
                                {{ is_array($parent->name) ? ($parent->name['ar'] ?? reset($parent->name)) : $parent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status_id">الحالة</label>
                    <select class="form-select" id="status_id" name="status_id">
                        <option value="">الكل</option>
                        <option value="1" @selected(request('status_id') == '1')>نشط</option>
                        <option value="2" @selected(request('status_id') == '2')>معطل</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> تصفية
                    </button>
                    @if(request()->anyFilled(['search', 'parent_id', 'status_id']))
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary" title="إلغاء الفلتر">
                            <i class="ti ti-rotate"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- جدول الأقسام -->
    <div class="card shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th style="width: 80px;">الصورة</th>
                        <th>اسم القسم</th>
                        <th>القسم الرئيسي</th>
                        <th class="text-center">المنتجات</th>
                        <th class="text-center">الرئيسية</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width: 150px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        @php
                            $catNameAr = is_array($category->name) ? ($category->name['ar'] ?? reset($category->name)) : $category->name;
                            $catNameEn = is_array($category->name) ? ($category->name['en'] ?? '') : '';
                        @endphp
                        <tr id="category-row-{{ $category->id }}">
                            <td class="text-muted">{{ $loop->iteration + ($categories->currentPage() - 1) * $categories->perPage() }}</td>
                            <td>
                                @if ($category->image)
                                    <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $catNameAr }}" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                @else
                                    <div class="avatar avatar-sm bg-label-primary rounded d-flex align-items-center justify-content-center">
                                        <i class="ti ti-category fs-4"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $catNameAr }}</div>
                                @if($catNameEn)
                                    <small class="text-muted d-block">{{ $catNameEn }}</small>
                                @endif
                                <small class="text-muted">slug: {{ $category->slug }}</small>
                            </td>
                            <td>
                                @if ($category->parent)
                                    <span class="badge bg-label-info">
                                        <i class="ti ti-subtask me-1"></i>
                                        {{ is_array($category->parent->name) ? ($category->parent->name['ar'] ?? reset($category->parent->name)) : $category->parent->name }}
                                    </span>
                                @else
                                    <span class="badge bg-label-primary">قسم رئيسي</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-label-secondary fw-semibold">
                                    {{ $category->products_count ?? $category->products()->count() }} منتج
                                </span>
                            </td>
                            <td class="text-center">
                                @if($category->appear_in_home)
                                    <span class="badge bg-label-success">نعم</span>
                                @else
                                    <span class="badge bg-label-secondary">لا</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-icon btn-toggle-status {{ $category->status_id == 1 ? 'btn-label-success' : 'btn-label-danger' }}" 
                                        data-id="{{ $category->id }}" title="{{ $category->status_id == 1 ? 'نشط - اضغط للتعطيل' : 'معطل - اضغط للتفعيل' }}">
                                    <i class="ti {{ $category->status_id == 1 ? 'ti-check' : 'ti-x' }} fs-5"></i>
                                </button>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    @if (!$category->parent_id)
                                        <a href="{{ route('admin.categories.create', ['parent_id' => $category->id]) }}" class="btn btn-sm btn-icon btn-outline-info" title="إضافة قسم فرعي">
                                            <i class="ti ti-plus"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-icon btn-outline-primary" title="تعديل">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-delete-cat" 
                                            data-id="{{ $category->id }}" data-name="{{ $catNameAr }}" title="حذف">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-folder-off fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>لا توجد أقسام مطابقة للبحث</h5>
                                    <a href="{{ route('admin.categories.create') }}" class="btn btn-sm btn-primary mt-2">
                                        إضافة قسم جديد
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="card-footer border-top d-flex justify-content-between align-items-center">
                <small class="text-muted">عرض {{ $categories->firstItem() }} إلى {{ $categories->lastItem() }} من أصل {{ $categories->total() }} قسم</small>
                <div>{{ $categories->links() }}</div>
            </div>
        @endif
    </div>
</div>

<form id="deleteCatForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تبديل الحالة
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const catId = this.dataset.id;
            const button = this;
            button.disabled = true;

            fetch("{{ url('admin/categories') }}/" + catId + "/toggle-status", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                button.disabled = false;
                if (data.success) {
                    if (data.is_active || data.status_id == 1) {
                        button.className = 'btn btn-sm btn-icon btn-toggle-status btn-label-success';
                        button.innerHTML = '<i class="ti ti-check fs-5"></i>';
                        button.title = 'نشط - اضغط للتعطيل';
                    } else {
                        button.className = 'btn btn-sm btn-icon btn-toggle-status btn-label-danger';
                        button.innerHTML = '<i class="ti ti-x fs-5"></i>';
                        button.title = 'معطل - اضغط للتفعيل';
                    }
                }
            })
            .catch(() => {
                button.disabled = false;
                alert('حدث خطأ أثناء تغيير الحالة');
            });
        });
    });

    // حذف قسم
    document.querySelectorAll('.btn-delete-cat').forEach(btn => {
        btn.addEventListener('click', function() {
            const catId = this.dataset.id;
            const catName = this.dataset.name;

            if (confirm(`هل أنت متأكد من حذف القسم "${catName}"؟`)) {
                const form = document.getElementById('deleteCatForm');
                form.action = "{{ url('admin/categories') }}/" + catId;
                form.submit();
            }
        });
    });
});
</script>
@endsection
