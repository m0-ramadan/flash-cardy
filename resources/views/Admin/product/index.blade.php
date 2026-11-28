@extends('Admin.layout.master')

@section('title', 'إدارة المنتجات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1">إدارة المنتجات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المنتجات</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-warning" id="syncFromLdBtn" onclick="syncFromLd()">
                <i class="ti ti-refresh me-1"></i> مزامنة المنتجات (LikeCard)
            </button>
            <button type="button" class="btn btn-info" id="downloadImagesBtn" onclick="downloadProductImages()">
                <i class="ti ti-photo-down me-1"></i> مزامنة الصور
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="exportProducts()">
                <i class="ti ti-file-export me-1"></i> تصدير
            </button>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> إضافة منتج جديد
            </a>
        </div>
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

    <!-- شريط الإجراءات الجماعية -->
    <div class="card mb-3 shadow-sm border-primary d-none" id="bulkActionsBar">
        <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary" id="selectedCountBadge">0</span>
                <span class="fw-semibold">منتج محدد</span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <select class="form-select form-select-sm" id="bulkActionSelect" style="width: 180px;">
                    <option value="">اختر إجراء جماعي...</option>
                    <option value="activate">تفعيل المحدد</option>
                    <option value="deactivate">تعطيل المحدد</option>
                    <option value="draft">تحويل لمسودة</option>
                    <option value="delete">حذف المحدد</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" onclick="applyBulkAction()">
                    <i class="ti ti-check me-1"></i> تنفيذ
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                    إلغاء التحديد
                </button>
            </div>
        </div>
    </div>

    <!-- جدول المنتجات -->
    <div class="card shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">
                            <input class="form-check-input" type="checkbox" id="selectAllProducts" title="تحديد الكل">
                        </th>
                        <th style="width: 55px;">#</th>
                        <th style="width: 75px;">الصورة</th>
                        <th>المنتج</th>
                        <th>القسم</th>
                        <th>السعر</th>
                        <th>المخزون / التسليم</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width: 210px;">الإجراءات</th>
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
                            <td>
                                <input class="form-check-input product-checkbox" type="checkbox" value="{{ $product->id }}">
                            </td>
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
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-toggle-status {{ $product->status_id == 1 ? 'btn-label-success' : 'btn-label-danger' }}"
                                    data-url="{{ route('admin.products.toggle-status', $product->id) }}"
                                    title="{{ $product->status_id == 1 ? 'نشط - اضغط للتعطيل' : 'معطل - اضغط للتفعيل' }}">
                                    <i class="ti {{ $product->status_id == 1 ? 'ti-check' : 'ti-x' }} fs-5"></i>
                                </button>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    @if($product->external_id || $product->fulfillment_source === 'like4app')
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-warning"
                                            onclick="syncSingleProduct({{ $product->id }})" title="مزامنة المنتج من LikeCard">
                                            <i class="ti ti-refresh"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('admin.products.show', $product->id) }}" class="btn btn-sm btn-icon btn-outline-info" title="عرض التفاصيل والأكواد">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-sm btn-icon btn-outline-primary" title="تعديل">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.products.duplicate', $product->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد إنشاء نسخة مسودة من هذا المنتج؟')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" title="نسخ المنتج">
                                            <i class="ti ti-copy"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-delete-prod"
                                            data-id="{{ $product->id }}" data-name="{{ $prodNameAr }}" title="حذف">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const csrfToken = '{{ csrf_token() }}';

function updateBulkBar() {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    const bar = document.getElementById('bulkActionsBar');
    const badge = document.getElementById('selectedCountBadge');
    badge.textContent = checked.length;
    bar.classList.toggle('d-none', checked.length === 0);
}

window.clearSelection = function() {
    document.querySelectorAll('.product-checkbox, #selectAllProducts').forEach(cb => cb.checked = false);
    updateBulkBar();
};

window.applyBulkAction = async function() {
    const ids = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => Number(cb.value));
    const action = document.getElementById('bulkActionSelect').value;

    if (!ids.length) {
        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تحديد منتج واحد على الأقل' });
        return;
    }
    if (!action) {
        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى اختيار الإجراء المطلوب' });
        return;
    }

    const confirmRes = await Swal.fire({
        title: 'تأكيد الإجراء الجماعي',
        text: `سيتم تنفيذ الإجراء على ${ids.length} منتج`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'تنفيذ',
        cancelButtonText: 'إلغاء'
    });

    if (!confirmRes.isConfirmed) return;

    Swal.fire({ title: 'جاري التنفيذ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('{{ route('admin.products.bulk-action') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ids, action })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'حدث خطأ أثناء تنفيذ الإجراء');
        }
        Swal.fire({ icon: 'success', title: 'تم بنجاح', text: data.message, timer: 1400, showConfirmButton: false })
            .then(() => location.reload());
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: err.message });
    }
};

