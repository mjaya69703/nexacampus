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

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'present' => 'bg-success text-white',
            'late' => 'bg-warning text-dark',
            'absent' => 'bg-danger text-white',
            'leave', 'sick' => 'bg-info text-white',
            'remote' => 'bg-primary text-white',
            default => 'bg-secondary text-white',
        };
    }

    public function locationBadge(?string $status): string
    {
        return match ($status) {
            'inside_radius' => 'bg-success text-white',
            'outside_radius', 'gps_missing' => 'bg-danger text-white',
            'partial' => 'bg-warning text-dark',
            default => 'bg-secondary text-white',
        };
    }
};
?>

@push('styles')
    <style>
        .attendance-photo-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            height: 100%;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .attendance-photo-frame {
            aspect-ratio: 16 / 10;
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
            transition: transform 0.3s ease;
        }

        .attendance-photo-frame:hover img {
            transform: scale(1.03);
        }

        .attendance-map {
            height: 450px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
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

    <x-admin.organization.header
        title="Detail Absensi: {{ $record->employeeProfile?->user?->name }}"
        description="Presensi pada {{ $record->attendance_date->format('d M Y') }} &bull; No. Pegawai: {{ $record->employeeProfile?->employee_number ?: '-' }}"
        icon="clock"
    >
        <a href="{{ route('admin.organization.employee-attendance-records.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Kehadiran</div>
                        <div class="fw-bold">{{ str($record->status)->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-map-marked-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Validasi Radius</div>
                        <div class="fw-bold">{{ str($record->location_status ?: 'unverified')->replace('_', ' ')->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-stopwatch fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Durasi</div>
                        <div class="fw-bold">{{ $record->work_minutes }} Menit</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h4 class="card-title fw-bold mb-1 fs-5"><i class="fas fa-user-check text-primary me-2"></i>Informasi Kehadiran</h4>
                    <p class="text-muted small mb-0">Rincian waktu, sumber presensi, dan unit penugasan pegawai.</p>
                </div>
                <div class="card-body p-4 pt-3">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <tbody>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary w-35">Tanggal Absensi</th>
                                    <td class="pe-0 py-3 fw-bold text-dark">{{ $record->attendance_date->format('l, d M Y') }}</td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Unit Kerja</th>
                                    <td class="pe-0 py-3 text-dark">{{ $record->workUnit?->name ?? $record->employeeProfile?->primaryWorkUnit?->name ?? '-' }}</td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Waktu Check-In</th>
                                    <td class="pe-0 py-3 font-monospace text-primary fw-bold fs-6">{{ $record->check_in_at?->format('H:i:s (d M Y)') ?? '-' }}</td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Waktu Check-Out</th>
                                    <td class="pe-0 py-3 font-monospace text-danger fw-bold fs-6">{{ $record->check_out_at?->format('H:i:s (d M Y)') ?? '-' }}</td>
                                </tr>
                                <tr class="border-bottom">
                                    <th class="ps-0 py-3 text-secondary">Sumber Absensi</th>
                                    <td class="pe-0 py-3"><span class="badge bg-light text-dark border px-3 py-1">{{ $record->source?->name ?? 'Manual/System' }}</span></td>
                                </tr>
                                @if ($record->notes)
                                    <tr>
                                        <th class="ps-0 py-3 text-secondary">Catatan</th>
                                        <td class="pe-0 py-3 text-dark">{{ $record->notes }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-4">
            <div class="card border-0 shadow-sm rounded-4 flex-fill overflow-hidden bg-light bg-opacity-50">
                <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
                    <div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 mb-2"><i class="fas fa-sign-in-alt me-1"></i> Check-in Geofence</span>
                        <h6 class="fw-bold text-dark mb-1 fs-6">{{ $record->checkInLocation?->name ?? 'Lokasi Tidak Terdaftar' }}</h6>
                        <div class="small font-monospace text-muted mb-2">
                            {{ $record->check_in_latitude && $record->check_in_longitude ? $record->check_in_latitude.', '.$record->check_in_longitude : 'Koordinat tidak tersedia' }}
                        </div>
                    </div>
                    <div class="border-top pt-2 d-flex justify-content-between align-items-center small">
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1">{{ $record->check_in_distance_meters !== null ? $record->check_in_distance_meters.' m dari pusat' : 'Jarak belum dihitung' }}</span>
                        @if ($record->check_in_accuracy_meters !== null)
                            <span class="text-muted"><i class="fas fa-crosshairs me-1"></i>Akurasi: {{ $record->check_in_accuracy_meters }} m</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 flex-fill overflow-hidden bg-light bg-opacity-50">
                <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
                    <div>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 mb-2"><i class="fas fa-sign-out-alt me-1"></i> Check-out Geofence</span>
                        <h6 class="fw-bold text-dark mb-1 fs-6">{{ $record->checkOutLocation?->name ?? 'Lokasi Tidak Terdaftar' }}</h6>
                        <div class="small font-monospace text-muted mb-2">
                            {{ $record->check_out_latitude && $record->check_out_longitude ? $record->check_out_latitude.', '.$record->check_out_longitude : 'Koordinat tidak tersedia' }}
                        </div>
                    </div>
                    <div class="border-top pt-2 d-flex justify-content-between align-items-center small">
                        <span class="badge bg-danger text-white rounded-pill px-3 py-1">{{ $record->check_out_distance_meters !== null ? $record->check_out_distance_meters.' m dari pusat' : 'Jarak belum dihitung' }}</span>
                        @if ($record->check_out_accuracy_meters !== null)
                            <span class="text-muted"><i class="fas fa-crosshairs me-1"></i>Akurasi: {{ $record->check_out_accuracy_meters }} m</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="card-title fw-bold mb-1 fs-5"><i class="fas fa-map-marked-alt text-primary me-2"></i>Peta Posisi & Radius Absensi</h4>
                <p class="text-muted small mb-0" id="adminAttendanceMapSummary">Memvisualisasikan titik check-in/out terhadap radius toleransi geofence kantor.</p>
            </div>
            <span class="badge {{ $this->locationBadge($record->location_status) }} rounded-pill px-3 py-1">{{ $record->location_status ? str($record->location_status)->replace('_', ' ')->title() : 'Unverified' }}</span>
        </div>
        <div class="card-body p-4 pt-2">
            <div id="adminAttendanceMap" class="attendance-map shadow-inner" wire:ignore></div>
        </div>
    </div>

    <div class="row g-4">
        @foreach ([['label' => 'Bukti Foto Check-In', 'url' => $record->check_in_photo_url, 'time' => $record->check_in_at?->format('H:i:s WIB'), 'color' => 'success'], ['label' => 'Bukti Foto Check-Out', 'url' => $record->check_out_photo_url, 'time' => $record->check_out_at?->format('H:i:s WIB'), 'color' => 'danger']] as $photo)
            <div class="col-md-6">
                <div class="attendance-photo-card">
                    <div class="attendance-photo-frame">
                        @if ($photo['url'])
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="d-block w-100 h-100">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}">
                            </a>
                        @else
                            <div class="text-secondary text-center p-5">
                                <i class="fas fa-camera-slash fa-3x mb-2 text-muted"></i>
                                <div class="fw-medium text-white">Bukti Foto Tidak Tersedia</div>
                                <div class="small text-muted">Absensi dicatat tanpa lampiran foto kamera mobile.</div>
                            </div>
                        @endif
                    </div>
                    <div class="p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-{{ $photo['color'] }} bg-opacity-10 text-{{ $photo['color'] }} rounded-pill px-3 py-1 mb-1">{{ $photo['label'] }}</span>
                            <div class="fw-bold text-dark fs-6">{{ $photo['time'] ?? 'Waktu tidak tercatat' }}</div>
                        </div>
                        @if ($photo['url'])
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary rounded-pill px-4 shadow-sm fw-medium">
                                <i class="fas fa-external-link-alt me-2"></i>Lihat Foto Penuh
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
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

            const primaryTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            }).addTo(map);
            const fallbackTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            });
            let fallbackLoaded = false;

            primaryTiles.on('tileerror', () => {
                if (fallbackLoaded) return;
                fallbackLoaded = true;
                primaryTiles.remove();
                fallbackTiles.addTo(map);
                setTimeout(() => map.invalidateSize(), 150);
            });

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

            [150, 500, 1000].forEach((delay) => {
                setTimeout(() => map.invalidateSize(), delay);
            });
        })();
    </script>
@endpush
