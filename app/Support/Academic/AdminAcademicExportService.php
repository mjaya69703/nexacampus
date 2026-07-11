<?php

namespace App\Support\Academic;

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\Curriculum;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyProgram;
use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AdminAcademicExportService
{
    public function payload(string $resource): array
    {
        return match ($resource) {
            'academic-years' => $this->academicYears(),
            'faculties' => $this->faculties(),
            'study-programs' => $this->studyPrograms(),
            'courses' => $this->courses(),
            'curriculums' => $this->curriculums(),
            'student-registrations' => $this->studentRegistrations(),
            'academic-periods' => $this->academicPeriods(),
            'course-offerings' => $this->courseOfferings(),
            'study-plans' => $this->studyPlans(),
            'course-schedules' => $this->courseSchedules(),
            'student-grades' => $this->studentGrades(),
            'transcripts' => $this->transcripts(),
            'academic-advisor-assignments' => $this->academicAdvisorAssignments(),
            default => throw new InvalidArgumentException('Resource akademik tidak didukung.'),
        };
    }

    public function importHeaders(string $resource): array
    {
        return $this->payload($resource)['headers'];
    }

    private function academicYears(): array
    {
        return $this->build('Tahun Akademik', ['Kode', 'Nama', 'Semester', 'Mulai', 'Selesai', 'Aktif'], AcademicYear::query()
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AcademicYear $year) => [
                $year->code,
                $year->name,
                $year->semester,
                $this->date($year->start_date),
                $this->date($year->end_date),
                $this->yesNo($year->is_active),
            ]));
    }

    private function faculties(): array
    {
        return $this->build('Fakultas', ['Kode', 'Nama', 'Nama Singkat', 'Program Studi', 'Aktif'], Faculty::query()
            ->withCount('studyPrograms')
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $faculty) => [
                $faculty->code,
                $faculty->name,
                $faculty->short_name,
                $faculty->study_programs_count,
                $this->yesNo($faculty->is_active),
            ]));
    }

    private function studyPrograms(): array
    {
        return $this->build('Program Studi', ['Kode', 'Nama', 'Fakultas', 'Jenjang', 'Gelar', 'Aktif'], StudyProgram::query()
            ->with('faculty')
            ->orderBy('name')
            ->get()
            ->map(fn (StudyProgram $program) => [
                $program->code,
                $program->name,
                $program->faculty?->name,
                $program->degree,
                trim(($program->prefix_degree ? $program->prefix_degree.' ' : '').($program->suffix_degree ?? '')),
                $this->yesNo($program->is_active),
            ]));
    }

    private function courses(): array
    {
        return $this->build('Mata Kuliah', ['Kode', 'Nama', 'SKS', 'Semester', 'Requirement', 'Kategori', 'Scope', 'Aktif'], Course::query()
            ->with(['latestScope.faculty', 'latestScope.studyProgram'])
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course) => [
                $course->code,
                $course->name,
                $course->credits,
                $course->semester_recommendation,
                $course->requirement_type,
                $course->category_type,
                $this->courseScope($course),
                $this->yesNo($course->is_active),
            ]));
    }

    private function curriculums(): array
    {
        return $this->build('Kurikulum', ['Kode', 'Nama', 'Program Studi', 'Mulai', 'Selesai', 'Mata Kuliah', 'Aktif'], Curriculum::query()
            ->with('studyProgram')
            ->withCount('curriculumCourses')
            ->orderByDesc('start_year')
            ->get()
            ->map(fn (Curriculum $curriculum) => [
                $curriculum->code,
                $curriculum->name,
                $curriculum->studyProgram?->name,
                $curriculum->start_year,
                $curriculum->end_year,
                $curriculum->curriculum_courses_count,
                $this->yesNo($curriculum->is_active),
            ]));
    }

    private function studentRegistrations(): array
    {
        return $this->build('Registrasi Mahasiswa', ['NIM', 'Mahasiswa', 'Program Studi', 'Tahun Akademik', 'Semester', 'Status Registrasi', 'Status Akademik', 'Aktif'], StudentRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->latest()
            ->get()
            ->map(fn (StudentRegistration $registration) => [
                $registration->studentProfile?->nim,
                $registration->studentProfile?->user?->name,
                $registration->studentProfile?->studyProgram?->name,
                $registration->academicYear?->name,
                $registration->semester_no,
                $registration->registration_status,
                $registration->academic_status,
                $this->yesNo($registration->is_active),
            ]));
    }

    private function academicPeriods(): array
    {
        return $this->build('Periode Akademik', ['Kode', 'Nama', 'Tahun Akademik', 'Tipe', 'Mulai', 'Selesai', 'Aktif'], AcademicPeriod::query()
            ->with('academicYear')
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (AcademicPeriod $period) => [
                $period->code,
                $period->name,
                $period->academicYear?->name,
                $period->type,
                $this->date($period->start_at),
                $this->date($period->end_at),
                $this->yesNo($period->is_active),
            ]));
    }

    private function courseOfferings(): array
    {
        return $this->build('Penawaran Kelas', ['Tahun Akademik', 'Program Studi', 'Mata Kuliah', 'Kelas', 'Kode', 'Semester', 'Kapasitas', 'Mode', 'Status', 'Dosen'], CourseOffering::query()
            ->with(['academicYear', 'studyProgram', 'course'])
            ->withCount('lecturers')
            ->latest()
            ->get()
            ->map(fn (CourseOffering $offering) => [
                $offering->academicYear?->name,
                $offering->studyProgram?->name,
                ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-'),
                $offering->label,
                $offering->code,
                $offering->semester_no,
                $offering->capacity,
                $offering->delivery_mode,
                $offering->status,
                $offering->lecturers_count,
            ]));
    }

    private function studyPlans(): array
    {
        return $this->build('KRS', ['NIM', 'Mahasiswa', 'Program Studi', 'Tahun Akademik', 'Semester', 'Status', 'Mata Kuliah', 'SKS'], StudyPlan::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->withCount('details')
            ->withSum('details as total_credits', 'credits')
            ->latest()
            ->get()
            ->map(fn (StudyPlan $plan) => [
                $plan->studentProfile?->nim,
                $plan->studentProfile?->user?->name,
                $plan->studentProfile?->studyProgram?->name,
                $plan->academicYear?->name,
                $plan->semester_no,
                $plan->status,
                $plan->details_count,
                (int) ($plan->total_credits ?? 0),
            ]));
    }

    private function courseSchedules(): array
    {
        return $this->build('Jadwal Kuliah', ['Mata Kuliah', 'Dosen', 'Hari', 'Waktu', 'Ruangan', 'Tipe', 'Mode', 'Aktif'], CourseSchedule::query()
            ->with(['courseOffering.course', 'lecturerProfile.user', 'room'])
            ->latest()
            ->get()
            ->map(fn (CourseSchedule $schedule) => [
                ($schedule->courseOffering?->course?->code ?? '-').' - '.($schedule->courseOffering?->course?->name ?? '-'),
                $schedule->lecturerProfile?->user?->name ?? 'Jadwal Umum',
                $schedule->day_of_week,
                ($schedule->start_time?->format('H:i') ?? '-').' - '.($schedule->end_time?->format('H:i') ?? '-'),
                $schedule->room?->name,
                $schedule->session_type,
                $schedule->delivery_mode,
                $this->yesNo($schedule->is_active),
            ]));
    }

    private function studentGrades(): array
    {
        return $this->build('Nilai Mahasiswa', ['NIM', 'Mahasiswa', 'Program Studi', 'Tahun Akademik', 'Mata Kuliah', 'Kelas', 'Final Score', 'Nilai Huruf', 'Lifecycle', 'Status'], StudentGrade::query()
            ->with(['studyPlanDetail.studyPlan.studentProfile.user', 'studyPlanDetail.studyPlan.studentProfile.studyProgram', 'studyPlanDetail.studyPlan.academicYear', 'studyPlanDetail.courseOffering.course'])
            ->latest()
            ->get()
            ->map(fn (StudentGrade $grade) => [
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->nim,
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->user?->name,
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name,
                $grade->studyPlanDetail?->studyPlan?->academicYear?->name,
                ($grade->studyPlanDetail?->courseOffering?->course?->code ?? '-').' - '.($grade->studyPlanDetail?->courseOffering?->course?->name ?? '-'),
                $grade->studyPlanDetail?->courseOffering?->label,
                $grade->final_score,
                $grade->letter_grade,
                $grade->grade_status,
                $grade->result_status,
            ]));
    }

    private function transcripts(): array
    {
        return $this->build('Transkrip', ['NIM', 'Mahasiswa', 'Program Studi', 'Study Results', 'Transcript Entries', 'IPK Snapshot'], StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->withCount(['studyResults', 'transcriptEntries'])
            ->withMax('studyResults as latest_cumulative_gpa', 'cumulative_gpa')
            ->orderBy('nim')
            ->get()
            ->map(fn (StudentProfile $student) => [
                $student->nim,
                $student->user?->name,
                $student->studyProgram?->name,
                $student->study_results_count,
                $student->transcript_entries_count,
                $student->latest_cumulative_gpa !== null ? number_format((float) $student->latest_cumulative_gpa, 2) : '-',
            ]));
    }

    private function academicAdvisorAssignments(): array
    {
        return $this->build('Dosen Wali', ['NIM', 'Mahasiswa', 'Dosen PA', 'Tahun Akademik', 'Mulai', 'Selesai', 'Aktif'], AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'lecturerProfile.user', 'academicYear'])
            ->latest()
            ->get()
            ->map(fn (AcademicAdvisorAssignment $assignment) => [
                $assignment->studentProfile?->nim,
                $assignment->studentProfile?->user?->name,
                $assignment->lecturerProfile?->user?->name,
                $assignment->academicYear?->name,
                $this->date($assignment->start_date),
                $this->date($assignment->end_date),
                $this->yesNo($assignment->is_active),
            ]));
    }

    private function build(string $title, array $headers, Collection $rows): array
    {
        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows->values()->all(),
        ];
    }

    private function courseScope(Course $course): string
    {
        $scope = $course->latestScope;

        return match ($scope?->scope_type) {
            'global' => 'Global',
            'faculty' => 'Fakultas: '.($scope->faculty?->name ?? '-'),
            'study_program' => 'Prodi: '.($scope->studyProgram?->name ?? '-'),
            default => '-',
        };
    }

    private function yesNo(mixed $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d M Y');
        }

        return (string) $value;
    }
}
