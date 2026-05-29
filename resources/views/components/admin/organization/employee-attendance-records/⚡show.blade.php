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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQPHQYp9zRZBvYeO1z8q0w6e1Ztf1w=" crossorigin="">
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

        .attendance-map {
            height: 420px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            background: #eef2ff;
        }

        .attendance-distance-label {
            border: 0;
            border-radius: 999px;
            padding: .35rem .6rem;
            background: rgba(17, 24, 39, .88);
            color: #fff;
            font-size: .76rem;
            font-weight: 700;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .18);
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

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Peta Radius & Posisi Absensi</h3>
                <small class="text-muted" id="adminAttendanceMapSummary">Radius kantor, posisi pegawai, dan jarak absensi.</small>
            </div>
            <span class="badge bg-indigo-lt text-indigo">{{ $record->location_status ? str($record->location_status)->replace('_', ' ')->title() : 'Unverified' }}</span>
        </div>
        <div class="card-body">
            <div id="adminAttendanceMap" class="attendance-map" wire:ignore></div>
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

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const mapElement = document.getElementById('adminAttendanceMap');
            if (! mapElement || ! window.L) return;

            const formatDistance = (meters) => {
                if (meters === null || meters === undefined || Number.isNaN(Number(meters))) {
                    return 'jarak belum ada';
                }

                const value = Number(meters);

                return value >= 1000
                    ? `${(value / 1000).toFixed(2)} km`
                    : `${Math.round(value)} m`;
            };

            const distanceMeters = (fromLat, fromLng, toLat, toLng) => {
                const earthRadius = 6371000;
                const latDelta = (toLat - fromLat) * Math.PI / 180;
                const lngDelta = (toLng - fromLng) * Math.PI / 180;
                const fromRad = fromLat * Math.PI / 180;
                const toRad = toLat * Math.PI / 180;
                const a = Math.sin(latDelta / 2) ** 2
                    + Math.cos(fromRad) * Math.cos(toRad) * Math.sin(lngDelta / 2) ** 2;

                return earthRadius * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
            };

            const points = @js([
                [
                    'label' => 'Check-in',
                    'time' => $record->check_in_at?->format('d M Y H:i'),
                    'latitude' => $record->check_in_latitude ? (float) $record->check_in_latitude : null,
                    'longitude' => $record->check_in_longitude ? (float) $record->check_in_longitude : null,
                    'distance' => $record->check_in_distance_meters,
                    'location' => $record->checkInLocation ? [
                        'name' => $record->checkInLocation->name,
                        'latitude' => (float) $record->checkInLocation->latitude,
                        'longitude' => (float) $record->checkInLocation->longitude,
                        'radius' => (int) $record->checkInLocation->radius_meters,
                    ] : null,
                ],
                [
                    'label' => 'Check-out',
                    'time' => $record->check_out_at?->format('d M Y H:i'),
                    'latitude' => $record->check_out_latitude ? (float) $record->check_out_latitude : null,
                    'longitude' => $record->check_out_longitude ? (float) $record->check_out_longitude : null,
                    'distance' => $record->check_out_distance_meters,
                    'location' => $record->checkOutLocation ? [
                        'name' => $record->checkOutLocation->name,
                        'latitude' => (float) $record->checkOutLocation->latitude,
                        'longitude' => (float) $record->checkOutLocation->longitude,
                        'radius' => (int) $record->checkOutLocation->radius_meters,
                    ] : null,
                ],
            ]);

            const map = L.map(mapElement, {
                zoomControl: true,
                scrollWheelZoom: false,
            }).setView([-6.2, 106.816666], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const layers = [];
            const renderedLocations = new Set();

            points.forEach((point) => {
                if (point.location) {
                    const locationKey = `${point.location.latitude},${point.location.longitude},${point.location.radius}`;
                    if (! renderedLocations.has(locationKey)) {
                        renderedLocations.add(locationKey);
                        const officePoint = [point.location.latitude, point.location.longitude];
                        layers.push(L.circle(officePoint, {
                            radius: point.location.radius,
                            color: '#4f46e5',
                            weight: 2,
                            fillColor: '#6366f1',
                            fillOpacity: .14,
                        }).addTo(map));
                        layers.push(L.marker(officePoint).addTo(map)
                            .bindPopup(`${point.location.name}<br>Radius ${formatDistance(point.location.radius)}`));
                    }
                }

                if (! point.latitude || ! point.longitude) return;

                const employeePoint = [point.latitude, point.longitude];
                layers.push(L.marker(employeePoint).addTo(map)
                    .bindPopup(`${point.label}<br>${point.time ?? '-'}<br>${point.latitude}, ${point.longitude}`));

                if (! point.location) return;

                const officePoint = [point.location.latitude, point.location.longitude];
                const measuredDistance = point.distance ?? distanceMeters(point.latitude, point.longitude, point.location.latitude, point.location.longitude);
                const insideRadius = measuredDistance <= point.location.radius;
                const distanceText = formatDistance(measuredDistance);

                layers.push(L.polyline([employeePoint, officePoint], {
                    color: insideRadius ? '#16a34a' : '#dc2626',
                    weight: 3,
                    dashArray: insideRadius ? null : '8 8',
                }).addTo(map));

                layers.push(L.marker([
                    (point.latitude + point.location.latitude) / 2,
                    (point.longitude + point.location.longitude) / 2,
                ], {
                    interactive: false,
                    icon: L.divIcon({
                        className: 'attendance-distance-label',
                        html: `${point.label}: ${distanceText}`,
                    }),
                }).addTo(map));
            });

            if (layers.length > 0) {
                const group = L.featureGroup(layers);
                const hasOutsidePoint = points.some((point) => point.location && point.distance !== null && point.distance > point.location.radius);
                map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: hasOutsidePoint ? 15 : 17 });

                const summary = document.getElementById('adminAttendanceMapSummary');
                const distances = points
                    .filter((point) => point.latitude && point.longitude && point.location)
                    .map((point) => `${point.label} ${formatDistance(point.distance ?? distanceMeters(point.latitude, point.longitude, point.location.latitude, point.location.longitude))}`);
                summary && distances.length > 0 && (summary.textContent = distances.join(' - '));
            }

            setTimeout(() => map.invalidateSize(), 250);
        })();
    </script>
@endpush
