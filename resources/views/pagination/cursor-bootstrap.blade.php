@if ($paginator->hasPages())
    <nav aria-label="Phân trang">
        <ul class="pagination mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">Trước</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Trước</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Tiếp</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">Tiếp</span></li>
            @endif
        </ul>
    </nav>
@endif
