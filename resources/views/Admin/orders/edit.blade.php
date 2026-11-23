@extends('Admin.layout.master')

@section('title', 'تعديل الطلب: #' . $order->order_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">تعديل الطلب <span class="text-primary">#{{ $order->order_number }}</span></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">الطلبات</a></li>
                    <li class="breadcrumb-item active">تعديل #{{ $order->order_number }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-outline-info">
                <i class="ti ti-eye me-1"></i> عرض التفاصيل
            </a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-right me-1"></i> رجوع للقائمة
            </a>
        </div>
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
                    <h5 class="card-title mb-0">بيانات الطلب</h5>
                </div>
                <div class="card-body pt-3">
                    <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="customer_name">اسم العميل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $order->customer_name ?: $order->user?->name) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="customer_phone">رقم الهاتف</label>
                                <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $order->customer_phone ?: $order->user?->phone) }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="customer_email">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" value="{{ old('customer_email', $order->customer_email ?: $order->user?->email) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="status">حالة الطلب <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    @foreach (\App\Models\Order::selectableStatuses() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $order->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="notes">ملاحظات الطلب</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $order->notes) }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> حفظ التعديلات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
