@extends('Admin.layout.master')

@section('title', 'إنشاء طلب جديد')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">إنشاء طلب جديد</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">الطلبات</a></li>
                    <li class="breadcrumb-item active">إنشاء طلب</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
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

    <form action="{{ route('admin.orders.store') }}" method="POST" id="orderForm">
        @csrf

        <div class="row">
            <!-- تفاصيل العميل والدفع -->
            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">بيانات العميل</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div class="mb-3">
                            <label class="form-label" for="user_id">اختيار العميل <span class="text-danger">*</span></label>
                            <select class="form-select" id="user_id" name="user_id" required onchange="fillCustomerInfo(this)">
                                <option value="">اختر العميل المسجل...</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-phone="{{ $u->phone }}" @selected(old('user_id') == $u->id)>
                                        {{ $u->name }} ({{ $u->email ?: $u->phone }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="customer_name">اسم العميل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="customer_phone">رقم الهاتف</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="customer_email">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" value="{{ old('customer_email') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="payment_method">طريقة الدفع <span class="text-danger">*</span></label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="manual">دفع يدوي / تحويل بنكي</option>
                                <option value="wallet">المحفظة (Wallet)</option>
                                <option value="paymob">بطاقة ائتمان / Paymob</option>
                                <option value="nowpayments">عملات رقمية / NOWPayments</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="notes">ملاحظات الطلب</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="ملاحظات إضافية...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اختيار المنتجات والملخص -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">منتجات الطلب</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProductRow()">
                            <i class="ti ti-plus me-1"></i> إضافة منتج
                        </button>
                    </div>
                    <div class="card-body pt-3">
                        <div id="products-container">
                            <div class="row g-2 mb-3 product-row align-items-end border-bottom pb-3">
                                <div class="col-md-7">
                                    <label class="form-label small">المنتج <span class="text-danger">*</span></label>
                                    <select class="form-select product-select" name="items[0][product_id]" required onchange="calculateTotal()">
                                        <option value="">اختر المنتج...</option>
                                        @foreach($products as $prod)
                                            @php
                                                $pName = is_array($prod->name) ? ($prod->name['ar'] ?? reset($prod->name)) : $prod->name;
                                            @endphp
                                            <option value="{{ $prod->id }}" data-price="{{ $prod->final_price ?? $prod->price }}">
                                                {{ $pName }} ({{ number_format($prod->final_price ?? $prod->price, 2) }} {{ config('commerce.base_currency', 'ر.س') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">الكمية <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="100" class="form-control product-qty" name="items[0][quantity]" value="1" required onchange="calculateTotal()">
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeProductRow(this)" disabled>
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">المجموع الكلي التقريبي:</span>
                                <span class="fw-bold fs-5 text-primary" id="order-total">0.00 {{ config('commerce.base_currency', 'ر.س') }}</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="ti ti-check me-1"></i> إتمام وإنشاء الطلب
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('js')
<script>
let rowIndex = 1;

function fillCustomerInfo(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('customer_name').value = opt.dataset.name || '';
        document.getElementById('customer_phone').value = opt.dataset.phone || '';
        document.getElementById('customer_email').value = opt.dataset.email || '';
    }
}

function addProductRow() {
    const container = document.getElementById('products-container');
    const firstRow = container.querySelector('.product-row');
    const clone = firstRow.cloneNode(true);

    const select = clone.querySelector('.product-select');
    select.name = `items[${rowIndex}][product_id]`;
    select.value = '';

    const qty = clone.querySelector('.product-qty');
    qty.name = `items[${rowIndex}][quantity]`;
    qty.value = '1';

    const delBtn = clone.querySelector('button');
    delBtn.disabled = false;

    container.appendChild(clone);
    rowIndex++;
    calculateTotal();
}

function removeProductRow(btn) {
    const rows = document.querySelectorAll('.product-row');
    if (rows.length > 1) {
        btn.closest('.product-row').remove();
        calculateTotal();
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.product-row').forEach(row => {
        const select = row.querySelector('.product-select');
        const qty = parseInt(row.querySelector('.product-qty').value) || 0;
        const opt = select.options[select.selectedIndex];
        if (opt && opt.dataset.price) {
            total += parseFloat(opt.dataset.price) * qty;
        }
    });
    document.getElementById('order-total').innerText = total.toFixed(2) + " {{ config('commerce.base_currency', 'ر.س') }}";
}
</script>
@endsection
