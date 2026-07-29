<?php

use Livewire\Component;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\AcademicYear;

new class extends Component
{
    public array $schedules = [];
    public ?AcademicYear $activeYear = null;

    public function mount(): void
    {
        $this->activeYear = AcademicYear::where('is_active', true)->first();

        // Get schedules regardless of active year strict check, to ensure data appears 
        // as long as the schedule itself is active.
        $this->schedules = CourseSchedule::with(['courseOffering.course', 'courseOffering.studyProgram', 'room', 'lecturerProfile'])
            ->where('is_active', true)
            ->get()
            ->groupBy('day_of_week')
                ->map(fn($daySchedules) => $daySchedules->map(fn($s) => [
                    'id'           => $s->id,
                    'course_code'  => $s->courseOffering?->course?->code ?? '-',
                    'course_name'  => $s->courseOffering?->course?->name ?? 'Unknown Course',
                    'program'      => $s->courseOffering?->studyProgram?->name ?? '-',
                    'lecturer'     => $s->lecturerProfile?->full_name ?? 'TBA',
                    'start_time'   => $s->start_time?->format('H:i'),
                    'end_time'     => $s->end_time?->format('H:i'),
                    'room'         => $s->room?->name ?? 'Online/TBA',
                    'delivery_mode'=> $s->delivery_mode ?? 'offline',
                    'session_type' => $s->session_type ?? 'lecture',
                ])->sortBy('start_time')->values()->toArray())
                ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Akademik',
            'pages' => 'Jadwal Kuliah Umum',
        ]);
    }
};
?>

@php
$daysMap = [
    '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', 
    '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu'
];
$deliveryColors = [
    'offline' => ['label' => 'Tatap Muka', 'class' => 'bg-primary-lt text-primary', 'icon' => 'fa-users'],
    'online'  => ['label' => 'Daring',     'class' => 'bg-success-lt text-success', 'icon' => 'fa-laptop'],
    'hybrid'  => ['label' => 'Hybrid',     'class' => 'bg-warning-lt text-warning', 'icon' => 'fa-rotate'],
];
@endphp

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Informasi Perkuliahan</span>
                            </div>
                            <h1 class="admission-title mb-3">Jadwal Kuliah<br><span style="opacity:.8">Tahun {{ $activeYear?->name ?? '-' }}</span></h1>
                            <p class="admission-subtitle mb-0">
                                Daftar jadwal perkuliahan umum per hari. Mahasiswa diwajibkan untuk memeriksa KRS masing-masing di portal akademik untuk jadwal spesifik.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Total Sesi Aktif</div>
                                    <div class="h3 text-white mb-0 fw-bolder">{{ collect($schedules)->flatten(1)->count() }} Kelas</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-calendar-check text-white-50"></i>
                                    <small class="text-white-50">Sesuai dengan periode akademik berjalan.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($schedules) > 0)
                <div class="row g-4">
                    @foreach($daysMap as $dayNum => $dayName)
                        @if(isset($schedules[$dayNum]) && count($schedules[$dayNum]) > 0)
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0;">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <h3 class="fw-bolder text-body mb-0" style="font-size:1.3rem;">Hari {{ $dayName }}</h3>
                                <div class="flex-fill border-bottom border-2 ms-2 opacity-25"></div>
                            </div>

                            <div class="row g-3">
                                @foreach($schedules[$dayNum] as $s)
                                @php $dlv = $deliveryColors[$s['delivery_mode']] ?? ['label'=>$s['delivery_mode'],'class'=>'bg-secondary-lt','icon'=>'fa-chalkboard']; @endphp
                                <div class="col-lg-6">
                                    <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100" style="border-left:4px solid #3b82f6 !important;">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-auto border-end pe-3 text-center" style="min-width:100px;">
                                                <div class="fw-black text-primary" style="font-size:1.3rem;">{{ $s['start_time'] }}</div>
                                                <div class="text-muted fw-semibold" style="font-size:.85rem;">{{ $s['end_time'] }}</div>
                                                <div class="badge {{ $dlv['class'] }} mt-2 px-2" style="font-size:.65rem;"><i class="fas {{ $dlv['icon'] }} me-1"></i>{{ $dlv['label'] }}</div>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <div class="badge bg-light text-muted fw-semibold border">{{ $s['course_code'] }}</div>
                                                    <div class="badge bg-light text-muted fw-semibold border">{{ $s['program'] }}</div>
                                                </div>
                                                <h4 class="fw-bolder text-body mb-1" style="font-size:1.05rem;line-height:1.3;">{{ $s['course_name'] }}</h4>
                                                <div class="text-muted mb-2" style="font-size:.85rem;"><i class="fas fa-user-tie me-1"></i>{{ $s['lecturer'] }}</div>
                                                <div class="text-dark fw-semibold" style="font-size:.85rem;"><i class="fas fa-location-dot text-danger me-1"></i>{{ $s['room'] }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-calendar-xmark"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Jadwal Belum Tersedia</h4>
                    <p class="text-muted mb-0">Jadwal perkuliahan untuk tahun ajaran aktif belum dipublikasikan atau belum ada sesi yang dijadwalkan.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
