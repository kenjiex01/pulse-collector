@php
    $error = $error ?? null;
@endphp
@if ($error)
    <span class="device-collect-error-msg" title="{{ $error }}">{{ $error }}</span>
@else
    <span class="device-collect-error-none">—</span>
@endif
