@php
    $selectedFinger = (int) ($selectedFinger ?? old('finger_index', 6));
    $labels = [
        0 => ['name' => 'Pinky', 'height' => 'h-pinky'],
        1 => ['name' => 'Ring', 'height' => 'h-ring'],
        2 => ['name' => 'Middle', 'height' => 'h-middle'],
        3 => ['name' => 'Index', 'height' => 'h-index'],
        4 => ['name' => 'Thumb', 'height' => 'h-thumb'],
        5 => ['name' => 'Thumb', 'height' => 'h-thumb'],
        6 => ['name' => 'Index', 'height' => 'h-index'],
        7 => ['name' => 'Middle', 'height' => 'h-middle'],
        8 => ['name' => 'Ring', 'height' => 'h-ring'],
        9 => ['name' => 'Pinky', 'height' => 'h-pinky'],
    ];
    $leftFingers = [0, 1, 2, 3, 4];
    $rightFingers = [5, 6, 7, 8, 9];
    $summaryLabel = $fingerOptions[$selectedFinger] ?? 'Finger '.$selectedFinger;
@endphp

<div class="finger-picker-wrap" data-finger-picker>
    <span class="finger-picker-label" id="finger-picker-heading">Select finger to enroll</span>

    <input type="hidden" name="finger_index" id="finger_index" value="{{ $selectedFinger }}" required>

    <div class="finger-hands" role="group" aria-labelledby="finger-picker-heading">
        <div class="finger-hand">
            <span class="finger-hand-title">Left hand</span>
            <div class="finger-hand-row left">
                @foreach ($leftFingers as $index)
                    @php $meta = $labels[$index]; @endphp
                    <button
                        type="button"
                        class="finger-segment {{ $meta['height'] }} @if($selectedFinger === $index) is-selected @endif"
                        data-finger-index="{{ $index }}"
                        aria-pressed="{{ $selectedFinger === $index ? 'true' : 'false' }}"
                        aria-label="Slot {{ $index }}, left {{ strtolower($meta['name']) }}"
                    >
                        <span class="finger-segment-inner">
                            <span class="finger-segment-num">{{ $index }}</span>
                            <span class="finger-segment-name">{{ $meta['name'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
            <div class="finger-palm" aria-hidden="true"></div>
        </div>

        <div class="finger-hand">
            <span class="finger-hand-title">Right hand</span>
            <div class="finger-hand-row right">
                @foreach ($rightFingers as $index)
                    @php $meta = $labels[$index]; @endphp
                    <button
                        type="button"
                        class="finger-segment {{ $meta['height'] }} @if($selectedFinger === $index) is-selected @endif"
                        data-finger-index="{{ $index }}"
                        aria-pressed="{{ $selectedFinger === $index ? 'true' : 'false' }}"
                        aria-label="Slot {{ $index }}, right {{ strtolower($meta['name']) }}"
                    >
                        <span class="finger-segment-inner">
                            <span class="finger-segment-num">{{ $index }}</span>
                            <span class="finger-segment-name">{{ $meta['name'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
            <div class="finger-palm" aria-hidden="true"></div>
        </div>
    </div>

    <p class="finger-picker-summary" id="finger-picker-summary" aria-live="polite">
        Selected: <span data-finger-summary>{{ $summaryLabel }}</span>
    </p>

    @error('finger_index')<p class="errors">{{ $message }}</p>@enderror
</div>

<script>
    (function () {
        var root = document.querySelector('[data-finger-picker]');
        if (!root) return;

        var input = root.querySelector('#finger_index');
        var summary = root.querySelector('[data-finger-summary]');
        var labels = @json($fingerOptions);

        root.querySelectorAll('[data-finger-index]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var index = btn.getAttribute('data-finger-index');
                input.value = index;

                root.querySelectorAll('[data-finger-index]').forEach(function (other) {
                    var selected = other === btn;
                    other.classList.toggle('is-selected', selected);
                    other.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                if (summary) {
                    summary.textContent = labels[index] || ('Finger ' + index);
                }
            });
        });
    })();
</script>
