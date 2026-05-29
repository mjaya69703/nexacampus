<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Support\Organization\EmployeeAttendanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $attendancePhoto = null;
    public float|string|null $latitude = null;
    public float|string|null $longitude = null;
    public int|string|null $accuracy = null;
    public bool $photoReady = false;
    public int $uploadProgress = 0;
    public string $uploadStatus = 'Belum ada foto';
    public string $gpsStatus = 'Menunggu lokasi';

    public function mount(): void
    {
        abort_unless($this->employeeProfile()?->is_active, 403);
    }

    public function checkIn(EmployeeAttendanceService $service): void
    {
        try {
            $payload = $this->validatedPayload();
            $service->checkInSelf($this->employeeProfile(), auth()->id(), $payload);
            $this->resetCapture();
            session()->flash('success', 'Check-in berhasil dicatat.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Absensi gagal diproses. Coba ulangi setelah foto dan lokasi siap.');
        }
    }

    public function checkOut(EmployeeAttendanceService $service): void
    {
        try {
            $payload = $this->validatedPayload();
            $service->checkOutSelf($this->employeeProfile(), auth()->id(), $payload);
            $this->resetCapture();
            session()->flash('success', 'Check-out berhasil dicatat.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Absensi gagal diproses. Coba ulangi setelah foto dan lokasi siap.');
        }
    }

    public function render()
    {
        return $this->view([
            'employee' => $this->employeeProfile(),
            'todayRecord' => $this->todayRecord(),
            'records' => $this->records(),
            'locations' => EmployeeAttendanceLocation::where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'menus' => 'Kepegawaian Saya',
            'pages' => 'Absensi Saya',
        ]);
    }

    private function employeeProfile()
    {
        return auth()->user()?->employeeProfile()->with('primaryWorkUnit')->first();
    }

    private function todayRecord(): ?EmployeeAttendanceRecord
    {
        return EmployeeAttendanceRecord::query()
            ->with(['source', 'checkInLocation', 'checkOutLocation'])
            ->where('employee_profile_id', $this->employeeProfile()->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->whereHas('source', fn ($query) => $query->where('code', 'EMPLOYEE_SELF'))
            ->first();
    }

    private function records()
    {
        return EmployeeAttendanceRecord::query()
            ->with(['source', 'workUnit', 'checkInLocation', 'checkOutLocation'])
            ->where('employee_profile_id', $this->employeeProfile()->id)
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->limit(12)
            ->get();
    }

    private function validatedPayload(): array
    {
        $validated = $this->validate([
            'attendancePhoto' => ['required', 'image', 'max:2048'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! $this->photoReady) {
            throw ValidationException::withMessages([
                'attendancePhoto' => 'Foto masih diproses. Tunggu sampai status foto siap dipakai.',
            ]);
        }

        $path = $this->attendancePhoto->store('employee-attendance', 'public');

        return [
            'photo_path' => $path,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
        ];
    }

    private function resetCapture(): void
    {
        $this->attendancePhoto = null;
        $this->photoReady = false;
        $this->uploadProgress = 0;
        $this->uploadStatus = 'Belum ada foto';
        $this->resetValidation();
    }

    private function locationBadge(?string $status): string
    {
        $class = match ($status) {
            'inside_radius' => 'bg-success',
            'outside_radius' => 'bg-danger',
            'partial' => 'bg-warning text-dark',
            'gps_missing' => 'bg-danger',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status ?: 'unverified')->replace('_', ' ')->title().'</span>';
    }
};
?>

@push('styles')
    <style>
        .attendance-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 0;
            border-radius: 20px;
            color: #fff;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
        }

        .attendance-hero::before {
            content: '';
            position: absolute;
            inset: -60% -30% auto auto;
            width: 420px;
            height: 420px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
        }

        .attendance-hero > .card-body {
            position: relative;
        }

        .attendance-camera {
            aspect-ratio: 4 / 3;
            background: #111827;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }

        .attendance-camera video,
        .attendance-camera img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .attendance-camera-empty {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            color: rgba(255, 255, 255, .72);
            text-align: center;
            padding: 1rem;
        }

        .attendance-metric {
            border: 1px solid rgba(98, 105, 118, .18);
            border-radius: 14px;
            padding: 1rem;
            height: 100%;
            background: var(--tblr-bg-surface);
        }

        .attendance-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .45rem .75rem;
            font-size: .82rem;
            font-weight: 700;
            background: #eef2ff;
            color: #4338ca;
        }

        .attendance-upload-progress {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .attendance-upload-progress > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width .2s ease;
        }

        .attendance-proof-list {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .attendance-proof-thumb {
            width: 54px;
            height: 54px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            padding: 0;
            background: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .attendance-proof-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .attendance-map {
            height: 320px;
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

        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: .42rem .75rem;
            background: rgba(255, 255, 255, .18);
            border-radius: 8px;
            color: white;
            font-size: .84rem;
            backdrop-filter: blur(10px);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card attendance-hero mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width: 72px; height: 72px; background: rgba(255,255,255,.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: .9rem; opacity: .88; margin-bottom: .35rem;">Kepegawaian Saya</div>
                            <h1 class="h2 mb-2" style="font-weight: 800;">Absensi Mandiri</h1>
                            <div style="opacity: .9; margin-bottom: 1rem;">{{ $employee->user?->name }} - {{ $employee->primaryWorkUnit?->name ?? 'Unit kerja belum diisi' }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="info-badge"><i class="fas fa-calendar-day me-2"></i>{{ now()->format('d M Y') }}</span>
                                <span class="info-badge"><i class="fas fa-right-to-bracket me-2"></i>{{ $todayRecord?->check_in_at?->format('H:i') ?? 'Belum check-in' }}</span>
                                <span class="info-badge"><i class="fas fa-right-from-bracket me-2"></i>{{ $todayRecord?->check_out_at?->format('H:i') ?? 'Belum check-out' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded p-3">
                                <div class="text-white-50">Masuk</div>
                                <div class="h1 mb-0 text-white">{{ $todayRecord?->check_in_at?->format('H:i') ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white bg-opacity-10 rounded p-3">
                                <div class="text-white-50">Keluar</div>
                                <div class="h1 mb-0 text-white">{{ $todayRecord?->check_out_at?->format('H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Verifikasi Kehadiran</h3>
                </div>
                <div class="card-body">
                    <div class="attendance-camera mb-3" wire:ignore>
                        <video id="attendanceCamera" playsinline autoplay muted class="d-none"></video>
                        <img id="attendancePreview" class="d-none" alt="Preview absensi">
                        <div id="attendanceCameraEmpty" class="attendance-camera-empty">
                            <div>
                                <i class="fas fa-camera fa-2x mb-2"></i>
                                <div>Ambil foto atau pilih gambar untuk bukti absensi.</div>
                            </div>
                        </div>
                    </div>

                    <input id="attendancePhotoInput" type="file" accept="image/*" capture="user" class="d-none">

                    <div class="btn-list mb-3">
                        <button type="button" id="startCameraButton" class="btn btn-outline-primary">
                            <i class="fas fa-video me-2"></i>Kamera
                        </button>
                        <button type="button" id="capturePhotoButton" class="btn btn-primary">
                            <i class="fas fa-camera me-2"></i>Ambil Foto
                        </button>
                        <button type="button" id="pickPhotoButton" class="btn btn-outline-secondary">
                            <i class="fas fa-upload me-2"></i>Upload
                        </button>
                    </div>

                    <div class="attendance-metric mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                            <div>
                                <div class="subheader">Foto Absensi</div>
                                <div class="fw-semibold" id="uploadStatusText">{{ $uploadStatus }}</div>
                            </div>
                            <span class="attendance-status-pill" id="photoReadyBadge">
                                <i class="fas {{ $photoReady ? 'fa-circle-check' : 'fa-circle-dot' }}"></i>
                                {{ $photoReady ? 'Siap' : 'Belum siap' }}
                            </span>
                        </div>
                        <div class="attendance-upload-progress">
                            <span id="uploadProgressBar" style="width: {{ $uploadProgress }}%;"></span>
                        </div>
                        <div class="small text-secondary mt-2" id="uploadProgressText">{{ $uploadProgress > 0 ? $uploadProgress.'%' : 'Foto akan dikompres ke WebP sebelum dikirim.' }}</div>
                    </div>

                    @error('attendancePhoto') <div class="text-danger mb-2">{{ $message }}</div> @enderror

                    <div class="attendance-metric mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="subheader">GPS</div>
                                <div class="fw-semibold" id="gpsStatusText">{{ $gpsStatus }}</div>
                                <div class="text-secondary" id="gpsCoordinateText">{{ $latitude && $longitude ? $latitude.', '.$longitude : 'Koordinat belum tersedia' }}</div>
                            </div>
                            <button type="button" id="refreshLocationButton" class="btn btn-outline-primary">
                                <i class="fas fa-location-crosshairs"></i>
                            </button>
                        </div>
                        @error('latitude') <div class="text-danger mt-2">{{ $message }}</div> @enderror
                        @error('longitude') <div class="text-danger mt-2">{{ $message }}</div> @enderror
                    </div>

                    <div class="attendance-metric mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <div class="subheader">Peta Radius Absensi</div>
                                <div class="fw-semibold" id="attendanceMapStatus">Menunggu lokasi</div>
                                <div class="text-secondary" id="attendanceMapDistance">Jarak belum dihitung</div>
                            </div>
                            <span class="badge bg-indigo-lt text-indigo">{{ $locations->count() }} lokasi</span>
                        </div>
                        <div id="employeeAttendanceMap" class="attendance-map" wire:ignore></div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" id="checkInButton" class="btn btn-primary" wire:click="checkIn" @disabled($todayRecord?->check_in_at || ! $photoReady)>
                            <i class="fas fa-right-to-bracket me-2"></i>Check-in
                        </button>
                        <button type="button" id="checkOutButton" class="btn btn-outline-primary" wire:click="checkOut" @disabled(! $todayRecord?->check_in_at || $todayRecord?->check_out_at || ! $photoReady)>
                            <i class="fas fa-right-from-bracket me-2"></i>Check-out
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title mb-0">Lokasi Kantor Aktif</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                @forelse ($locations as $location)
                                    <div class="col-md-6">
                                        <div class="attendance-metric">
                                            <div class="fw-semibold">{{ $location->name }}</div>
                                            <div class="text-secondary">{{ $location->latitude }}, {{ $location->longitude }}</div>
                                            <div class="mt-2"><span class="badge bg-azure-lt">{{ $location->radius_meters }} meter</span></div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-secondary">Belum ada lokasi kantor aktif. Admin bisa menambahkan dari Kepegawaian > Lokasi Absensi.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title mb-0">Riwayat Absensi</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table card-table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Masuk</th>
                                        <th>Keluar</th>
                                        <th>Lokasi</th>
                                        <th>Bukti</th>
                                        <th>Durasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($records as $record)
                                        <tr>
                                            <td>{{ $record->attendance_date->format('d M Y') }}</td>
                                            <td>{!! $this->locationBadge($record->location_status) !!}</td>
                                            <td>{{ $record->check_in_at?->format('H:i') ?? '-' }}</td>
                                            <td>{{ $record->check_out_at?->format('H:i') ?? '-' }}</td>
                                            <td>
                                                <div>{{ $record->checkInLocation?->name ?? $record->checkOutLocation?->name ?? '-' }}</div>
                                                <div class="text-secondary">{{ $record->check_in_distance_meters !== null ? $record->check_in_distance_meters.' m' : '-' }}</div>
                                            </td>
                                            <td>
                                                <div class="attendance-proof-list">
                                                    @foreach ([['label' => 'Masuk', 'url' => $record->check_in_photo_url], ['label' => 'Keluar', 'url' => $record->check_out_photo_url]] as $photo)
                                                        @if ($photo['url'])
                                                            <button type="button" class="attendance-proof-thumb" data-bs-toggle="modal" data-bs-target="#attendancePhoto{{ $record->id }}{{ $loop->index }}" title="Foto {{ $photo['label'] }}">
                                                                <img src="{{ $photo['url'] }}" alt="Foto {{ $photo['label'] }}">
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                    @if (! $record->check_in_photo_url && ! $record->check_out_photo_url)
                                                        <span class="text-secondary">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $record->work_minutes }} menit</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-secondary py-4">Belum ada riwayat absensi.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($records as $record)
        @foreach ([['label' => 'Masuk', 'url' => $record->check_in_photo_url, 'time' => $record->check_in_at?->format('d M Y H:i')], ['label' => 'Keluar', 'url' => $record->check_out_photo_url, 'time' => $record->check_out_at?->format('d M Y H:i')]] as $photo)
            @if ($photo['url'])
                <div class="modal fade" id="attendancePhoto{{ $record->id }}{{ $loop->index }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title mb-0">Foto Check-{{ $photo['label'] }}</h5>
                                    <small class="text-secondary">{{ $photo['time'] ?? '-' }}</small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0">
                                <img src="{{ $photo['url'] }}" alt="Foto Check-{{ $photo['label'] }}" class="w-100 d-block">
                            </div>
                            <div class="modal-footer">
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                                    <i class="fas fa-up-right-from-square me-1"></i>Buka Foto
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach
</div>

@push('scripts')
    <script>
        (() => {
            let stream = null;
            let uploadReady = @js($photoReady);
            const checkInLocked = @js((bool) $todayRecord?->check_in_at);
            const checkOutLocked = @js(! $todayRecord?->check_in_at || (bool) $todayRecord?->check_out_at);
            const officeLocations = @js($locations->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'radius' => (int) $location->radius_meters,
            ])->values());
            let attendanceMap = null;
            let userMarker = null;
            let distanceLine = null;
            let distanceMarker = null;
            const officeLayers = [];

            const formatDistance = (meters) => {
                if (meters === null || Number.isNaN(meters)) return 'Jarak belum dihitung';

                return meters >= 1000
                    ? `${(meters / 1000).toFixed(2)} km`
                    : `${Math.round(meters)} m`;
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

            const nearestOffice = (latitude, longitude) => officeLocations
                .map((location) => ({
                    ...location,
                    distance: distanceMeters(latitude, longitude, location.latitude, location.longitude),
                }))
                .sort((a, b) => a.distance - b.distance)[0] ?? null;

            const ensureAttendanceMap = () => {
                if (attendanceMap || ! window.L || ! document.getElementById('employeeAttendanceMap')) {
                    return attendanceMap;
                }

                attendanceMap = L.map('employeeAttendanceMap', {
                    zoomControl: true,
                    scrollWheelZoom: false,
                }).setView([-6.2, 106.816666], 12);

                const primaryTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                }).addTo(attendanceMap);
                const fallbackTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                });
                let fallbackLoaded = false;

                primaryTiles.on('tileerror', () => {
                    if (fallbackLoaded) return;
                    fallbackLoaded = true;
                    primaryTiles.remove();
                    fallbackTiles.addTo(attendanceMap);
                    setTimeout(() => attendanceMap.invalidateSize(), 150);
                });

                officeLocations.forEach((location) => {
                    const point = [location.latitude, location.longitude];
                    const radius = L.circle(point, {
                        radius: location.radius,
                        color: '#4f46e5',
                        weight: 2,
                        fillColor: '#6366f1',
                        fillOpacity: .14,
                    }).addTo(attendanceMap);
                    const marker = L.marker(point).addTo(attendanceMap)
                        .bindPopup(`${location.name}<br>Radius ${formatDistance(location.radius)}`);

                    officeLayers.push(radius, marker);
                });

                if (officeLayers.length > 0) {
                    attendanceMap.fitBounds(L.featureGroup(officeLayers).getBounds(), { padding: [24, 24], maxZoom: 16 });
                }

                [150, 500, 1000].forEach((delay) => {
                    setTimeout(() => attendanceMap.invalidateSize(), delay);
                });

                return attendanceMap;
            };

            const updateAttendanceMap = (latitude, longitude) => {
                const map = ensureAttendanceMap();
                if (! map) return;

                const userPoint = [latitude, longitude];
                const nearest = nearestOffice(latitude, longitude);
                const mapStatus = document.getElementById('attendanceMapStatus');
                const mapDistance = document.getElementById('attendanceMapDistance');

                if (userMarker) userMarker.remove();
                if (distanceLine) distanceLine.remove();
                if (distanceMarker) distanceMarker.remove();

                userMarker = L.marker(userPoint, {
                    title: 'Posisi kamu',
                }).addTo(map).bindPopup('Posisi kamu');

                if (! nearest) {
                    mapStatus && (mapStatus.textContent = 'Belum ada lokasi kantor aktif');
                    mapDistance && (mapDistance.textContent = 'Admin perlu menambahkan lokasi absensi.');
                    map.setView(userPoint, 16);
                    return;
                }

                const officePoint = [nearest.latitude, nearest.longitude];
                const insideRadius = nearest.distance <= nearest.radius;
                const distanceText = formatDistance(nearest.distance);

                distanceLine = L.polyline([userPoint, officePoint], {
                    color: insideRadius ? '#16a34a' : '#dc2626',
                    weight: 3,
                    dashArray: insideRadius ? null : '8 8',
                }).addTo(map);

                distanceMarker = L.marker([
                    (latitude + nearest.latitude) / 2,
                    (longitude + nearest.longitude) / 2,
                ], {
                    interactive: false,
                    icon: L.divIcon({
                        className: 'attendance-distance-label',
                        html: distanceText,
                    }),
                }).addTo(map);

                mapStatus && (mapStatus.textContent = insideRadius ? `Di dalam radius ${nearest.name}` : `Di luar radius ${nearest.name}`);
                mapDistance && (mapDistance.textContent = `Jarak ${distanceText}, radius ${formatDistance(nearest.radius)}`);

                const bounds = L.latLngBounds([userPoint, officePoint]);
                if (insideRadius) {
                    map.setView(userPoint, 17);
                } else {
                    map.fitBounds(bounds.pad(.25), { padding: [24, 24], maxZoom: 15 });
                }
            };

            const setUploadUi = (status, progress = null, ready = uploadReady, previewUrl = null) => {
                uploadReady = ready;
                const statusText = document.getElementById('uploadStatusText');
                const progressBar = document.getElementById('uploadProgressBar');
                const progressText = document.getElementById('uploadProgressText');
                const badge = document.getElementById('photoReadyBadge');
                const preview = document.getElementById('attendancePreview');
                const empty = document.getElementById('attendanceCameraEmpty');
                const checkIn = document.getElementById('checkInButton');
                const checkOut = document.getElementById('checkOutButton');

                if (statusText) statusText.textContent = status;
                if (progress !== null) {
                    const normalized = Math.max(0, Math.min(100, Math.round(progress)));
                    if (progressBar) progressBar.style.width = `${normalized}%`;
                    if (progressText) progressText.textContent = normalized > 0 ? `${normalized}%` : 'Foto akan dikompres ke WebP sebelum dikirim.';
                }
                if (badge) {
                    badge.innerHTML = ready
                        ? '<i class="fas fa-circle-check"></i> Siap'
                        : '<i class="fas fa-circle-dot"></i> Belum siap';
                }
                if (previewUrl && preview) {
                    preview.src = previewUrl;
                    preview.classList.remove('d-none');
                    empty?.classList.add('d-none');
                }
                if (checkIn) checkIn.disabled = checkInLocked || ! ready;
                if (checkOut) checkOut.disabled = checkOutLocked || ! ready;
            };

            const uploadWebp = async (blob) => {
                const file = new File([blob], `attendance-${Date.now()}.webp`, { type: 'image/webp' });
                const previewUrl = URL.createObjectURL(blob);

                setUploadUi('Mengupload foto...', 1, false, previewUrl);
                await @this.set('photoReady', false);
                await @this.set('uploadStatus', 'Mengupload foto...');
                await @this.set('uploadProgress', 1);

                @this.upload('attendancePhoto', file, async () => {
                    await @this.set('photoReady', true);
                    await @this.set('uploadStatus', 'Foto siap dipakai.');
                    await @this.set('uploadProgress', 100);
                    setUploadUi('Foto siap dipakai.', 100, true, previewUrl);
                }, async () => {
                    await @this.set('photoReady', false);
                    await @this.set('uploadStatus', 'Upload foto gagal. Coba ambil ulang.');
                    await @this.set('uploadProgress', 0);
                    setUploadUi('Upload foto gagal. Coba ambil ulang.', 0, false);
                }, (event) => {
                    const progress = event?.detail?.progress ?? event?.progress ?? event ?? 0;
                    setUploadUi('Mengupload foto...', progress, false, previewUrl);
                    @this.set('uploadProgress', Math.round(progress));
                });
            };

            const imageToWebp = (file) => new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const maxSize = 1280;
                    const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    const context = canvas.getContext('2d');
                    context.drawImage(img, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Gagal konversi gambar.')), 'image/webp', 0.78);
                };
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });

            const locate = () => {
                const status = document.getElementById('gpsStatusText');
                if (! navigator.geolocation) {
                    @this.set('gpsStatus', 'GPS tidak tersedia');
                    return;
                }

                status && (status.textContent = 'Mengambil lokasi...');
                navigator.geolocation.getCurrentPosition((position) => {
                    const latitude = Number(position.coords.latitude.toFixed(7));
                    const longitude = Number(position.coords.longitude.toFixed(7));
                    @this.set('latitude', latitude);
                    @this.set('longitude', longitude);
                    @this.set('accuracy', Math.round(position.coords.accuracy));
                    @this.set('gpsStatus', `Akurasi ${Math.round(position.coords.accuracy)} meter`);
                    const coordinateText = document.getElementById('gpsCoordinateText');
                    coordinateText && (coordinateText.textContent = `${latitude}, ${longitude}`);
                    updateAttendanceMap(latitude, longitude);
                }, () => {
                    @this.set('gpsStatus', 'Izin lokasi ditolak atau gagal dibaca');
                    const mapStatus = document.getElementById('attendanceMapStatus');
                    mapStatus && (mapStatus.textContent = 'Izin lokasi ditolak atau gagal dibaca');
                }, {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0,
                });
            };

            document.getElementById('refreshLocationButton')?.addEventListener('click', locate);
            document.getElementById('pickPhotoButton')?.addEventListener('click', () => document.getElementById('attendancePhotoInput')?.click());
            document.getElementById('attendancePhotoInput')?.addEventListener('change', async (event) => {
                const file = event.target.files?.[0];
                if (file) {
                    try {
                        setUploadUi('Mengompres foto...', 0, false);
                        await @this.set('uploadStatus', 'Mengompres foto...');
                        await uploadWebp(await imageToWebp(file));
                    } catch (error) {
                        setUploadUi('Gagal membaca foto. Coba pilih gambar lain.', 0, false);
                    }
                }
            });
            document.getElementById('startCameraButton')?.addEventListener('click', async () => {
                const video = document.getElementById('attendanceCamera');
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = stream;
                    video.classList.remove('d-none');
                    document.getElementById('attendanceCameraEmpty')?.classList.add('d-none');
                } catch (error) {
                    document.getElementById('attendancePhotoInput')?.click();
                }
            });
            document.getElementById('capturePhotoButton')?.addEventListener('click', async () => {
                const video = document.getElementById('attendanceCamera');
                if (! video || ! stream) {
                    document.getElementById('attendancePhotoInput')?.click();
                    return;
                }

                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth || 960;
                canvas.height = video.videoHeight || 720;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                setUploadUi('Mengompres foto...', 0, false);
                canvas.toBlob((blob) => {
                    if (! blob) {
                        setUploadUi('Gagal mengambil foto. Coba ulangi.', 0, false);
                        return;
                    }

                    uploadWebp(blob);
                }, 'image/webp', 0.78);
            });

            setUploadUi(@js($uploadStatus), @js($uploadProgress), uploadReady);
            ensureAttendanceMap();
            locate();
        })();
    </script>
@endpush
