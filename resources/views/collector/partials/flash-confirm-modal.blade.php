@php
    $flashSuccess = session('success');
    $flashError = session('error');
    $flashWarning = session('warning');
    $flashTitle = $flashSuccess ? 'Success' : ($flashError ? 'Error' : ($flashWarning ? 'Notice' : null));
    $flashMessage = $flashSuccess ?: ($flashError ?: $flashWarning);
    $flashKind = $flashSuccess ? 'success' : ($flashError ? 'error' : ($flashWarning ? 'warning' : null));
@endphp

@if ($flashMessage && $flashKind)
    <div
        id="flash-confirm-modal"
        class="flash-confirm-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="flash-confirm-title"
        data-flash-confirm
    >
        <div class="flash-confirm-card flash-confirm-{{ $flashKind }}">
            <h2 id="flash-confirm-title">{{ $flashTitle }}</h2>
            <p>{{ $flashMessage }}</p>
            <div class="flash-confirm-actions">
                <button type="button" class="btn" data-flash-confirm-close autofocus>OK</button>
            </div>
        </div>
    </div>
@endif
