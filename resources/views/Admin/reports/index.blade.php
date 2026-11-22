@extends('Admin.layout.master')
@section('title','التقارير')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y"><h4>تقارير الأداء المالي والتشغيلي</h4><form class="row g-2 mb-4"><div class="col-md-3"><input type="date" name="from" class="form-control" value="{{$from->toDateString()}}"></div><div class="col-md-3"><input type="date" name="to" class="form-control" value="{{$to->toDateString()}}"></div><div class="col"><button class="btn btn-primary">تطبيق</button></div></form>
<div class="row g-3 mb-4">@foreach($stats as $k=>$v)<div class="col-md-3"><div class="card"><div class="card-body"><small>{{$k}}</small><h4>{{is_numeric($v)?number_format($v,2):$v}}</h4></div></div></div>@endforeach</div>
<div class="row"><div class="col-lg-6"><div class="card"><div class="card-header">أفضل المنتجات (طلبات مدفوعة فقط)</div><table class="table"><tr><th>المنتج</th><th>الكمية</th><th>الإيراد</th></tr>@foreach($topProducts as $p)<tr><td>{{$p->name}}</td><td>{{$p->quantity}}</td><td>{{number_format($p->revenue,2)}}</td></tr>@endforeach</table></div></div><div class="col-lg-6"><div class="card"><div class="card-header">البوابات</div><table class="table"><tr><th>البوابة</th><th>الحالة</th><th>العدد</th></tr>@foreach($gateways as $g)<tr><td>{{$g->gateway}}</td><td>{{$g->status}}</td><td>{{$g->total}}</td></tr>@endforeach</table></div></div></div>
</div>
@endsection
