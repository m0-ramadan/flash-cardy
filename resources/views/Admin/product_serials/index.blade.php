@extends('Admin.layout.master')
@section('title','أكواد المنتج')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">أكواد المنتج التسلسلية</h4>
            <div class="text-muted">{{ $product->name }} @if($product->sku) — {{ $product->sku }} @endif</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products.edit',$product) }}" class="btn btn-outline-primary">تعديل المنتج</a>
            <a href="{{ route('admin.products.show',$product->id) }}" class="btn btn-outline-secondary">عرض المنتج</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        @foreach(['الإجمالي'=>'total','متاح'=>'available','مستخدم/مرسل'=>'assigned','منتهي'=>'expired','معطل'=>'disabled'] as $label=>$key)
            <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><h4 class="mb-0">{{ number_format($stats[$key] ?? 0) }}</h4></div></div></div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>إضافة أكواد كسطور</strong></div>
                <div class="card-body">
                    <p class="text-muted small">كل سطر: <code>CODE|YYYY-MM-DD|PIN|ملاحظة</code>. التاريخ وPIN والملاحظة اختياريان. مثال: <code>ABC-123|2027-12-31|7788|دفعة سبتمبر</code></p>
                    <form method="POST" action="{{ route('admin.products.serials.bulk-text',$product) }}">@csrf
                        <textarea name="serials_text" class="form-control" rows="10" dir="ltr" placeholder="CODE-001|2027-12-31&#10;CODE-002|2027-12-31|1234">{{ old('serials_text') }}</textarea>
                        <button class="btn btn-primary mt-3">إضافة الأكواد</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>إضافة كود واحد</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.products.serials.store',$product) }}">@csrf
                        <div class="mb-3"><label class="form-label">الكود</label><input name="serials[0][code]" required class="form-control" dir="ltr"></div>
                        <div class="mb-3"><label class="form-label">PIN إن وجد</label><input name="serials[0][pin]" class="form-control" dir="ltr"></div>
                        <div class="mb-3"><label class="form-label">ينتهي في</label><input type="datetime-local" name="serials[0][expires_at]" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">ملاحظة</label><textarea name="serials[0][notes]" class="form-control"></textarea></div>
                        <button class="btn btn-success">إضافة</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <form class="row g-2">
            <div class="col-md-4"><select name="status" class="form-select"><option value="">كل الحالات</option>@foreach(['available'=>'متاح','delivered'=>'مرسل','assigned'=>'مخصص','expired'=>'منتهي','disabled'=>'معطل'] as $v=>$l)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-4"><select name="expires" class="form-select"><option value="">كل الصلاحيات</option><option value="soon" @selected(request('expires')==='soon')>ينتهي خلال 30 يوم</option><option value="expired" @selected(request('expires')==='expired')>منتهي</option></select></div>
            <div class="col-md-4"><button class="btn btn-primary">تصفية</button> <a href="{{ route('admin.products.serials.index',$product) }}" class="btn btn-outline-secondary">إعادة</a></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>الكود</th><th>الحالة</th><th>الصلاحية</th><th>الطلب</th><th>المصدر</th><th>ملاحظة</th><th>إجراء</th></tr></thead>
            <tbody>
            @forelse($serials as $serial)
                <tr>
                    <td>{{ $serial->id }}</td>
                    <td dir="ltr"><code>{{ $serial->masked_code }}</code></td>
                    <td><span class="badge bg-{{ $serial->status==='available'?'success':($serial->status==='delivered'?'primary':($serial->status==='expired'?'danger':'secondary')) }}">{{ $serial->status }}</span></td>
                    <td>{{ $serial->expires_at?->format('Y-m-d H:i') ?? 'بدون انتهاء' }}</td>
                    <td>@if($serial->order)<a href="{{ route('admin.orders.show',$serial->order_id) }}">{{ $serial->order->order_number }}</a>@else — @endif</td>
                    <td>{{ $serial->source }}</td>
                    <td>{{ $serial->notes }}</td>
                    <td>
                        @if(!in_array($serial->status,['assigned','delivered']))
                        <form class="d-inline" method="POST" action="{{ route('admin.products.serials.destroy',[$product,$serial]) }}" onsubmit="return confirm('حذف الكود غير المستخدم؟')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">حذف</button></form>
                        @else <span class="text-muted small">محفوظ كسجل</span> @endif
                    </td>
                </tr>
            @empty <tr><td colspan="8" class="text-center text-muted py-5">لا توجد أكواد حتى الآن.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $serials->links() }}</div>
    </div>
</div>
@endsection
