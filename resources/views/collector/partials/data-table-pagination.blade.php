@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="data-table-footer">
        <div class="data-table-footer-start">
            <p class="data-table-info muted">
                @if ($paginator->total() === 0)
                    Showing 0 entries
                @else
                    Showing {{ number_format($paginator->firstItem()) }} to {{ number_format($paginator->lastItem()) }}
                    of {{ number_format($paginator->total()) }} entries
                @endif
            </p>

            @isset($perPageOptions)
                <form method="get" action="{{ $perPageFormAction ?? url()->current() }}" class="data-table-per-page">
                    @foreach (request()->except(['per_page', 'page']) as $name => $value)
                        @if (is_array($value))
                            @foreach ($value as $item)
                                <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="footer_per_page" class="data-table-per-page-label">
                        Show
                        <select id="footer_per_page" name="per_page" onchange="this.form.submit()">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected(($perPageSelection ?? null) === $option)>{{ $option }}</option>
                            @endforeach
                            <option value="all" @selected(($perPageSelection ?? null) === 'all')>All</option>
                        </select>
                        entries
                    </label>
                </form>
            @endisset
        </div>

        @if ($paginator->hasPages())
            <nav class="data-table-pagination" aria-label="Pagination">
                @if ($paginator->onFirstPage())
                    <span class="page-link disabled" aria-disabled="true">First</span>
                    <span class="page-link disabled" aria-disabled="true">Prev</span>
                @else
                    <a class="page-link" href="{{ $paginator->url(1) }}">First</a>
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}">Prev</a>
                @endif

                @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}">Next</a>
                    <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">Last</a>
                @else
                    <span class="page-link disabled" aria-disabled="true">Next</span>
                    <span class="page-link disabled" aria-disabled="true">Last</span>
                @endif
            </nav>
        @endif
    </div>
@endif
