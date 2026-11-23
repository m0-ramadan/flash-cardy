@extends('Admin.layout.master')

@section('title', 'تفاصيل الطلب: #' . $order->order_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">تفاصيل الطلب <span class="text-primary">#{{ $order->order_number }}</span></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">الطلبات</a></li>
                    <li class="breadcrumb-item active">#{{ $order->order_number }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.orders.print', $order->id) }}" target="_blank" class="btn btn-outline-secondary">
                <i class="ti ti-printer me-1"></i> طباعة الفاتورة
            </a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary">
                <i class="ti ti-arrow-right me-1"></i> رجوع للطلبات
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

    <div class="row">
        <!-- عمود تفاصيل المنتجات والتسليم -->
        <div class="col-lg-8">
            <!-- قائمة المنتجات المطلوبة -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">منتجات الطلب</h5>
                    <span class="badge bg-label-secondary">{{ $order->items->count() }} منتجات</span>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>المنتج</th>
                                <th>سعر الوحدة</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-end">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                @php
                                    $prodName = $item->product ? (is_array($item->product->name) ? ($item->product->name['ar'] ?? reset($item->product->name)) : $item->product->name) : ($item->product_name ?? 'منتج غير معروف');
                                    $curr = $order->currency ?: config('commerce.base_currency', 'ر.س');
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $prodName }}</div>
                                        @if($item->product && $item->product->sku)
                                            <small class="text-muted">SKU: {{ $item->product->sku }}</small>
                                        @endif
                                    </td>
                                    <td>{{ number_format($item->unit_price, 2) }} {{ $curr }}</td>
                                    <td class="text-center"><span class="badge bg-label-primary px-3">{{ $item->quantity }}</span></td>
                                    <td class="text-end fw-bold text-dark">{{ number_format($item->total_price, 2) }} {{ $curr }}</td>
                                </tr>

                                <!-- الأكواد الرقمية وبطاقات الشحن التابعة لهذا العنصر -->
                                <tr>
                                    <td colspan="4" class="p-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 text-primary"><i class="ti ti-barcode me-1"></i> أكواد التسليم الرقمي (Serials / Vouchers)</h6>
                                            <span class="badge bg-label-info">حالة التسليم: {{ $item->serial_status ?: 'pending' }}</span>
                                        </div>

                                        @if(!empty($item->serial_codes) && is_array($item->serial_codes) && count($item->serial_codes) > 0)
                                            <div class="table-responsive rounded border mb-2">
                                                <table class="table table-sm table-bordered align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>كود البطاقة (Voucher Code)</th>
                                                            <th>رقم الكود / PIN</th>
                                                            <th class="text-center">الحالة</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item->serial_codes as $idx => $serial)
                                                            <tr>
                                                                <td style="width: 40px;">{{ $idx + 1 }}</td>
                                                                <td>
                                                                    <code class="fw-bold fs-6 select-all">{{ $serial['voucher_code'] ?? ($serial['serial_code'] ?? '—') }}</code>
                                                                </td>
                                                                <td>
                                                                    @if(!empty($serial['pin_plain']))
                                                                        <span class="badge bg-label-warning">PIN: {{ $serial['pin_plain'] }}</span>
                                                                    @elseif(!empty($serial['serial_number']))
                                                                        <span class="text-muted">{{ $serial['serial_number'] }}</span>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-success">تم التسليم</span>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="alert alert-warning py-2 small mb-2">
                                                <i class="ti ti-alert-triangle me-1"></i> لم يتم تسليم أو تعيين أكواد رقمية لهذا العنصر بعد.
                                            </div>
                                        @endif

                                        <!-- خيارات تعيين وإرسال الأكواد يدوياً -->
                                        @if($item->serial_status !== 'delivered' && ($item->product?->fulfillment_source === 'local_serials' || !empty($item->product?->serials)))
                                            <div class="card p-3 border border-primary border-opacity-25 mt-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="small fw-bold">إدارة تسليم الأكواد لهذا المنتج:</span>
                                                    <small class="text-muted">المخزون المتاح حالياً من الأكواد: <strong class="text-success">{{ $item->product?->serials()->usable()->count() ?? 0 }}</strong></small>
                                                </div>

                                                <div class="row g-2">
                                                    <!-- تحويل من الأكواد الجاهزة -->
                                                    <div class="col-md-6">
                                                        <form action="{{ route('admin.orders.items.serials.assign-available', $item) }}" method="POST">
                                                            @csrf
                                                            <button class="btn btn-sm btn-success w-100" type="submit">
                                                                <i class="ti ti-send me-1"></i> تعيين وإرسال من الأكواد الجاهزة
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- أو إدخال كود فوري وتعيينه -->
                                                    <div class="col-md-6">
                                                        <form action="{{ route('admin.orders.items.serials.add-and-assign', $item) }}" method="POST" class="d-flex gap-1">
                                                            @csrf
                                                            <input type="text" name="code" class="form-control form-control-sm" placeholder="أدخل كود فوري هنا..." required>
                                                            <button class="btn btn-sm btn-primary text-nowrap" type="submit">
                                                                <i class="ti ti-plus me-1"></i> إضافة وإرسال
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- الفاتورة والملخص المالي -->
                <div class="card-footer bg-light border-top p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            @if($order->notes)
                                <h6 class="fw-bold mb-1">ملاحظات العميل / الطلب:</h6>
                                <p class="text-muted small mb-0">{{ $order->notes }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">المجموع الفرعي:</span>
                                <span class="fw-semibold">{{ number_format($order->subtotal ?: $order->total_amount, 2) }} {{ $curr }}</span>
                            </div>
                            @if($order->discount_amount > 0)
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>الخصم:</span>
                                    <span>-{{ number_format($order->discount_amount, 2) }} {{ $curr }}</span>
                                </div>
                            @endif
                            @if($order->tax_amount > 0)
                                <div class="d-flex justify-content-between mb-2 text-muted">
                                    <span>الضريبة:</span>
                                    <span>+{{ number_format($order->tax_amount, 2) }} {{ $curr }}</span>
                                </div>
                            @endif
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold fs-6">الإجمالي النهائي:</span>
                                <span class="fw-bold fs-5 text-primary">{{ number_format($order->total_amount, 2) }} {{ $curr }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- عمود الحالة وبيانات العميل -->
        <div class="col-lg-4">
            <!-- تحديث حالة الطلب -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">حالة الطلب والدفع</h5>
                </div>
                <div class="card-body pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">حالة الطلب الحالية:</span>
                        @php
                            $badge = str_replace('bg-label-', 'bg-', \App\Models\Order::statusBadge($order->status));
                            $label = \App\Models\Order::statusLabel($order->status);
                        @endphp
                        <span class="badge {{ $badge }} fs-6 px-3 py-1">{{ $label }}</span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">طريقة الدفع:</span>
                        <span class="badge bg-label-primary text-uppercase">{{ $order->payment_method ?: 'غير محدد' }}</span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">حالة الدفع:</span>
                        <span class="badge {{ in_array($order->payment_state, ['paid','finished','confirmed','success']) ? 'bg-label-success' : 'bg-label-warning' }}">
                            {{ $order->payment_state ?: $order->status_payment ?: 'pending' }}
                        </span>
                    </div>

                    <hr>

                    <form action="{{ route('admin.orders.update-status', $order->id) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="update_status">تغيير حالة الطلب</label>
                            <select class="form-select" id="update_status" name="status" required>
                                @foreach (\App\Models\Order::selectableStatuses() as $value => $label)
                                    <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="status_notes">ملاحظة التحديث (اختياري)</label>
                            <textarea class="form-control" id="status_notes" name="notes" rows="2" placeholder="اكتب سبب تغيير الحالة أو ملاحظة..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-check me-1"></i> حفظ وتحديث الحالة
                        </button>
                    </form>
                </div>
            </div>

            <!-- بيانات العميل -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">بيانات العميل</h5>
                </div>
                <div class="card-body pt-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar avatar-md bg-label-primary rounded me-3 d-flex align-items-center justify-content-center">
                            <i class="ti ti-user fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-dark">{{ $order->customer_name ?: ($order->user?->name ?? 'عميل زائر') }}</h6>
                            <small class="text-muted">عميل مسجل: {{ $order->user ? 'نعم' : 'لا' }}</small>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0">
                        @if($order->customer_email || $order->user?->email)
                            <li class="mb-2">
                                <span class="text-muted d-block small">البريد الإلكتروني:</span>
                                <span class="fw-semibold text-dark">{{ $order->customer_email ?: $order->user?->email }}</span>
                            </li>
                        @endif
                        @if($order->customer_phone || $order->user?->phone)
                            <li class="mb-2">
                                <span class="text-muted d-block small">رقم الهاتف:</span>
                                <span class="fw-semibold text-dark">{{ $order->customer_phone ?: $order->user?->phone }}</span>
                            </li>
                        @endif
                        <li class="mb-0">
                            <span class="text-muted d-block small">تاريخ الطلب:</span>
                            <span class="fw-semibold text-dark">{{ $order->created_at->format('Y-m-d — h:i A') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
