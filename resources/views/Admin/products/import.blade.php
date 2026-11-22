@extends('Admin.layout.master')
@section('title','استيراد المنتجات')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y"><h4>استيراد CSV للمنتجات</h4><div class="card"><div class="card-body"><p>الأعمدة الإلزامية: <code>sku,name,price</code>. اختيارياً: description, stock, category_id, status_id, external_id, image_path.</p><p class="text-warning">image_path يقبل مسار تخزين محلي فقط ولا يقوم بتنزيل روابط خارجية.</p><form method="POST" enctype="multipart/form-data" action="{{route('admin.product-data.import')}}">@csrf<div class="mb-3"><input type="file" name="file" accept=".csv,text/csv" class="form-control" required></div><label><input type="checkbox" name="update_existing" value="1"> تحديث المنتجات الموجودة إذا تطابق SKU</label><br><button class="btn btn-primary mt-3">استيراد</button></form>@if(session('import_errors'))<hr><ul>@foreach(session('import_errors') as $e)<li>{{$e}}</li>@endforeach</ul>@endif</div></div></div>
@endsection
