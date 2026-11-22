@extends('Admin.layout.master')
@section('title','إحصائيات المقالات')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><h4>إحصائيات المقالات</h4><a class="btn btn-outline-primary" href="{{ route('admin.articles.index') }}">العودة للمقالات</a></div>
    <div class="row g-3">
        @foreach(['total'=>'إجمالي المقالات','published'=>'منشور','draft'=>'مسودة','featured'=>'مميز','this_month'=>'هذا الشهر'] as $key=>$label)
            <div class="col-md-4 col-xl-2"><div class="card"><div class="card-body"><small class="text-muted">{{ $label }}</small><h3 class="mb-0">{{ $stats[$key] ?? 0 }}</h3></div></div></div>
        @endforeach
    </div>
</div>
@endsection
