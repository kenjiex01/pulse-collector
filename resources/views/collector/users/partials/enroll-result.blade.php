@if ($result = session('enroll_result'))
    <div
        class="enroll-result enroll-result-{{ $result['status'] ?? 'success' }}"
        role="status"
        aria-live="polite"
        tabindex="-1"
        id="enroll-result-banner"
    >
        <p class="enroll-result-title">{{ $result['title'] ?? 'Enrollment result' }}</p>
        <p class="enroll-result-message">{{ $result['message'] ?? '' }}</p>
        @if (! empty($result['detail']))
            <p class="enroll-result-detail muted">{{ $result['detail'] }}</p>
        @endif
    </div>
@endif
