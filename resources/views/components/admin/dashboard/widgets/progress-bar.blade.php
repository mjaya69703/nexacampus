@props([
    'current' => 0,
    'total' => 0,
    'value' => null,
    'max' => 100,
    'label' => '',
    'batchName' => '',
    'color' => 'auto',
    'height' => 8,
    'showPercentage' => true,
    'suffix' => null,
])

@php
    // Mode A: value/max langsung. Mode B (legacy): current/total.
    $percentage = $total > 0
        ? round(($current / $total) * 100, 1)
        : ($max > 0 ? round(((float) ($value ?? 0)) / $max * 100, 1) : 0);

    $barColor = match($color) {
        'primary', 'success', 'warning', 'danger', 'info', 'purple', 'teal' => 'bg-'.$color,
        default => match(true) {
            $percentage >= 70 => 'bg-success',
            $percentage >= 40 => 'bg-warning',
            default => 'bg-danger',
        },
    };

    $textColor = str_replace('bg-', 'text-', $barColor);
@endphp

<div class="mb-3">
    @if($label || ($showPercentage && $suffix === null))
        <div class="mb-2 d-flex justify-content-between align-items-center gap-2">
            <span class="small fw-semibold">{{ $label }}</span>
            @if($showPercentage)
                <span class="small fw-bold {{ $suffix ? '' : $textColor }}">{{ $suffix ?? ($percentage.'%') }}</span>
            @endif
        </div>
    @endif
    <div class="progress" style="height: {{ $height }}px;">
        <div class="progress-bar {{ $barColor }}" style="width: {{ min($percentage, 100) }}%"></div>
    </div>
    @if($batchName)
        <div class="mt-2 small text-muted">{{ $batchName }}</div>
    @endif
</div>
