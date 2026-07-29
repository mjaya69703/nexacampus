@props([
    'label' => 'Stat',
    'value' => 0,
    'icon' => 'fas fa-chart-line',
    'color' => 'primary',
    'prefix' => '',
    'suffix' => '',
    'trend' => null,
    'trendValue' => null,
])

@php
    $colorClasses = match($color) {
        'primary' => ['bg' => 'bg-primary bg-opacity-10', 'text' => 'text-primary', 'badge' => 'bg-primary-lt text-primary'],
        'success' => ['bg' => 'bg-success bg-opacity-10', 'text' => 'text-success', 'badge' => 'bg-success-lt text-success'],
        'warning' => ['bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning', 'badge' => 'bg-warning-lt text-warning'],
        'danger' => ['bg' => 'bg-danger bg-opacity-10', 'text' => 'text-danger', 'badge' => 'bg-danger-lt text-danger'],
        'info' => ['bg' => 'bg-info bg-opacity-10', 'text' => 'text-info', 'badge' => 'bg-info-lt text-info'],
        'dark' => ['bg' => 'bg-dark bg-opacity-10', 'text' => 'text-dark', 'badge' => 'bg-dark-lt text-dark'],
        'purple' => ['bg' => 'bg-purple bg-opacity-10', 'text' => 'text-purple', 'badge' => 'bg-purple-lt text-purple'],
        'teal' => ['bg' => 'bg-teal bg-opacity-10', 'text' => 'text-teal', 'badge' => 'bg-teal-lt text-teal'],
        default => ['bg' => 'bg-secondary bg-opacity-10', 'text' => 'text-secondary', 'badge' => 'bg-secondary-lt text-secondary'],
    };

    $trendIcon = match($trend) {
        'up' => 'fas fa-arrow-up',
        'down' => 'fas fa-arrow-down',
        'neutral' => 'fas fa-minus',
        default => null,
    };

    $trendColor = match($trend) {
        'up' => 'text-success',
        'down' => 'text-danger',
        'neutral' => 'text-muted',
        default => null,
    };
@endphp

<div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
    <div class="text-muted small mb-1">{{ $label }}</div>
    <div class="d-flex align-items-end justify-content-between">
        <div>
            <div class="fs-2 fw-bold {{ $colorClasses['text'] }} lh-1">
                {{ $prefix }}{{ is_numeric($value) ? number_format((float)$value) : $value }}{{ $suffix }}
            </div>
            @if($trend && $trendValue)
                <div class="mt-1">
                    <span class="badge {{ $trendColor }} bg-opacity-10 rounded-pill py-0 px-2">
                        <i class="{{ $trendIcon }} me-1"></i>{{ $trendValue }}%
                    </span>
                </div>
            @endif
        </div>
        <i class="{{ $icon }} fs-4 {{ $colorClasses['text'] }} opacity-75"></i>
    </div>
</div>
