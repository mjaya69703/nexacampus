<?php

use App\Models\Organization\EmployeeAttendanceRecord;
use Livewire\Component;

new class extends Component
{
    public EmployeeAttendanceRecord $record;

    public function mount(int $id): void
    {
        $this->record = EmployeeAttendanceRecord::with([
            'employeeProfile.user',
            'employeeProfile.primaryWorkUnit',
            'workUnit',
            'source',
            'checkInLocation',
            'checkOutLocation',
        ])->findOrFail($id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Detail Absensi Pegawai',
        ]);
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'present' => 'bg-success',
            'late' => 'bg-warning text-dark',
            'absent' => 'bg-danger',
            'leave', 'sick' => 'bg-info',
            'remote' => 'bg-primary',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status)->title().'</span>';
    }

    private function locationBadge(?string $status): string
    {
        $class = match ($status) {
            'inside_radius' => 'bg-success',
            'outside_radius', 'gps_missing' => 'bg-danger',
            'partial' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status ?: 'unverified')->replace('_', ' ')->title().'</span>';
    }
};
?>

@push('styles')
    <style>
        .attendance-photo-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            height: 100%;
        }

        .attendance-photo-frame {
            aspect-ratio: 4 / 3;
            background: #111827;
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .attendance-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Detail Absensi Pegawai</h3>
                <small class="text-muted">{{ $record->attendance_date->format('d M Y') }} - {{ $record->employeeProfile?->user?->name }}</small>
            </div>
            <a href="{{ route('admin.organization.employee-attendance-records.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            <div class="row row-cards">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg">{{ str($record->employeeProfile?->user?->name ?? 'P')->substr(0, 2)->upper() }}</span>
                        <div>
                            <div class="h3 mb-1">{{ $record->employeeProfile?->user?->name }}</div>
                            <div class="text-secondary">
                                {{ $record->employeeProfile?->employee_number ?: 'Nomor pegawai belum diisi' }}
                                @if ($record->employeeProfile?->primaryWorkUnit)
                                    - {{ $record->employeeProfile->primaryWorkUnit->name }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-3">Tanggal</dt>
                        <dd class="col-sm-9">{{ $record->attendance_date->format('d M Y') }}</dd>
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">{!! $this->statusBadge($record->status) !!}</dd>
                        <dt class="col-sm-3">Sumber</dt>
                        <dd class="col-sm-9">{{ $record->source?->name ?? '-' }}</dd>
                        <dt class="col-sm-3">Unit Kerja</dt>
                        <dd class="col-sm-9">{{ $record->workUnit?->name ?? $record->employeeProfile?->primaryWorkUnit?->name ?? '-' }}</dd>
                        <dt class="col-sm-3">Check-in</dt>
                        <dd class="col-sm-9">{{ $record->check_in_at?->format('d M Y H:i') ?? '-' }}</dd>
                        <dt class="col-sm-3">Check-out</dt>
                        <dd class="col-sm-9">{{ $record->check_out_at?->format('d M Y H:i') ?? '-' }}</dd>
                        <dt class="col-sm-3">Durasi</dt>
                        <dd class="col-sm-9">{{ $record->work_minutes }} menit</dd>
                        <dt class="col-sm-3">Status Radius</dt>
                        <dd class="col-sm-9">{!! $this->locationBadge($record->location_status) !!}</dd>
                        @if ($record->notes)
                            <dt class="col-sm-3">Catatan</dt>
                            <dd class="col-sm-9">{{ $record->notes }}</dd>
                        @endif
                    </dl>
                </div>

                <div class="col-lg-4">
                    <div class="border rounded p-3 mb-3">
                        <div class="text-secondary mb-2">Lokasi Check-in</div>
                        <div class="fw-semibold">{{ $record->checkInLocation?->name ?? '-' }}</div>
                        <div class="text-secondary">{{ $record->check_in_latitude && $record->check_in_longitude ? $record->check_in_latitude.', '.$record->check_in_longitude : 'Koordinat tidak tersedia' }}</div>
                        <div class="mt-2">
                            <span class="badge bg-azure-lt">{{ $record->check_in_distance_meters !== null ? $record->check_in_distance_meters.' m dari kantor' : 'jarak belum ada' }}</span>
                            @if ($record->check_in_accuracy_meters !== null)
                                <span class="badge bg-secondary-lt">{{ $record->check_in_accuracy_meters }} m akurasi</span>
                            @endif
                        </div>
                    </div>

                    <div class="border rounded p-3">
                        <div class="text-secondary mb-2">Lokasi Check-out</div>
                        <div class="fw-semibold">{{ $record->checkOutLocation?->name ?? '-' }}</div>
                        <div class="text-secondary">{{ $record->check_out_latitude && $record->check_out_longitude ? $record->check_out_latitude.', '.$record->check_out_longitude : 'Koordinat tidak tersedia' }}</div>
                        <div class="mt-2">
                            <span class="badge bg-azure-lt">{{ $record->check_out_distance_meters !== null ? $record->check_out_distance_meters.' m dari kantor' : 'jarak belum ada' }}</span>
                            @if ($record->check_out_accuracy_meters !== null)
                                <span class="badge bg-secondary-lt">{{ $record->check_out_accuracy_meters }} m akurasi</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        @foreach ([['label' => 'Foto Check-in', 'url' => $record->check_in_photo_url, 'time' => $record->check_in_at?->format('H:i')], ['label' => 'Foto Check-out', 'url' => $record->check_out_photo_url, 'time' => $record->check_out_at?->format('H:i')]] as $photo)
            <div class="col-md-6">
                <div class="attendance-photo-card">
                    <div class="attendance-photo-frame">
                        @if ($photo['url'])
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="d-block w-100 h-100">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}">
                            </a>
                        @else
                            <div class="text-secondary text-center p-4">
                                <i class="fas fa-image fa-2x mb-2"></i>
                                <div>Belum ada foto.</div>
                            </div>
                        @endif
                    </div>
                    <div class="p-3">
                        <div class="fw-semibold">{{ $photo['label'] }}</div>
                        <div class="text-secondary">{{ $photo['time'] ?? '-' }}</div>
                        @if ($photo['url'])
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-3">
                                <i class="fas fa-up-right-from-square me-1"></i>Buka Foto
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
