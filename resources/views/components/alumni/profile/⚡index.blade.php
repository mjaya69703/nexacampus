<?php

use App\Enums\EmploymentStatus;
use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public ?AlumniProfile $profile = null;
    public int $profileCompleteness = 0;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->profile = AlumniProfile::query()
            ->where('user_id', $user->id)
            ->with(['studyProgram', 'faculty'])
            ->first();

        if (! $this->profile) {
            return;
        }

        $this->hasProfile = true;
        $this->profileCompleteness = $this->profile->profileCompleteness();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Profil',
            'pages' => 'Profil Alumni',
        ]);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">Profil Saya</h1>
                        <div style="opacity: 0.9;">Lihat dan kelola data profil alumni kamu.</div>
                    </div>
                </div>
                @if ($hasProfile)
                    <a href="{{ route('alumni.profile.edit') }}" class="btn btn-light fw-semibold">
                        <i class="fas fa-pen me-1"></i> Edit Profil
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $hasProfile)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <h3>Profil Alumni Belum Tersedia</h3>
                <p class="text-muted">Hubungi administrator untuk mengaktifkan profil alumni kamu.</p>
            </div>
        </div>
    @else
        {{-- Profile Completeness --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Kelengkapan Profil</span>
                    <span class="badge bg-primary-lt text-primary">{{ $profileCompleteness }}%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $profileCompleteness }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                </div>
                @if ($profileCompleteness < 100)
                    <div class="text-muted small mt-2">Lengkapi data phone, kota, status kerja, dan LinkedIn untuk profil 100%.</div>
                @endif
            </div>
        </div>

        <div class="row g-4">
            {{-- Personal Information --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-user me-2 text-primary"></i>Data Pribadi</h3>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 40%;">NIM</td>
                                <td class="fw-semibold">{{ $profile->nim }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nama Lengkap</td>
                                <td class="fw-semibold">{{ $profile->full_name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Email</td>
                                <td>{{ $profile->email }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">No. Telepon</td>
                                <td>{{ $profile->phone ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal Lahir</td>
                                <td>{{ $profile->birth_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jenis Kelamin</td>
                                <td>{{ $profile->gender ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Alamat</td>
                                <td>{{ $profile->address ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Kota</td>
                                <td>{{ $profile->current_city ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Provinsi</td>
                                <td>{{ $profile->current_province ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Academic & Career --}}
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-graduation-cap me-2 text-primary"></i>Data Akademik</h3>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 40%;">Fakultas</td>
                                <td class="fw-semibold">{{ $profile->faculty?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Program Studi</td>
                                <td class="fw-semibold">{{ $profile->studyProgram?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tahun Lulus</td>
                                <td>{{ $profile->graduation_year }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal Lulus</td>
                                <td>{{ $profile->graduation_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">IPK</td>
                                <td>{{ $profile->gpa }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0"><i class="fas fa-briefcase me-2 text-primary"></i>Data Karir</h3>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 40%;">Status Kerja</td>
                                <td>
                                    @php $es = EmploymentStatus::tryFrom($profile->employment_status); @endphp
                                    <span class="badge bg-primary-lt text-primary">{{ $es ? $es->label() : '-' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Perusahaan</td>
                                <td>{{ $profile->employer_name ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jabatan</td>
                                <td>{{ $profile->job_title ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Industri</td>
                                <td>{{ $profile->job_industry ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">LinkedIn</td>
                                <td>
                                    @if ($profile->linkedin_url)
                                        <a href="{{ $profile->linkedin_url }}" target="_blank" class="text-primary">{{ $profile->linkedin_url }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
