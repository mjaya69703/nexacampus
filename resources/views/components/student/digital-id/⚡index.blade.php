<?php

use App\Models\Academic\StudentProfile;
use App\Support\Student\DigitalStudentIdService;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public ?StudentProfile $studentProfile = null;
    public array $card = [];
    public string $verificationUrl = '';
    public string $qrSvg = '';

    public function mount(DigitalStudentIdService $service): void
    {
        $this->studentProfile = auth()->user()?->studentProfile()
            ->with(['user', 'studyProgram.faculty', 'entryAcademicYear'])
            ->first();

        if (! $this->studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->verificationUrl = $service->verificationUrl($this->studentProfile);
        $this->qrSvg = $service->qrSvg($this->studentProfile, 230);

        $this->card = [
            'name' => $this->studentProfile->user?->name ?? '-',
            'nim' => $this->studentProfile->nim,
            'study_program' => $this->studentProfile->studyProgram?->name ?? '-',
            'faculty' => $this->studentProfile->studyProgram?->faculty?->name ?? '-',
            'entry_year' => $this->studentProfile->entry_year ?? $this->studentProfile->entryAcademicYear?->name ?? '-',
            'semester' => $this->studentProfile->current_semester ?? '-',
            'status' => $this->studentProfile->academic_status ?? '-',
            'active' => (bool) $this->studentProfile->is_active,
            'photo' => $this->studentProfile->user?->photo ?: asset('storage/images/profile/default.jpg'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kartu Mahasiswa',
            'pages' => 'Kartu Mahasiswa',
        ]);
    }
};
?>

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia. Hubungi administrator akademik untuk mengaktifkan akun mahasiswa.</div>
    @else
        <style>
            .student-id-shell {
                display: grid;
                grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
                gap: 1.25rem;
            }

            .student-id-card {
                position: relative;
                overflow: hidden;
                min-height: 420px;
                border-radius: 18px;
                background:
                    linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(30, 64, 175, .9)),
                    radial-gradient(circle at top right, rgba(45, 212, 191, .32), transparent 34%);
                color: #fff;
                box-shadow: 0 22px 55px rgba(15, 23, 42, .22);
            }

            .student-id-card::after {
                content: "";
                position: absolute;
                width: 280px;
                height: 280px;
                right: -90px;
                bottom: -110px;
                border: 1px solid rgba(255, 255, 255, .2);
                border-radius: 50%;
            }

            .student-id-content {
                position: relative;
                z-index: 1;
                height: 100%;
                padding: 1.5rem;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 2rem;
            }

            .student-id-avatar {
                width: 92px;
                height: 92px;
                border-radius: 18px;
                background-size: cover;
                background-position: center;
                border: 3px solid rgba(255, 255, 255, .75);
                box-shadow: 0 12px 28px rgba(0, 0, 0, .22);
            }

            .student-id-qr {
                background: #fff;
                border-radius: 16px;
                padding: .75rem;
                color: #0f172a;
                width: 100%;
                max-width: 260px;
                margin-inline: auto;
            }

            .student-id-qr svg {
                width: 100%;
                height: auto;
                display: block;
            }

            .student-id-field {
                padding: .9rem 1rem;
                border-radius: 14px;
                background: rgba(255, 255, 255, .1);
                border: 1px solid rgba(255, 255, 255, .14);
            }

            @media (max-width: 991.98px) {
                .student-id-shell {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="student-id-shell">
            <section class="student-id-card">
                <div class="student-id-content">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-uppercase fw-bold mb-2" style="letter-spacing:.08em; opacity:.78;">NexaCampus</div>
                            <h1 class="h2 mb-1">Kartu Mahasiswa</h1>
                            <div style="opacity:.82;">{{ $card['faculty'] }}</div>
                        </div>
                        <span class="badge {{ $card['active'] ? 'bg-green-lt text-green' : 'bg-red-lt text-red' }}">
                            {{ $card['active'] ? 'Aktif' : 'Tidak Aktif' }}
                        </span>
                    </div>

                    <div class="row g-4 align-items-end">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="student-id-avatar" style="background-image:url('{{ $card['photo'] }}')"></div>
                                <div>
                                    <div class="h2 mb-1">{{ $card['name'] }}</div>
                                    <div class="fs-3 fw-bold" style="opacity:.86;">{{ $card['nim'] }}</div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="student-id-field">
                                        <div class="small" style="opacity:.72;">Program Studi</div>
                                        <div class="fw-bold">{{ $card['study_program'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="student-id-field">
                                        <div class="small" style="opacity:.72;">Semester</div>
                                        <div class="fw-bold">{{ $card['semester'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="student-id-field">
                                        <div class="small" style="opacity:.72;">Status</div>
                                        <div class="fw-bold">{{ $card['status'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="student-id-qr">
                                {!! $qrSvg !!}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Verifikasi</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tautan verifikasi</label>
                        <input class="form-control" value="{{ $verificationUrl }}" readonly>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" class="btn btn-primary">
                            <i class="fas fa-shield-check me-2"></i>Buka Verifikasi
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Cetak Kartu
                        </button>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-secondary small">Angkatan</div>
                            <div class="fw-bold">{{ $card['entry_year'] }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Fakultas</div>
                            <div class="fw-bold">{{ $card['faculty'] }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    @endif
</div>
