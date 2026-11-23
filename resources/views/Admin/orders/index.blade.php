@extends('Admin.layout.master')

@section('title', 'إدارة الطلبات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إدارة الطلبات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الطلبات</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            @if(Route::has('admin.orders.export'))
                <a href="{{ route('admin.orders.export', request()->query()) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-download me-1"></i> تصدير الطلبات
                </a>
            @endif
            <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> إنشاء طلب جديد
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
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-muted d-block small mb-1">إجمالي الطلبات</span>
                    <h5 class="mb-0 fw-bold">{{ number_format($stats['total'] ?? 0) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-warning d-block small mb-1"><i class="ti ti-clock me-1"></i> قيد الانتظار</span>
                    <h5 class="mb-0 fw-bold text-warning">{{ number_format($stats['pending'] ?? 0) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-info d-block small mb-1"><i class="ti ti-loader me-1"></i> جاري التجهيز</span>
                    <h5 class="mb-0 fw-bold text-info">{{ number_format($stats['processing'] ?? 0) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-success d-block small mb-1"><i class="ti ti-cash-banknote me-1"></i> تم الدفع</span>
                    <h5 class="mb-0 fw-bold text-success">{{ number_format($stats['paid'] ?? 0) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-danger d-block small mb-1"><i class="ti ti-x me-1"></i> ملغية</span>
                    <h5 class="mb-0 fw-bold text-danger">{{ number_format($stats['cancelled'] ?? 0) }}</h5>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <span class="text-primary d-block small mb-1"><i class="ti ti-cash me-1"></i> الإيرادات</span>
                    <h5 class="mb-0 fw-bold text-primary">{{ number_format($stats['total_revenue'] ?? 0, 0) }} {{ config('commerce.base_currency', 'ر.س') }}</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- تصفية وبحث -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.orders.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="search">بحث برقم الطلب أو العميل</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="رقم الطلب، الاسم، الهاتف...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">حالة الطلب</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">جميع الحالات</option>
                        @foreach (\App\Models\Order::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_method">طريقة الدفع</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">جميع الطرق</option>
                        <option value="wallet" @selected(request('payment_method') === 'wallet')>المحفظة (Wallet)</option>
                        <option value="paymob" @selected(request('payment_method') === 'paymob')>بطاقة ائتمان / Paymob</option>
                        <option value="nowpayments" @selected(request('payment_method') === 'nowpayments')>عملات رقمية / NOWPayments</option>
                        <option value="tamara" @selected(request('payment_method') === 'tamara')>تمارا</option>
                        <option value="tabby" @selected(request('payment_method') === 'tabby')>تابي</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> تصفية
                    </button>
                    @if(request()->anyFilled(['search', 'status', 'payment_method']))
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary" title="إلغاء الفلتر">
                            <i class="ti ti-rotate"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- جدول الطلبات -->
    <div class="card shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المنتجات</th>
                        <th>المبلغ</th>
                        <th>طريقة الدفع</th>
                        <th class="text-center">حالة الطلب</th>
                        <th>التاريخ</th>
                        <th class="text-center" style="width: 140px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $statusBadge = \App\Models\Order::statusBadge($order->status);
                            $statusName = \App\Models\Order::statusLabel($order->status);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="fw-bold text-primary text-decoration-none">
                                    #{{ $order->order_number }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $order->customer_name ?: ($order->user?->name ?? 'عميل زائر') }}</div>
                                @if($order->customer_phone || $order->user?->phone)
                                    <small class="text-muted d-block"><i class="ti ti-phone me-1"></i>{{ $order->customer_phone ?: $order->user?->phone }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-label-secondary">
                                    <i class="ti ti-package me-1"></i> {{ $order->items->sum('quantity') }} عنصر
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ number_format($order->total_amount, 2) }} {{ $order->currency ?: config('commerce.base_currency', 'ر.س') }}</div>
                            </td>
                            <td>
                                <span class="badge bg-label-primary text-uppercase">{{ $order->payment_method ?: 'غير محدد' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $statusBadge }} fw-semibold">{{ $statusName }}</span>
                            </td>
                            <td>
                                <small class="text-muted d-block">{{ $order->created_at->format('Y-m-d') }}</small>
                                <small class="text-muted">{{ $order->created_at->format('h:i A') }}</small>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-icon btn-outline-info" title="عرض التفاصيل والأكواد">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.orders.print', $order->id) }}" target="_blank" class="btn btn-sm btn-icon btn-outline-secondary" title="طباعة الفاتورة">
                                        <i class="ti ti-printer"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-delete-order" 
                                            data-id="{{ $order->id }}" data-num="{{ $order->order_number }}" title="حذف">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-shopping-cart-off fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>لا توجد طلبات مطابقة للبحث</h5>
                                    <a href="{{ route('admin.orders.create') }}" class="btn btn-sm btn-primary mt-2">
                                        إنشاء طلب جديد
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <small class="text-muted">عرض {{ $orders->firstItem() }} إلى {{ $orders->lastItem() }} من أصل {{ $orders->total() }} طلب</small>
                <div>{{ $orders->links() }}</div>
            </div>
        @endif
    </div>
</div>

<form id="deleteOrderForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-delete-order').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.id;
            const orderNum = this.dataset.num;

            if (confirm(`هل أنت متأكد من حذف الطلب رقم #${orderNum}؟`)) {
                const form = document.getElementById('deleteOrderForm');
                form.action = "{{ url('admin/orders') }}/" + orderId;
                form.submit();
            }
        });
    });
});
</script>
@endsection
