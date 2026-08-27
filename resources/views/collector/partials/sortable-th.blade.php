@php
    $isActive = ($currentSort ?? '') === $sort;
    $nextDirection = $isActive
        ? (($currentDirection ?? 'desc') === 'asc' ? 'desc' : 'asc')
        : (in_array($sort, ['punched_at', 'created_at', 's3'], true) ? 'desc' : 'asc');
    $params = array_merge(
        request()->except(['sort', 'direction', 'page']),
        ['sort' => $sort, 'direction' => $nextDirection],
    );
@endphp
<a
    href="{{ route('logs.index', $params) }}"
    class="sortable-th @if($isActive) is-active @endif"
    @if($isActive) aria-sort="{{ ($currentDirection ?? 'desc') === 'asc' ? 'ascending' : 'descending' }}" @endif
>
    <span>{{ $label }}</span>
    @if ($isActive)
        <span class="sort-indicator" aria-hidden="true">{{ ($currentDirection ?? 'desc') === 'asc' ? '↑' : '↓' }}</span>
    @else
        <span class="sort-indicator sort-indicator-idle" aria-hidden="true">↕</span>
    @endif
</a>
