<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentRegistration;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrasi semester mahasiswa.
 *
 * Koreksi dari Blade lama: registrasi berstatus Cancelled tidak lagi
 * dianggap sebagai registrasi aktif, sehingga mahasiswa yang membatalkan
 * pengajuan tetap bisa menyusun registrasi baru pada tahun akademik yang
 * sama. Karena skema hanya mengizinkan satu baris per mahasiswa per tahun
 * akademik, baris yang dibatalkan dipakai ulang (diaktifkan kembali) alih-
 * alih membuat baris kedua yang akan melanggar unique constraint.
 */
class StudentRegistrationController extends Controller
{
    private const ACADEMIC_STATUSES = ['Aktif', 'Nonaktif', 'Lulus', 'Drop Out', 'Keluar'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with(['studyProgram.faculty'])->first();

        if (! $profile) {
            return Inertia::render('Student/Registration/Index', [
                'shell' => ShellProps::make($user, 'Academic', 'Registrasi Semester'),
                'hasProfile' => false,
                'hasActiveYear' => false,
                'student' => null,
                'year' => null,
                'period' => ['canRegister' => false, 'name' => null, 'range' => null],
                'form' => ['semester_no' => null, 'academic_status' => 'Aktif', 'notes' => ''],
                'current' => null,
                'history' => [],
                'academicStatusOptions' => self::ACADEMIC_STATUSES,
                'urls' => $this->urls(),
            ]);
        }

        $activeYear = $this->activeYear();
        $current = $activeYear
            ? StudentRegistration::query()
                ->where('student_profile_id', $profile->id)
                ->where('academic_year_id', $activeYear->id)
                ->where('registration_status', '!=', 'Cancelled')
                ->latest('id')
                ->first()
            : null;

        $period = $activeYear ? $this->activeRegistrationPeriod($activeYear) : null;

        $history = StudentRegistration::query()
            ->with('academicYear')
            ->where('student_profile_id', $profile->id)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (StudentRegistration $registration) => [
                'academicYear' => $registration->academicYear?->name ?? '-',
                'semesterNo' => $registration->semester_no,
                'registrationStatus' => $registration->registration_status,
                'academicStatus' => $registration->academic_status,
                'registrationDate' => $registration->registration_date?->format('d M Y') ?? '-',
                'submittedAt' => $registration->submitted_at?->format('d M Y H:i') ?? '-',
            ])
            ->all();

        return Inertia::render('Student/Registration/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Registrasi Semester'),
            'hasProfile' => true,
            'hasActiveYear' => (bool) $activeYear,
            'student' => [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'semester' => $profile->current_semester,
            ],
            'year' => $activeYear ? ['id' => $activeYear->id, 'name' => $activeYear->name] : null,
            'period' => [
                'canRegister' => (bool) $period,
                'name' => $period?->name,
                'range' => $period
                    ? $period->start_at?->format('d M Y H:i').' - '.$period->end_at?->format('d M Y H:i')
                    : null,
            ],
            'form' => [
                'semester_no' => $current?->semester_no ?? $profile->current_semester,
                'academic_status' => $current?->academic_status ?? $profile->academic_status ?? 'Aktif',
                'notes' => $current?->notes ?? '',
            ],
            'current' => $current ? [
                'id' => $current->id,
                'status' => $current->registration_status,
                'academicStatus' => $current->academic_status,
                'submittedAt' => $current->submitted_at?->format('d M Y H:i'),
                'approvedAt' => $current->approved_at?->format('d M Y H:i'),
                'canEdit' => in_array($current->registration_status, ['Draft', 'Rejected'], true),
            ] : null,
            'history' => $history,
            'academicStatusOptions' => self::ACADEMIC_STATUSES,
            'urls' => $this->urls(),
        ]);
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        return $this->upsert($request, 'Draft');
    }

    public function submit(Request $request): RedirectResponse
    {
        return $this->upsert($request, 'Submitted');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();

        if (! $profile) {
            return back()->with('error', 'Profil mahasiswa belum tersedia.');
        }

        $activeYear = $this->activeYear();

        if (! $activeYear) {
            return back()->with('error', 'Tahun akademik aktif belum tersedia.');
        }

        $registration = StudentRegistration::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $activeYear->id)
            ->where('registration_status', '!=', 'Cancelled')
            ->latest('id')
            ->first();

        if (! $registration) {
            return back()->with('error', 'Tidak ada registrasi aktif yang dapat dibatalkan.');
        }

        if (! in_array($registration->registration_status, ['Draft', 'Submitted'], true)) {
            return back()->with('error', 'Status registrasi saat ini tidak dapat dibatalkan.');
        }

        $registration->update([
            'registration_status' => 'Cancelled',
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('student.registration.index')
            ->with('success', 'Registrasi semester berhasil dibatalkan.');
    }

    private function upsert(Request $request, string $status): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        if (! $profile) {
            return back()->with('error', 'Profil mahasiswa belum tersedia.');
        }

        $activeYear = $this->activeYear();

        if (! $activeYear) {
            return back()->with('error', 'Tahun akademik aktif belum tersedia.');
        }

        // Ambil baris terakhir apa pun statusnya: baris Cancelled tetap
        // menempati slot unik (student_profile_id + academic_year_id) dan
        // harus dipakai ulang, bukan diabaikan.
        $latest = StudentRegistration::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $activeYear->id)
            ->latest('id')
            ->first();

        $current = $latest && $latest->registration_status !== 'Cancelled' ? $latest : null;

        if ($current && ! in_array($current->registration_status, ['Draft', 'Rejected'], true)) {
            return back()->with('error', 'Registrasi saat ini tidak dapat diubah.');
        }

        if ($status === 'Submitted' && ! $this->activeRegistrationPeriod($activeYear)) {
            return back()->with('error', 'Periode registrasi semester belum aktif.');
        }

        $validated = $request->validate([
            'semester_no' => ['nullable', 'integer', 'min:1', 'max:14'],
            'academic_status' => ['required', Rule::in(self::ACADEMIC_STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'academic_status.in' => 'Pengajuan cuti akademik dilakukan melalui menu Layanan > Cuti Akademik.',
        ]);

        $payload = [
            'student_profile_id' => $profile->id,
            'academic_year_id' => $activeYear->id,
            'semester_no' => $validated['semester_no'] ?? null,
            'academic_status' => $validated['academic_status'],
            'registration_status' => $status,
            'registration_date' => Carbon::today(),
            'submitted_at' => $status === 'Submitted' ? now() : null,
            'notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null,
            'is_active' => true,
            'updated_by' => $user->id,
        ];

        if ($latest) {
            // Termasuk baris Cancelled: aktifkan kembali pada tahun akademik
            // yang sama agar unique constraint tetap terjaga.
            $latest->update($payload);
        } else {
            StudentRegistration::create($payload + ['created_by' => $user->id]);
        }

        return redirect()->route('student.registration.index')->with(
            'success',
            $status === 'Draft'
                ? 'Draft registrasi semester berhasil disimpan.'
                : 'Registrasi semester berhasil diajukan.',
        );
    }

    private function activeYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();
    }

    private function activeRegistrationPeriod(AcademicYear $year): ?AcademicPeriod
    {
        $now = now();

        return AcademicPeriod::query()
            ->where('academic_year_id', $year->id)
            ->where('type', 'Student Registration')
            ->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('student.registration.index'),
            'draft' => route('student.registration.draft'),
            'submit' => route('student.registration.submit'),
            'cancel' => route('student.registration.cancel'),
            'leaves' => route('student.student-services.leaves'),
        ];
    }
}