window.exportProducts = function() {
    const filters = new URLSearchParams(window.location.search);
    const url = '{{ route('admin.products.export') }}' + (filters.toString() ? '?' + filters.toString() : '');
    window.open(url, '_blank');
};

window.syncFromLd = async function() {
    let nextPage = 1;
    let finished = false;
    let totalNew = 0;
    let totalUpdated = 0;
    let totalUnchanged = 0;
    let totalFailed = 0;

    Swal.fire({
        title: 'جاري مزامنة المنتجات من LikeCard...',
        allowOutsideClick: false,
        didOpen: async () => {
            Swal.showLoading();
            try {
                while (!finished) {
                    const response = await fetch('{{ route('admin.products.sync-from-like4app-batch') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ page: nextPage })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'حدث خطأ أثناء مزامنة المنتجات');
                    }

                    totalNew += data.new_count ?? 0;
                    totalUpdated += data.updated_count ?? 0;
                    totalUnchanged += data.unchanged_count ?? 0;
                    totalFailed += data.failed_count ?? 0;
                    finished = !!data.finished;
                    nextPage = data.next_page ?? (nextPage + 1);

                    Swal.update({
                        html: `
                            <div class="text-end" dir="rtl">
                                <p class="mb-1">الصفحة الحالية: <strong>${nextPage - 1}</strong></p>
                                <p class="mb-1 text-success">منتجات جديدة: <strong>${totalNew}</strong></p>
                                <p class="mb-1 text-primary">منتجات محدثة: <strong>${totalUpdated}</strong></p>
                                <p class="mb-1 text-muted">لم تتغير: <strong>${totalUnchanged}</strong></p>
                                <p class="mb-0 text-danger">فشل: <strong>${totalFailed}</strong></p>
                            </div>
                        `
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: 'تمت مزامنة المنتجات بنجاح',
                    html: `تمت إضافة <b>${totalNew}</b> منتج وتحديث <b>${totalUpdated}</b> منتج. يمكنك الآن تشغيل مزامنة الصور.`
                }).then(() => location.reload());
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'خطأ في المزامنة', text: error.message });
            }
        }
    });
};

window.downloadProductImages = async function() {
    let finished = false;
    let totalProcessed = 0;

    Swal.fire({
        title: 'جاري تحميل ومزامنة صور المنتجات...',
        allowOutsideClick: false,
        didOpen: async () => {
            Swal.showLoading();
            try {
                while (!finished) {
                    const response = await fetch('{{ route('admin.products.download-images-batch') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ limit: 20 })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'حدث خطأ أثناء تحميل الصور');
                    }

                    totalProcessed += data.processed ?? 0;
                    finished = !!data.finished;

                    Swal.update({ html: `<p>تمت معالجة <strong>${totalProcessed}</strong> صورة</p>` });
                }

                Swal.fire({ icon: 'success', title: 'تمت مزامنة الصور بنجاح' }).then(() => location.reload());
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'خطأ', text: error.message });
            }
        }
    });
};

window.syncSingleProduct = function(productId) {
    Swal.fire({
        title: 'مزامنة المنتج',
        text: 'هل تريد مزامنة بيانات وسعر هذا المنتج من LikeCard؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، مزامنة',
        cancelButtonText: 'إلغاء',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch(`{{ url('admin/products') }}/${productId}/sync-from-ld`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'حدث خطأ أثناء مزامنة المنتج');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`خطأ: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'تمت المزامنة',
                text: result.value?.message || 'تم تحديث المنتج بنجاح',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        }
    });
};

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllProducts');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }

    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });

    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const url = this.dataset.url;
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'فشل تغيير الحالة');
                const isActive = Number(data.status_id) === 1;
                this.classList.toggle('btn-label-success', isActive);
                this.classList.toggle('btn-label-danger', !isActive);
                this.title = isActive ? 'نشط - اضغط للتعطيل' : 'معطل - اضغط للتفعيل';
                const icon = this.querySelector('i');
                icon.classList.toggle('ti-check', isActive);
                icon.classList.toggle('ti-x', !isActive);
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'خطأ', text: err.message });
            }
        });
    });

    document.querySelectorAll('.btn-delete-prod').forEach(btn => {
        btn.addEventListener('click', function() {
            const prodId = this.dataset.id;
            const prodName = this.dataset.name;
            Swal.fire({
                title: 'تأكيد الحذف',
                text: `هل أنت متأكد من حذف المنتج "${prodName}"؟`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ea5455',
                confirmButtonText: 'نعم، حذف',
                cancelButtonText: 'إلغاء'
            }).then(result => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteProdForm');
                    form.action = "{{ url('admin/products') }}/" + prodId;
                    form.submit();
                }
            });
        });
    });
});
</script>
@endsection
