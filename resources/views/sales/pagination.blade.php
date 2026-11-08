@if ($paginator->hasPages())
    <nav class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2" aria-label="Paginación de ventas">
        <span class="small text-secondary text-center text-lg-start">
            Mostrando {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }} de {{ $paginator->total() }} ventas
        </span>
        <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center justify-content-lg-end">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">Anterior</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
