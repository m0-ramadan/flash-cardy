@extends('Admin.layout.master')

@section('title', 'إدارة البانرات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إدارة البانرات والسلايدر</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">البانرات</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> إضافة بانر جديد
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

    <!-- جدول البانرات -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">قائمة البانرات</h5>
            <div class="badge bg-label-primary">{{ $banners->total() }} بانر</div>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>عنوان البانر</th>
                        <th>نوع البانر</th>
                        <th class="text-center">عدد العناصر (الصور)</th>
                        <th class="text-center">الترتيب</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width: 150px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($banners as $banner)
                        @php
                            $typeName = match($banner->type?->name ?? '') {
                                'main_slider' => 'سلايدر رئيسي (الصفحة الرئيسية)',
                                'image_card' => 'بطاقة إعلانية',
                                'category_slider' => 'سلايدر أقسام',
                                'product_slider' => 'سلايدر منتجات',
                                default => $banner->type?->name ?? 'افتراضي'
                            };
                            $itemsCount = $banner->items?->count() ?? 0;
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration + ($banners->currentPage() - 1) * $banners->perPage() }}</td>
                            <td>
                                <a href="{{ route('admin.banners.edit', $banner) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $banner->title }}
                                </a>
                                @if($banner->category)
                                    <small class="text-muted d-block">قسم: {{ is_array($banner->category->name) ? ($banner->category->name['ar'] ?? reset($banner->category->name)) : $banner->category->name }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $banner->type?->name === 'main_slider' ? 'bg-label-primary' : 'bg-label-info' }}">
                                    {{ $typeName }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $itemsCount > 0 ? 'bg-label-success' : 'bg-label-warning' }} fw-semibold">
                                    <i class="ti ti-photo me-1"></i> {{ $itemsCount }} صور
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-label-secondary">{{ $banner->section_order }}</span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-icon btn-toggle-banner {{ $banner->is_active ? 'btn-label-success' : 'btn-label-danger' }}"
                                        data-id="{{ $banner->id }}" title="{{ $banner->is_active ? 'نشط - اضغط للتعطيل' : 'معطل - اضغط للتفعيل' }}">
                                    <i class="ti {{ $banner->is_active ? 'ti-check' : 'ti-x' }} fs-5"></i>
                                </button>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-primary" title="إدارة الصور والتعديل">
                                        <i class="ti ti-edit me-1"></i> تعديل والصور
                                    </a>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-delete-banner" 
                                            data-id="{{ $banner->id }}" data-title="{{ $banner->title }}" title="حذف">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-photo-off fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>لا توجد بانرات مسجلة</h5>
                                    <a href="{{ route('admin.banners.create') }}" class="btn btn-sm btn-primary mt-2">
                                        إضافة بانر جديد
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($banners->hasPages())
            <div class="card-footer border-top d-flex justify-content-between align-items-center">
                <small class="text-muted">عرض {{ $banners->firstItem() }} إلى {{ $banners->lastItem() }} من أصل {{ $banners->total() }} بانر</small>
                <div>{{ $banners->links() }}</div>
            </div>
        @endif
    </div>
</div>

<form id="deleteBannerForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تبديل حالة البانر
    document.querySelectorAll('.btn-toggle-banner').forEach(btn => {
        btn.addEventListener('click', function() {
            const bannerId = this.dataset.id;
            const button = this;
            button.disabled = true;

            fetch("{{ url('admin/banners') }}/" + bannerId + "/toggle-status", {
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
                    if (data.is_active) {
                        button.className = 'btn btn-sm btn-icon btn-toggle-banner btn-label-success';
                        button.innerHTML = '<i class="ti ti-check fs-5"></i>';
                        button.title = 'نشط - اضغط للتعطيل';
                    } else {
                        button.className = 'btn btn-sm btn-icon btn-toggle-banner btn-label-danger';
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

    // حذف بانر
    document.querySelectorAll('.btn-delete-banner').forEach(btn => {
        btn.addEventListener('click', function() {
            const bannerId = this.dataset.id;
            const title = this.dataset.title;

            if (confirm(`هل أنت متأكد من حذف البانر "${title}"؟`)) {
                const form = document.getElementById('deleteBannerForm');
                form.action = "{{ url('admin/banners') }}/" + bannerId;
                form.submit();
            }
        });
    });
});
</script>
@endsection
