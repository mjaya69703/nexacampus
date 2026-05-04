<?php

use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

new class extends Component {
    public Activity $activity;
    public array $properties = [];
    public ?string $causerName = null;

    public function mount($id)
    {
        $this->activity = Activity::query()
            ->with('causer')
            ->findOrFail($id);

        $this->properties = $this->activity->properties?->toArray() ?? [];

        if ($this->activity->causer) {
            $fullName = trim(($this->activity->causer->first_name ?? '') . ' ' . ($this->activity->causer->last_name ?? ''));

            $this->causerName = $fullName !== ''
                ? $fullName
                : ($this->activity->causer->name ?? 'User #' . $this->activity->causer_id);
        } else {
            $this->causerName = 'System';
        }
    }

    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Activity Log Detail',
        ];

        return $this->view()->layout('layouts.app', $data);
    }

    public function subjectLabel(): string
    {
        return $this->activity->subject_type
            ? class_basename($this->activity->subject_type)
            : '-';
    }

    public function prettyJson(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
};
?>

@push('styles')
    <style>
        .activity-json-block {
            background: var(--tblr-bg-surface-secondary, #f6f8fb);
            border: 1px solid var(--tblr-border-color, #dce1e7);
            border-radius: 12px;
            padding: 1rem;
            margin: 0;
            color: var(--tblr-body-color, #182433);
            font-size: .8125rem;
            line-height: 1.5;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
            max-height: 520px;
        }

        [data-bs-theme="dark"] .activity-json-block,
        .dark .activity-json-block {
            background: #0f172a;
            border-color: #334155;
            color: #e5e7eb;
        }

        .activity-detail-label {
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .02em;
            color: var(--tblr-secondary-color, #667382);
            margin-bottom: .35rem;
            text-transform: uppercase;
        }

        .activity-detail-value {
            font-size: .95rem;
            color: var(--tblr-body-color, #182433);
            word-break: break-word;
        }

        [data-bs-theme="dark"] .activity-detail-value,
        .dark .activity-detail-value {
            color: #f1f5f9;
        }

        .activity-meta-list .activity-meta-item + .activity-meta-item {
            border-top: 1px solid var(--tblr-border-color, #dce1e7);
        }

        [data-bs-theme="dark"] .activity-meta-list .activity-meta-item + .activity-meta-item,
        .dark .activity-meta-list .activity-meta-item + .activity-meta-item {
            border-top-color: #334155;
        }
    </style>
@endpush

<div>
    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Informasi Utama</h3>

                    <a href="{{ route('admin.system.activity-logs.index') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>

                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="activity-detail-label">Description</div>
                            <div class="activity-detail-value">{{ $activity->description ?: '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="activity-detail-label">Log Name</div>
                            <div class="activity-detail-value">{{ $activity->log_name ?: '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="activity-detail-label">Event</div>
                            <div class="activity-detail-value">{{ $activity->event ?: '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="activity-detail-label">Created At</div>
                            <div class="activity-detail-value">
                                {{ optional($activity->created_at)->format('d M Y H:i:s') }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="activity-detail-label">Causer</div>
                            <div class="activity-detail-value">
                                {{ $causerName }} (ID: {{ $activity->causer_id ?? '-' }})
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="activity-detail-label">Subject</div>
                            <div class="activity-detail-value">
                                {{ $this->subjectLabel() }} (ID: {{ $activity->subject_id ?? '-' }})
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Metadata</h3>
                </div>

                <div class="card-body activity-meta-list py-2">
                    <div class="activity-meta-item py-3">
                        <div class="activity-detail-label">Active Role</div>
                        <div class="activity-detail-value">{{ data_get($properties, 'active_role', '-') }}</div>
                    </div>

                    <div class="activity-meta-item py-3">
                        <div class="activity-detail-label">IP Address</div>
                        <div class="activity-detail-value">{{ data_get($properties, 'ip', '-') }}</div>
                    </div>

                    <div class="activity-meta-item py-3">
                        <div class="activity-detail-label">User Agent</div>
                        <div class="activity-detail-value small">
                            {{ data_get($properties, 'user_agent', '-') }}
                        </div>
                    </div>

                    <div class="activity-meta-item py-3">
                        <div class="activity-detail-label">Subject Type</div>
                        <div class="activity-detail-value">{{ $activity->subject_type ?? '-' }}</div>
                    </div>

                    <div class="activity-meta-item py-3">
                        <div class="activity-detail-label">Causer Type</div>
                        <div class="activity-detail-value">{{ $activity->causer_type ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($properties['attributes']))
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Attributes</h3>
            </div>
            <div class="card-body">
                <pre class="activity-json-block">{{ $this->prettyJson($properties['attributes']) }}</pre>
            </div>
        </div>
    @endif

    @if (!empty($properties['old']))
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Old Values</h3>
            </div>
            <div class="card-body">
                <pre class="activity-json-block">{{ $this->prettyJson($properties['old']) }}</pre>
            </div>
        </div>
    @endif

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Raw Properties</h3>
        </div>
        <div class="card-body">
            @if (!empty($properties))
                <pre class="activity-json-block">{{ $this->prettyJson($properties) }}</pre>
            @else
                <div class="text-secondary">
                    Activity ini tidak memiliki properties tambahan.
                </div>
            @endif
        </div>
    </div>
</div>