@props([
    'title' => 'Data Table',
    'columns' => [],
    'items' => [],
    'emptyMessage' => 'Tidak ada data',
    'badge' => '',
    'badgeColor' => 'primary',
    'actionRoute' => '',
    'actionLabel' => 'Detail',
])

@php
    $badgeClasses = match($badgeColor) {
        'primary' => 'bg-primary-lt text-primary',
        'success' => 'bg-success-lt text-success',
        'warning' => 'bg-warning-lt text-warning',
        'danger' => 'bg-danger-lt text-danger',
        'info' => 'bg-info-lt text-info',
        default => 'bg-secondary-lt text-secondary',
    };

    $hasKeys = count($items) > 0 && is_array($items[0] ?? null) && array_keys_is_associative($items[0] ?? []);
@endphp

<div class="card border rounded-4">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">
                @if(isset($icon))
                    <i class="{{ $icon }} me-2"></i>
                @endif
                {{ $title }}
            </h5>
            @if($badge)
                <span class="badge {{ $badgeClasses }}">{{ $badge }}</span>
            @endif
        </div>

        @if(count($items) > 0)
            <div class="table-responsive">
                <table class="table table-vcenter table-sm card-table">
                    <thead>
                        <tr>
                            @if($hasKeys)
                                @foreach(array_keys($items[0]) as $col)
                                    <th>{{ is_string($col) ? ucfirst(str_replace('_', ' ', $col)) : $col }}</th>
                                @endforeach
                            @else
                                @foreach($columns as $col)
                                    <th>{{ $col }}</th>
                                @endforeach
                            @endif
                            @if($actionRoute || isset($slot))
                                <th class="text-end">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                @if($hasKeys)
                                    @foreach($item as $value)
                                        <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                    @endforeach
                                @else
                                    @foreach($item as $cell)
                                        <td>{{ is_array($cell) ? json_encode($cell) : $cell }}</td>
                                    @endforeach
                                @endif
                                @if($actionRoute || isset($slot))
                                    <td class="text-end">
                                        @if(isset($slot) && !$slot->isEmpty())
                                            {{ $slot }}
                                        @elseif($actionRoute)
                                            <a href="{{ $actionRoute }}" class="btn btn-ghost-primary px-3 py-1">{{ $actionLabel }}</a>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-muted small text-center py-4">{{ $emptyMessage }}</div>
        @endif
    </div>
</div>

@php
function array_keys_is_associative(array $arr): bool {
    if (empty($arr)) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}
@endphp
