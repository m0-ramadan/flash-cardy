@extends('Admin.layout.master')

@section('title', 'إدارة الزيارات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1">إحصائيات وسجل الزيارات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الزيارات</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="text-muted d-block mb-1">إجمالي الزيارات</span>
                            <h4 class="mb-0">{{ number_format($stats['total']) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-eye fs-4"></i>
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
                        <div>
                            <span class="text-muted d-block mb-1">زيارات اليوم</span>
                            <h4 class="mb-0 text-success">{{ number_format($stats['today']) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-calendar-event fs-4"></i>
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
                        <div>
                            <span class="text-muted d-block mb-1">زيارات هذا الشهر</span>
                            <h4 class="mb-0 text-info">{{ number_format($stats['month']) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ti ti-chart-bar fs-4"></i>
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
                        <div>
                            <span class="text-muted d-block mb-1">زوار فريدون (هذا الشهر)</span>
                            <h4 class="mb-0 text-warning">{{ number_format($stats['unique_month_ips']) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-users fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ملخص آخر 30 يوم: أكثر الدول والصفحات -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-world me-1"></i> أكثر الدول زيارة (آخر 30 يوم)</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topCountries as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $item->country }}</span>
                                <span class="badge bg-label-primary">{{ number_format($item->total) }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center px-0">لا توجد بيانات</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-link me-1"></i> أكثر الصفحات زيارة (آخر 30 يوم)</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topPages as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 gap-2">
                                <span class="text-truncate" dir="ltr" style="max-width: 75%;" title="{{ $item->path }}">{{ $item->path }}</span>
                                <span class="badge bg-label-info">{{ number_format($item->total) }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center px-0">لا توجد بيانات</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- الفلترة والبحث -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.visitors.index') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="search">بحث (IP / رابط / مدينة / متصفح)</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="ابحث...">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="country">الدولة</label>
                    <select class="form-select" id="country" name="country">
                        <option value="">الكل</option>
                        @foreach ($countries as $c)
                            <option value="{{ $c }}" @selected(request('country') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="device_type">الجهاز</label>
                    <select class="form-select" id="device_type" name="device_type">
                        <option value="">الكل</option>
                        <option value="desktop" @selected(request('device_type') === 'desktop')>كمبيوتر</option>
                        <option value="mobile" @selected(request('device_type') === 'mobile')>موبايل</option>
                        <option value="tablet" @selected(request('device_type') === 'tablet')>تابلت</option>
                        <option value="bot" @selected(request('device_type') === 'bot')>بوت (Bot)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="from">من تاريخ</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ request('from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="to">إلى تاريخ</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ request('to') }}">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary w-100" title="تصفية">
                        <i class="ti ti-filter"></i>
                    </button>
                    @if(request()->anyFilled(['search', 'country', 'device_type', 'from', 'to']))
                        <a href="{{ route('admin.visitors.index') }}" class="btn btn-outline-secondary" title="إلغاء الفلتر">
                            <i class="ti ti-rotate"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- جدول الزيارات -->
    <div class="card shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>IP</th>
                        <th>الدولة / المدينة</th>
                        <th>الصفحة / المسار</th>
                        <th>الجهاز والمتصفح</th>
                        <th>النوع</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($visitors as $visitor)
                        <tr>
                            <td class="text-muted">{{ $visitor->id }}</td>
                            <td dir="ltr" class="text-start"><code>{{ $visitor->ip ?? '—' }}</code></td>
                            <td>
                                <span class="fw-semibold">{{ $visitor->country ?: 'غير معروف' }}</span>
                                @if ($visitor->city)
                                    <small class="text-muted d-block">{{ $visitor->city }}</small>
                                @endif
                            </td>
                            <td dir="ltr" class="text-start" style="max-width: 280px;">
                                <div class="text-truncate" title="{{ $visitor->full_url ?: $visitor->path }}">
                                    <span class="badge bg-label-secondary me-1">{{ $visitor->method ?: 'GET' }}</span>
                                    {{ $visitor->path ?: '/' }}
                                </div>
                            </td>
                            <td>
                                <div>{{ $visitor->browser ?: '—' }} @if($visitor->browser_version)<small class="text-muted">{{ $visitor->browser_version }}</small>@endif</div>
                                <small class="text-muted">{{ $visitor->platform ?: '' }} {{ $visitor->device ? '• ' . $visitor->device : '' }}</small>
                            </td>
                            <td>
                                @if ($visitor->is_bot)
                                    <span class="badge bg-label-warning"><i class="ti ti-robot me-1"></i> بوت</span>
                                @elseif ($visitor->is_mobile)
                                    <span class="badge bg-label-info"><i class="ti ti-device-mobile me-1"></i> موبايل</span>
                                @elseif ($visitor->is_tablet)
                                    <span class="badge bg-label-secondary"><i class="ti ti-device-tablet me-1"></i> تابلت</span>
                                @else
                                    <span class="badge bg-label-success"><i class="ti ti-device-desktop me-1"></i> كمبيوتر</span>
                                @endif
                            </td>
                            <td dir="ltr" class="text-start">
                                <small>{{ optional($visitor->created_at)->format('Y-m-d H:i') }}</small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti ti-eye-off fs-1 d-block mb-2"></i>
                                لا توجد زيارات مطابقة للبحث
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($visitors->hasPages())
            <div class="card-footer border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <small class="text-muted">عرض {{ $visitors->firstItem() }} إلى {{ $visitors->lastItem() }} من أصل {{ number_format($visitors->total()) }} زيارة</small>
                <div>{{ $visitors->links() }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
