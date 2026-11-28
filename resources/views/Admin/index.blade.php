@extends('Admin.layout.master')

@section('title', 'الرئيسية')

@section('css')
    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/css/pages/app-logistics-dashboard.css') }}" />


    <style>
        body {
            font-family: "Cairo", sans-serif !important;
        }

        .layout-navbar-fixed body:not(.modal-open) .layout-content-navbar .layout-navbar,
        .layout-menu-fixed body:not(.modal-open) .layout-content-navbar .layout-navbar,
        .layout-menu-fixed-offcanvas body:not(.modal-open) .layout-content-navbar .layout-navbar {
            z-index: 1043;
        }

        .layout-navbar-fixed body:not(.modal-open) .layout-content-navbar .layout-menu,
        .layout-menu-fixed body:not(.modal-open) .layout-content-navbar .layout-menu,
        .layout-menu-fixed-offcanvas body:not(.modal-open) .layout-content-navbar .layout-menu {
            z-index: 1043;
        }

        i {
            margin: 0px 5px 0px 5px
        }

        textarea {
            height: 100px;
        }
    </style>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">الرئيسية</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-xl-12 mb-4 col-lg-5 col-12">

                صباح الخير يا {{ auth()->guard('admin')->user()->name }}
                😍
            </div>


            <div class="row mb-4 g-4">

                <div class="col-lg-8">
                    <div class="row">

                        {{-- الطلبات هذا الشهر --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-warning">
                                                <i class="ti ti-basket ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">
                                            {{ $stats['monthly_orders'] ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="mb-1">الطلبات في الشهر</p>
                                </div>
                            </div>
                        </div>

                        {{-- عدد كل الطلبات --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-primary">
                                                <i class="ti ti-basket-filled ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_orders'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">إجمالي الطلبات</p>
                                </div>
                            </div>
                        </div>

                        {{-- الطلبات الملغية --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-danger">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-danger">
                                                <i class="ti ti-x ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['cancelled_orders'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">الطلبات الملغية</p>
                                </div>
                            </div>
                        </div>

                        {{-- عدد العملاء --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-secondary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-secondary">
                                                <i class="ti ti-users ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_users'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">عدد العملاء</p>
                                </div>
                            </div>
                        </div>

                        {{-- العملاء هذا الشهر --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-dark">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-dark">
                                                <i class="ti ti-user-plus ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">
                                            {{ $stats['monthly_users'] ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="mb-1">العملاء في الشهر</p>
                                </div>
                            </div>
                        </div>

                        {{-- عدد الموظفين --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-success">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-success">
                                                <i class="ti ti-user-cog ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_admins'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">عدد الموظفين</p>
                                </div>
                            </div>
                        </div>

                        {{-- الزيارات --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-info">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-info">
                                                <i class="ti ti-eye ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_visitors'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">عدد الزيارات</p>
                                </div>
                            </div>
                        </div>

                        {{-- الزيارات هذا الشهر --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-primary">
                                                <i class="ti ti-eye-check ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">
                                            {{ $stats['monthly_visitors'] ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="mb-1">الزيارات في الشهر</p>
                                </div>
                            </div>
                        </div>

                        {{-- عدد المنتجات --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-warning">
                                                <i class="ti ti-package ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_products'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">عدد المنتجات</p>
                                </div>
                            </div>
                        </div>

                        {{-- المنتجات هذا الشهر --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-success">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-success">
                                                <i class="ti ti-package-import ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">
                                            {{ $stats['monthly_products'] ?? 0 }}
                                        </h4>
                                    </div>
                                    <p class="mb-1">المنتجات المضافة هذا الشهر</p>
                                </div>
                            </div>
                        </div>

                        {{-- الأقسام --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-secondary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-secondary">
                                                <i class="ti ti-category ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['total_categories'] ?? 0 }}</h4>
                                    </div>
                                    <p class="mb-1">عدد الأقسام</p>
                                </div>
                            </div>
                        </div>

                        {{-- الإيرادات --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-primary">
                                                <i class="ti ti-currency-pound ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['base_currency'] ?? 'EGP' }} {{ number_format($stats['total_revenue'] ?? 0, 2) }}</h4>
                                    </div>
                                    <p class="mb-1">إجمالي الإيرادات</p>
                                </div>
                            </div>
                        </div>

                        {{-- صافي الإيرادات --}}
                        <div class="col-sm-6 col-lg-4 mb-4">
                            <div class="card card-border-shadow-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2 pb-1">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded bg-label-warning">
                                                <i class="ti ti-chart-donut ti-md"></i>
                                            </span>
                                        </div>
                                        <h4 class="ms-1 mb-0">{{ $stats['base_currency'] ?? 'EGP' }} {{ number_format($stats['net_revenue'] ?? 0, 2) }}</h4>
                                    </div>
                                    <p class="mb-1">صافي الإيرادات</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-xxl-4 mb-4 order-5 order-xxl-0">
                    <div class="card mb-4">
                        <div class="card-header pb-0">
                            <h5 class="card-title mb-0">{{ $stats['base_currency'] ?? 'EGP' }} {{ number_format($stats['this_month_revenue'] ?? 0, 2) }}</h5>
                            <small class="text-muted">إيرادات المنصة هذا الشهر</small>
                        </div>
                        <div class="card-body">
                            <div id="expensesChart"></div>
                            <div class="mt-md-2 text-center mt-lg-3 mt-3">
                                @php $diff = $stats['revenue_diff'] ?? 0; @endphp
                                <small class="text-muted mt-3">
                                    {{ $diff >= 0 ? 'زيادة' : 'انخفاض' }} إيرادات المنصة بـ {{ number_format(abs($diff), 2) }} {{ $stats['base_currency'] ?? 'EGP' }} عن الشهر الماضي
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title mb-0">
                                <h5 class="m-0">طرق الدفع</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            @if (!empty($paymentMethodsBreakdown))
                                <div class="vehicles-overview-progress progress rounded-2 my-4" style="height: 46px">
                                    @foreach ($paymentMethodsBreakdown as $method)
                                        @if ($method['percent'] > 0)
                                            <div class="progress-bar fw-medium text-start {{ $method['color'] }} px-2"
                                                role="progressbar" style="width: {{ $method['percent'] }}%"
                                                aria-valuenow="{{ $method['percent'] }}" aria-valuemin="0" aria-valuemax="100"
                                                title="{{ $method['label'] }} ({{ $method['percent'] }}%)">
                                                @if ($method['percent'] >= 10)
                                                    {{ $method['percent'] }}%
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="table-responsive">
                                    <table class="table card-table">
                                        <tbody class="table-border-bottom-0">
                                            @foreach ($paymentMethodsBreakdown as $method)
                                                <tr>
                                                    <td class="w-50 ps-0">
                                                        <div class="d-flex justify-content-start align-items-center">
                                                            <div class="me-2">
                                                                <i class="ti ti-credit-card mt-n1"></i>
                                                            </div>
                                                            <h6 class="mb-0 fw-normal">{{ $method['label'] }}</h6>
                                                        </div>
                                                    </td>
                                                    <td class="text-end pe-0">
                                                        <span class="fw-medium">{{ $method['percent'] }}% ({{ $method['count'] }})</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center mb-0">لا توجد بيانات لطلبات مدفوعة بعد</p>
                            @endif
                        </div>
                    </div>

                </div>



            </div>



            <div class="row mb-4">
                <div class="col-12 col-xl-6 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h4 id="visitors_data">احصائيات الزيارات حسب الدولة</h4>

                            <div class="col-md-4 mb-4">
                                <select name="visitors_year" id="visitors_year" class="form-control select2">
                                    <option value="">اختر السنة</option>
                                    @foreach ($chartYears ?? range(now()->year, now()->year - 4) as $yr)
                                        <option value="{{ $yr }}" {{ $yr == now()->year ? 'selected' : '' }}>{{ $yr }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>

                        <div class="card-body">
                            <div id="visitorsAreaChart"></div>
                        </div>
                    </div>
                </div>


                <div class="col-12 col-xl-6 col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title mb-0">
                                <h5 class="m-0 me-2">اكثر العملاء الطلبات و تقييم</h5>
                            </div>

                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless border-top">
                                <thead class="border-bottom">
                                    <tr>
                                        <th>العميل</th>
                                        <th class="text-end">عدد الطلبات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($topCustomers ?? [] as $row)
                                        <tr>
                                            <td class="pt-2">
                                                <div class="d-flex justify-content-start align-items-center">
                                                    <div class="avatar me-3 avatar-sm">
                                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                                            {{ mb_substr($row->user?->name ?? 'ع', 0, 1) }}
                                                        </span>
                                                    </div>
                                                    <span class="mb-0">
                                                        {{ $row->user?->name ?? 'عميل #' . $row->user_id }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-end pt-2">
                                                <div class="user-progress">
                                                    <p class="mb-0 fw-medium">{{ $row->orders_count }}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-3">لا توجد طلبات مسجلة بعد</td>
                                        </tr>
                                    @endforelse
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!--/ Website Analytics -->

            <!-- Line Area Chart -->
            <div class="row mb-4">

                <div class="col-6 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h4 id="sales_data">احصائيات الطلبات {{ now()->year }}</h4>

                            <div class="col-md-4 mb-4">
                                <select name="sales_year" id="year" class="form-control select2 year">
                                    <option value="">اختر السنة</option>
                                    @foreach ($chartYears ?? range(now()->year, now()->year - 4) as $yr)
                                        <option value="{{ $yr }}" {{ $yr == now()->year ? 'selected' : '' }}>{{ $yr }}</option>
                                    @endforeach
                                </select>

                            </div>

                        </div>
                        <div class="card-body">
                            <div id="lineAreaChart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h4 id="all_data">الاحصائيات لسنة {{ now()->year }}</h4>

                            <div class="col-md-4 mb-4">
                                <select name="data_year" id="data_year" class="form-control select2 year">
                                    <option value="">اختر السنة</option>
                                    @foreach ($chartYears ?? range(now()->year, now()->year - 4) as $yr)
                                        <option value="{{ $yr }}" {{ $yr == now()->year ? 'selected' : '' }}>{{ $yr }}</option>
                                    @endforeach
                                </select>

                            </div>
                        </div>
                        <div class="card-body">
                            <div id="lineAreaChart1"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Line Area Chart -->


        </div>
    </div>
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        function loadVisitorsChart(year = null) {
            $.ajax({
                url: "{{ route('admin.visitors.chart') }}",
                data: {
                    year: year
                },
                success: function(response) {

                    console.log("Visitors Chart Data:", response); // ⬅️ Console Log Added

                    var chartEl = document.querySelector("#visitorsAreaChart");

                    var chartOptions = {
                        series: [{
                            name: "عدد الزيارات",
                            data: response.count
                        }],
                        chart: {
                            height: 350,
                            type: "area",
                            toolbar: false
                        },
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            curve: "smooth",
                            width: 2
                        },
                        xaxis: {
                            categories: response.countries
                        },
                        colors: ['#7367F0'],
                        fill: {
                            type: "gradient",
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.5,
                                opacityTo: 0.3,
                                stops: [0, 90, 100]
                            }
                        }
                    };

                    var chart = new ApexCharts(chartEl, chartOptions);
                    chart.render();
                }
            });
        }

        // Initial Load
        loadVisitorsChart();

        // On Change
        $('#visitors_year').on('change', function() {
            let year = $(this).val();
            $("#visitorsAreaChart").html('');
            loadVisitorsChart(year);
        });
        document.addEventListener('DOMContentLoaded', function() {

            function loadChart(year) {
                fetch(`/orders/stats/${year}`)
                    .then(res => res.json())
                    .then(data => {
                        let months = data.map(i => i.month);
                        let totals = data.map(i => i.total);

                        let options = {
                            chart: {
                                height: 350,
                                type: 'area'
                            },
                            series: [{
                                name: 'عدد الطلبات',
                                data: totals
                            }],
                            xaxis: {
                                categories: months
                            }
                        };

                        let chartDiv = document.querySelector('#lineAreaChart');
                        chartDiv.innerHTML = ""; // مسح القديم
                        let chart = new ApexCharts(chartDiv, options);
                        chart.render();
                    });
            }

            // عند اختيار سنة
            document.querySelector('#year').addEventListener('change', function() {
                document.getElementById("sales_data").innerHTML = `احصائيات الطلبات ${this.value}`;
                loadChart(this.value);
            });

            // تحميل السنة الحالية تلقائياً
            loadChart({{ now()->year }});
        });
    </script>

@endsection
