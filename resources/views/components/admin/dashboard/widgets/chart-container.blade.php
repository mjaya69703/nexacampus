@props([
    'id' => 'chart',
    'title' => 'Chart',
    'subtitle' => '',
    'type' => 'bar',
    'height' => 240,
    'badge' => '',
    'badgeColor' => 'primary',
])

@php
    $badgeClasses = match($badgeColor) {
        'primary' => 'bg-primary-lt text-primary',
        'success' => 'bg-success-lt text-success',
        'warning' => 'bg-warning-lt text-warning',
        'danger' => 'bg-danger-lt text-danger',
        'info' => 'bg-info-lt text-info',
        'purple' => 'bg-purple-lt text-purple',
        'teal' => 'bg-teal-lt text-teal',
        default => 'bg-secondary-lt text-secondary',
    };
@endphp

<div class="card border rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    @if(isset($icon))
                        <i class="{{ $icon }} me-2"></i>
                    @endif
                    {{ $title }}
                </h5>
                @if($subtitle)
                    <span class="text-muted small">{{ $subtitle }}</span>
                @endif
            </div>
            @if($badge)
                <span class="badge {{ $badgeClasses }} fw-bold px-3 py-1.5 rounded-pill">{{ $badge }}</span>
            @endif
        </div>
        <div id="{{ $id }}" style="min-height: {{ $height }}px;"></div>
    </div>
</div>

@once
    @push('scripts')
        <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
    @endpush
@endonce
