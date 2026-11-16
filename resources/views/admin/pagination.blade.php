@if ($paginator->hasPages())
    <nav class="adm-pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="adm-btn adm-btn--sm is-disabled">&laquo;</span>
        @else
            <a class="adm-btn adm-btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
        @endif
        <span class="adm-pager__info">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="adm-btn adm-btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
        @else
            <span class="adm-btn adm-btn--sm is-disabled">&raquo;</span>
        @endif
    </nav>
@endif
