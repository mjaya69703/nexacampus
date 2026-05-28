<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Support\Organization\EmployeeAttendanceService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $attendancePhoto = null;
    public float|string|null $latitude = null;
    public float|string|null $longitude = null;
    public int|string|null $accuracy = null;
    public int $uploadProgress = 0;
    public string $gpsStatus = 'Menunggu lokasi';

    public function mount(): void
    {
        abort_unless($this->employeeProfile()?->is_active, 403);
    }

    public function checkIn(EmployeeAttendanceService $service): void
    {
        $payload = $this->validatedPayload();
        $service->checkInSelf($this->employeeProfile(), auth()->id(), $payload);
        $this->resetCapture();
        session()->flash('success', 'Check-in berhasil dicatat.');
    }

    public function checkOut(EmployeeAttendanceService $service): void
    {
        $payload = $this->validatedPayload();
        $service->checkOutSelf($this->employeeProfile(), auth()->id(), $payload);
        $this->resetCapture();
        session()->flash('success', 'Check-out berhasil dicatat.');
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
        $this->uploadProgress = 0;
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
            background: linear-gradient(135deg, rgba(20, 72, 132, .98), rgba(16, 107, 92, .96));
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
        }

        .attendance-camera {
            aspect-ratio: 4 / 3;
            background: #111827;
            border-radius: 8px;
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
            border-radius: 8px;
            padding: 1rem;
            height: 100%;
            background: var(--tblr-bg-surface);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="attendance-hero mb-3">
        <div class="p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-7">
                    <div class="subheader text-white-50">Absensi Mandiri</div>
                    <h1 class="mb-2">{{ now()->format('d M Y') }}</h1>
                    <div class="text-white-75">{{ $employee->user?->name }} · {{ $employee->primaryWorkUnit?->name ?? 'Unit kerja belum diisi' }}</div>
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
                    <div class="attendance-camera mb-3">
                        <video id="attendanceCamera" playsinline autoplay muted class="d-none"></video>
                        @if ($attendancePhoto)
                            <img src="{{ $attendancePhoto->temporaryUrl() }}" alt="Preview absensi">
                        @else
                            <div class="attendance-camera-empty">
                                <div>
                                    <i class="fas fa-camera fa-2x mb-2"></i>
                                    <div>Ambil foto atau pilih gambar untuk bukti absensi.</div>
                                </div>
                            </div>
                        @endif
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

                    @if ($uploadProgress > 0 && $uploadProgress < 100)
                        <div class="progress mb-3">
                            <div class="progress-bar" style="width: {{ $uploadProgress }}%">{{ $uploadProgress }}%</div>
                        </div>
                    @endif

                    @error('attendancePhoto') <div class="text-danger mb-2">{{ $message }}</div> @enderror

                    <div class="attendance-metric mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="subheader">GPS</div>
                                <div class="fw-semibold" id="gpsStatusText">{{ $gpsStatus }}</div>
                                <div class="text-secondary">{{ $latitude && $longitude ? $latitude.', '.$longitude : 'Koordinat belum tersedia' }}</div>
                            </div>
                            <button type="button" id="refreshLocationButton" class="btn btn-outline-primary">
                                <i class="fas fa-location-crosshairs"></i>
                            </button>
                        </div>
                        @error('latitude') <div class="text-danger mt-2">{{ $message }}</div> @enderror
                        @error('longitude') <div class="text-danger mt-2">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" wire:click="checkIn" @disabled($todayRecord?->check_in_at)>
                            <i class="fas fa-right-to-bracket me-2"></i>Check-in
                        </button>
                        <button type="button" class="btn btn-outline-primary" wire:click="checkOut" @disabled(! $todayRecord?->check_in_at || $todayRecord?->check_out_at)>
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
                                            <td>{{ $record->work_minutes }} menit</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-4">Belum ada riwayat absensi.</td>
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
</div>

@push('scripts')
    <script>
        (() => {
            let stream = null;

            const uploadWebp = async (blob) => {
                const file = new File([blob], `attendance-${Date.now()}.webp`, { type: 'image/webp' });
                @this.upload('attendancePhoto', file, () => {
                    @this.set('uploadProgress', 100);
                }, () => {
                    @this.set('uploadProgress', 0);
                }, (event) => {
                    const progress = event?.detail?.progress ?? event?.progress ?? event ?? 0;
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
                    @this.set('latitude', position.coords.latitude.toFixed(7));
                    @this.set('longitude', position.coords.longitude.toFixed(7));
                    @this.set('accuracy', Math.round(position.coords.accuracy));
                    @this.set('gpsStatus', `Akurasi ${Math.round(position.coords.accuracy)} meter`);
                }, () => {
                    @this.set('gpsStatus', 'Izin lokasi ditolak atau gagal dibaca');
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
                    await uploadWebp(await imageToWebp(file));
                }
            });
            document.getElementById('startCameraButton')?.addEventListener('click', async () => {
                const video = document.getElementById('attendanceCamera');
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = stream;
                video.classList.remove('d-none');
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
                canvas.toBlob(uploadWebp, 'image/webp', 0.78);
            });

            locate();
        })();
    </script>
@endpush
