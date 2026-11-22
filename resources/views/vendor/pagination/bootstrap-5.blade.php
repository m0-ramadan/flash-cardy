@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="w-100">
        {{-- عرض مخصص لشاشات الموبايل لتفادي انكسار التجاوب مع كثرة الأرقام (< 576px) --}}
        <div class="d-flex d-sm-none justify-content-between align-items-center w-100 gap-2 my-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">
                    &rsaquo; السابق
                </span>
            @else
                <a class="btn btn-sm btn-outline-primary" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    &rsaquo; السابق
                </a>
            @endif

            <span class="badge bg-label-secondary px-3 py-2 fw-semibold">
                @if (method_exists($paginator, 'lastPage'))
                    {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                @else
                    صفحة {{ $paginator->currentPage() }}
                @endif
            </span>

            @if ($paginator->hasMorePages())
                <a class="btn btn-sm btn-outline-primary" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    التالي &lsaquo;
                </a>
            @else
                <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">
                    التالي &lsaquo;
                </span>
            @endif
        </div>

        {{-- عرض كامل مع الأرقام للشاشات المتوسطة والكبيرة (>= 576px) --}}
        <div class="d-none d-sm-block">
            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
                {{-- Previous Page Link (RTL: Chevron Right) --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="السابق">
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="السابق">&rsaquo;</a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link (RTL: Chevron Left) --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="التالي">&lsaquo;</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="التالي">
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
