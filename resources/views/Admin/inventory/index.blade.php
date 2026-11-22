@extends('Admin.layout.master')
@section('title','المخزون')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
<h4 class="mb-4">إدارة المخزون</h4>
<div class="row g-3 mb-4">
@foreach($stats as $key=>$value)<div class="col-md-2"><div class="card"><div class="card-body"><small>{{ $key }}</small><h4>{{ number_format($value) }}</h4></div></div></div>@endforeach
</div>
<div class="card mb-4"><div class="card-body"><form class="row g-2"><div class="col-md-6"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="بحث بالاسم أو SKU"></div><div class="col-md-3 form-check mt-3"><input type="checkbox" name="low_stock" value="1" @checked(request('low_stock'))> مخزون منخفض</div><div class="col-md-3"><button class="btn btn-primary">بحث</button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>المنتج</th><th>SKU</th><th>المتاح</th><th>محجوز</th><th>مباع</th><th>تعديل</th></tr></thead><tbody>
@foreach($products as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->sku }}</td><td>{{ max(0,(int)$p->stock-(int)$p->reserved_stock) }}</td><td>{{ $p->reserved_stock }}</td><td>{{ $p->sold_stock }}</td><td><form method="POST" action="{{ route('admin.inventory.adjust',$p) }}" class="d-flex gap-1">@csrf<input class="form-control" style="max-width:100px" type="number" min="0" name="stock" value="{{ $p->stock }}"><input class="form-control" name="reason" placeholder="سبب التعديل" required><button class="btn btn-sm btn-primary">حفظ</button></form></td></tr>@endforeach
</tbody></table></div><div class="card-footer">{{ $products->links() }}</div></div>
<div class="card mt-4"><div class="card-header">آخر حركات المخزون</div><div class="table-responsive"><table class="table"><tr><th>المرجع</th><th>المنتج</th><th>النوع</th><th>الكمية</th><th>قبل</th><th>بعد</th><th>التاريخ</th></tr>@foreach($movements as $m)<tr><td>{{ $m->reference }}</td><td>{{ $m->product?->name }}</td><td>{{ $m->type }}</td><td>{{ $m->quantity }}</td><td>{{ $m->stock_before }}</td><td>{{ $m->stock_after }}</td><td>{{ $m->created_at }}</td></tr>@endforeach</table></div></div>
</div>
@endsection
