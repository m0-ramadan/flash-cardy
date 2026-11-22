@extends('Admin.layout.master')

@section('title', 'إدارة المنتجات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إدارة المنتجات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المنتجات</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> إضافة منتج جديد
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

    <!-- بطاقات الإحصائيات -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">إجمالي المنتجات</span>
                            <h4 class="mb-0">{{ number_format($totalProducts) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-shopping-bag fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">المنتجات النشطة</span>
                            <h4 class="mb-0 text-success">{{ number_format($activeProducts) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-circle-check fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">المنتجات المعطلة</span>
                            <h4 class="mb-0 text-secondary">{{ number_format($inactiveProducts) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="ti ti-circle-x fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">منخفض المخزون</span>
                            <h4 class="mb-0 text-warning">{{ number_format($lowStockProducts) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-alert-triangle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- فلترة وبحث -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.products.index') }}" method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="search">بحث باسم المنتج أو الكود</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="ابحث عن اسم المنتج...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="category_id">القسم</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">جميع الأقسام</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>
                                {{ is_array($cat->name) ? ($cat->name['ar'] ?? reset($cat->name)) : $cat->name }}
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
                    @if(request()->anyFilled(['search', 'category_id', 'status_id']))
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary" title="إلغاء الفلتر">
                            <i class="ti ti-rotate"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- جدول المنتجات -->
    <div class="card shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th style="width: 80px;">الصورة</th>
                        <th>المنتج</th>
                        <th>القسم</th>
                        <th>السعر</th>
                        <th>المخزون / التسليم</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width: 160px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $prodNameAr = is_array($product->name) ? ($product->name['ar'] ?? reset($product->name)) : (method_exists($product, 'getTranslation') ? $product->getTranslation('name', 'ar') : $product->name);
                            $prodNameEn = is_array($product->name) ? ($product->name['en'] ?? '') : (method_exists($product, 'getTranslation') ? $product->getTranslation('name', 'en') : '');
                            $imagePath = $product->primaryImage?->path ?? $product->image_path;
                            $curr = $product->sign_currency ?: config('commerce.base_currency', 'ر.س');
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration + ($products->currentPage() - 1) * $products->perPage() }}</td>
                            <td>
                                @if ($imagePath)
                                    <img src="{{ asset('storage/' . $imagePath) }}" alt="{{ $prodNameAr }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                @else
                                    <div class="avatar avatar-sm bg-label-secondary rounded d-flex align-items-center justify-content-center">
                                        <i class="ti ti-photo fs-4"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.products.show', $product->id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $prodNameAr }}
                                </a>
                                @if($prodNameEn)
                                    <small class="text-muted d-block">{{ $prodNameEn }}</small>
                                @endif
                                @if($product->sku)
                                    <small class="badge bg-label-secondary mt-1">SKU: {{ $product->sku }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($product->category)
                                    <span class="badge bg-label-info">
                                        {{ is_array($product->category->name) ? ($product->category->name['ar'] ?? reset($product->category->name)) : $product->category->name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($product->has_discount && $product->discount && $product->discount->is_active)
                                    <div class="fw-bold text-success">{{ number_format($product->final_price ?? $product->price, 2) }} {{ $curr }}</div>
                                    <small class="text-muted text-decoration-line-through">{{ number_format($product->price, 2) }}</small>
                                @else
                                    <div class="fw-bold text-dark">{{ number_format($product->price, 2) }} {{ $curr }}</div>
                                @endif
                            </td>
                            <td>
                                @if($product->fulfillment_source === 'local_serials')
                                    @php $usableSerials = $product->serials()->usable()->count(); @endphp
                                    <a href="{{ route('admin.products.serials.index', $product->id) }}" class="badge {{ $usableSerials > 0 ? 'bg-label-success' : 'bg-label-danger' }} text-decoration-none" title="إدارة الأكواد الرقمية">
                                        <i class="ti ti-barcode me-1"></i> {{ $usableSerials }} كود جاهز
                                    </a>
                                @elseif($product->fulfillment_source === 'like4app')
                                    <span class="badge bg-label-info"><i class="ti ti-cloud me-1"></i> Like4App</span>
                                @else
                                    <span class="badge bg-label-secondary">{{ $product->stock }} متوفر</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.products.toggle-status', $product->id) }}" class="btn btn-sm btn-icon {{ $product->status_id == 1 ? 'btn-label-success' : 'btn-label-danger' }}" title="{{ $product->status_id == 1 ? 'نشط - اضغط للتعطيل' : 'معطل - اضغط للتفعيل' }}">
                                    <i class="ti {{ $product->status_id == 1 ? 'ti-check' : 'ti-x' }} fs-5"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.products.show', $product->id) }}" class="btn btn-sm btn-icon btn-outline-info" title="عرض التفاصيل والأكواد">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-sm btn-icon btn-outline-primary" title="تعديل">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-delete-prod" 
                                            data-id="{{ $product->id }}" data-name="{{ $prodNameAr }}" title="حذف">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-package-off fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>لا توجد منتجات مطابقة</h5>
                                    <a href="{{ route('admin.products.create') }}" class="btn btn-sm btn-primary mt-2">
                                        إضافة منتج جديد
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <small class="text-muted">عرض {{ $products->firstItem() }} إلى {{ $products->lastItem() }} من أصل {{ $products->total() }} منتج</small>
                <div>{{ $products->links() }}</div>
            </div>
        @endif
    </div>
</div>

<form id="deleteProdForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-delete-prod').forEach(btn => {
        btn.addEventListener('click', function() {
            const prodId = this.dataset.id;
            const prodName = this.dataset.name;

            if (confirm(`هل أنت متأكد من حذف المنتج "${prodName}"؟`)) {
                const form = document.getElementById('deleteProdForm');
                form.action = "{{ url('admin/products') }}/" + prodId;
                form.submit();
            }
        });
    });
});
</script>
@endsection
