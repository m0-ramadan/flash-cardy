@extends('Admin.layout.master')

@section('title', 'إحصائيات الزيارات')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1">إحصائيات الزيارات</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">إحصائيات الزيارات</li>
                </ol>
            </nav>
        </div>
        <form action="{{ route('admin.visitors.index') }}" method="GET" class="d-flex align-items-center gap-2">
            <label for="range" class="form-label mb-0 text-muted small">الفترة:</label>
            <select name="range" id="range" class="form-select form-select-sm" style="width: 170px;" onchange="this.form.submit()">
                <option value="7" @selected($range === '7')>آخر 7 أيام</option>
                <option value="30" @selected($range === '30')>آخر 30 يوم</option>
                <option value="90" @selected($range === '90')>آخر 90 يوم</option>
                <option value="365" @selected($range === '365')>آخر سنة</option>
                <option value="all" @selected($range === 'all')>كل الوقت</option>
            </select>
        </form>
    </div>

    <!-- بطاقات الإحصائيات الرئيسية -->
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
                            <span class="text-muted d-block mb-1">زيارات الفترة المحددة</span>
                            <h4 class="mb-0 text-warning">{{ number_format($stats['period']) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-trending-up fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الرسم البياني + توزيع الأجهزة -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-chart-area-line me-1"></i> منحنى الزيارات</h6>
                </div>
                <div class="card-body">
                    <div id="visitsTrendChart" style="min-height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-devices me-1"></i> توزيع الأجهزة</h6>
                </div>
                <div class="card-body">
                    @foreach ($deviceBreakdown as $item)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="ti {{ $item['icon'] }} me-1"></i> {{ $item['label'] }}</span>
                                <span class="fw-semibold">{{ $item['percent'] }}% <small class="text-muted">({{ number_format($item['count']) }})</small></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar {{ $item['color'] }}" role="progressbar" style="width: {{ $item['percent'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- أكثر الدول وأكثر الصفحات -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-world me-1"></i> أكثر الدول زيارة</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topCountries as $item)
                            @php $pct = $stats['period'] > 0 ? round(($item->total / $stats['period']) * 100, 1) : 0; @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $item->country }}</span>
                                <div>
                                    <small class="text-muted me-2">{{ $pct }}%</small>
                                    <span class="badge bg-label-primary">{{ number_format($item->total) }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center px-0">لا توجد بيانات في هذه الفترة</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-link me-1"></i> أكثر الصفحات زيارة</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topPages as $item)
                            @php $pct = $stats['period'] > 0 ? round(($item->total / $stats['period']) * 100, 1) : 0; @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 gap-2">
                                <span class="text-truncate" dir="ltr" style="max-width: 70%;" title="{{ $item->path }}">{{ $item->path }}</span>
                                <div class="text-nowrap">
                                    <small class="text-muted me-2">{{ $pct }}%</small>
                                    <span class="badge bg-label-info">{{ number_format($item->total) }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center px-0">لا توجد بيانات في هذه الفترة</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- المتصفحات وأنظمة التشغيل -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="mb-0 fw-bold"><i class="ti ti-browser me-1"></i> أكثر المتصفحات استخداماً</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topBrowsers as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $item->browser }}</span>
                                <span class="badge bg-label-secondary">{{ number_format($item->total) }}</span>
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
                    <h6 class="mb-0 fw-bold"><i class="ti ti-cpu me-1"></i> أكثر أنظمة التشغيل</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($topPlatforms as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $item->platform }}</span>
                                <span class="badge bg-label-secondary">{{ number_format($item->total) }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center px-0">لا توجد بيانات</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels = @json($chartLabels);
    const counts = @json($chartCounts);

    const chartEl = document.querySelector('#visitsTrendChart');
    if (chartEl && labels.length) {
        const chart = new ApexCharts(chartEl, {
            series: [{ name: 'الزيارات', data: counts }],
            chart: { height: 300, type: 'area', toolbar: { show: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: labels },
            colors: ['#7367F0'],
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95, 100] }
            }
        });
        chart.render();
    } else if (chartEl) {
        chartEl.innerHTML = '<div class="text-center text-muted py-5">لا توجد بيانات كافية لعرض الرسم البياني</div>';
    }
});
</script>
@endsection
