@props([
    'current' => 0,
    'total' => 0,
    'label' => '',
    'batchName' => '',
    'color' => 'auto',
    'height' => 10,
    'showPercentage' => true,
])

@php
    $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;

    $barColor = match($color) {
        'primary' => 'bg-primary',
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'danger' => 'bg-danger',
        'info' => 'bg-info',
        default => match(true) {
            $percentage >= 70 => 'bg-success',
            $percentage >= 40 => 'bg-warning',
            default => 'bg-danger',
        },
    };
@endphp

<div class="mb-3">
    @if($label || $showPercentage)
        <div class="mb-2 d-flex justify-content-between align-items-center">
            @if($label)
                <span class="small fw-semibold">{{ $label }}</span>
            @endif
            @if($showPercentage)
                <span class="small fw-bold {{ $barColor == 'bg-success' ? 'text-success' : ($barColor == 'bg-warning' ? 'text-warning' : 'text-danger') }}">
                    {{ $percentage }}%
                </span>
            @endif
        </div>
    @endif
    <div class="progress" style="height: {{ $height }}px;">
        <div class="progress-bar {{ $barColor }}" style="width: {{ min($percentage, 100) }}%"></div>
    </div>
    @if($batchName)
        <div class="mt-2 small text-muted">{{ $batchName }}</div>
    @endif
    @if($label || $showPercentage === false)
        <div class="mt-1 small text-muted">
            {{ $current }} dari {{ $total }}
            @if(!$showPercentage)
                ({{ $percentage }}%)
            @endif
        </div>
    @endif
</div>
