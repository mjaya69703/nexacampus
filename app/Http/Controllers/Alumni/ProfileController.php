<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Alumni\AlumniProfile;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil alumni (portal) — baca & perbarui data profil sendiri.
 *
 * Koreksi dari Blade lama: status pekerjaan divalidasi terhadap enum dan
 * data perusahaan/jabatan dibersihkan saat alumni mengubah status menjadi
 * "belum bekerja" / "studi lanjut", sehingga profil tidak menyimpan karir
 * lama yang sudah tidak relevan.
 */
class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile()
            ->with(['studyProgram:id,name', 'faculty:id,name'])
            ->first();

        return Inertia::render('Alumni/Profile/Index', [
            'shell' => ShellProps::make($user, 'Profil', 'Profil Alumni'),
            'hasProfile' => (bool) $profile,
            'profile' => $profile ? $this->profilePayload($profile) : null,
            'completeness' => $profile?->profileCompleteness() ?? 0,
            'urls' => [
                'index' => route('alumni.profile.index'),
                'edit' => route('alumni.profile.edit'),
                'dashboard' => route('alumni.dashboard.index'),
                'jobs' => route('alumni.jobs.index'),
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $profile = $this->profileOrFail($request);

        return Inertia::render('Alumni/Profile/Edit', [
            'shell' => ShellProps::make($request->user(), 'Profil', 'Edit Profil Alumni'),
            'profile' => [
                'phone' => $profile->phone ?? '',
                'address' => $profile->address ?? '',
                'current_city' => $profile->current_city ?? '',
                'current_province' => $profile->current_province ?? '',
                'employment_status' => $profile->employment_status ?: EmploymentStatus::Unemployed->value,
                'employer_name' => $profile->employer_name ?? '',
                'job_title' => $profile->job_title ?? '',
                'job_industry' => $profile->job_industry ?? '',
                'linkedin_url' => $profile->linkedin_url ?? '',
            ],
            'employmentStatusOptions' => EmploymentStatus::options(),
            'urls' => [
                'index' => route('alumni.profile.index'),
                'update' => route('alumni.profile.update'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $this->profileOrFail($request);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'current_city' => ['nullable', 'string', 'max:100'],
            'current_province' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['required', Rule::in(array_keys(EmploymentStatus::options()))],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'job_industry' => ['nullable', 'string', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
        ]);

        if (! in_array($validated['employment_status'], [
            EmploymentStatus::Working->value,
            EmploymentStatus::Entrepreneur->value,
        ], true)) {
            $validated['employer_name'] = null;
            $validated['job_title'] = null;
            $validated['job_industry'] = null;
        }

        $validated['updated_by'] = $request->user()->id;
        $profile->update($validated);

        return redirect()
            ->route('alumni.profile.index')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    private function profileOrFail(Request $request): AlumniProfile
    {
        $profile = $request->user()->alumniProfile()->first();

        abort_unless($profile, 404, 'Profil alumni belum tersedia.');

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(AlumniProfile $profile): array
    {
        return [
            'nim' => $profile->nim,
            'fullName' => $profile->full_name,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'birthDate' => $profile->birth_date?->format('d M Y'),
            'gender' => $profile->gender,
            'address' => $profile->address,
            'currentCity' => $profile->current_city,
            'currentProvince' => $profile->current_province,
            'faculty' => $profile->faculty?->name,
            'studyProgram' => $profile->studyProgram?->name,
            'graduationYear' => $profile->graduation_year,
            'graduationDate' => $profile->graduation_date?->format('d M Y'),
            'gpa' => $profile->gpa !== null ? number_format((float) $profile->gpa, 2) : null,
            'employmentStatus' => $profile->employment_status,
            'employmentStatusLabel' => EmploymentStatus::tryFrom((string) $profile->employment_status)?->label()
                ?? $profile->employment_status,
            'employerName' => $profile->employer_name,
            'jobTitle' => $profile->job_title,
            'jobIndustry' => $profile->job_industry,
            'linkedinUrl' => $profile->linkedin_url,
            'isActive' => (bool) $profile->is_active,
        ];
    }
}
