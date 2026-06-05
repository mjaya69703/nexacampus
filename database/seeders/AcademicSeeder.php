<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentGrade;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\Course;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CoursePrerequisite;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\CourseScope;
use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentAdvisorNote;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Models\Campus\Building;
use App\Models\Campus\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AcademicSeeder extends Seeder
{
    private User $admin;
    private AcademicYear $activeYear;
    private Collection $faculties;
    private Collection $programs;
    private Collection $courses;
    private Collection $curriculums;
    private Collection $rooms;
    private Collection $lecturers;
    private Collection $students;
    private Collection $offerings;

    public function run(): void
    {
        $this->admin = $this->seedAdmin();
        $this->activeYear = $this->seedAcademicCalendar();
        $this->faculties = $this->seedFaculties();
        $this->programs = $this->seedStudyPrograms();
        $this->courses = $this->seedCourses();
        $this->curriculums = $this->seedCurriculums();
        $this->rooms = $this->seedRooms();
        $this->lecturers = $this->seedLecturers();
        $this->students = $this->seedStudents();
        $this->offerings = $this->seedCourseOfferings();

        $studyPlans = $this->seedRegistrationsAndStudyPlans();

        $this->seedAttendance($studyPlans);
        $this->seedGrades($studyPlans);
        $this->seedLearningContent($studyPlans);
        $this->seedAcademicAdvising();
    }

    private function seedAdmin(): User
    {
        $admin = $this->ensureUser(
            email: 'superuser@example.com',
            firstName: 'SuperUser',
            lastName: 'NexaCampus',
            username: 'superuser',
            phone: '0800000001',
            code: 'USR-SUPER',
            password: 'admin123',
        );

        $this->assignRoleIfExists($admin, 'superuser');

        return $admin;
    }

    private function seedAcademicCalendar(): AcademicYear
    {
        $year = AcademicYear::updateOrCreate(
            ['code' => '2025G'],
            [
                'name' => '2025/2026 Ganjil',
                'semester' => 'Ganjil',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->startOfMonth()->addMonths(5)->toDateString(),
                'is_active' => true,
                'desc' => 'Tahun akademik aktif untuk semester ganjil.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        );

        foreach ([
            ['type' => 'Student Registration', 'name' => 'Registrasi Mahasiswa Ganjil', 'code' => 'REG-2025G', 'start' => -14, 'end' => 30],
            ['type' => 'Study Plan', 'name' => 'KRS Mahasiswa Ganjil', 'code' => 'KRS-2025G', 'start' => -10, 'end' => 25],
            ['type' => 'Custom', 'name' => 'Perkuliahan Semester Ganjil', 'code' => 'KUL-2025G', 'start' => -3, 'end' => 120],
            ['type' => 'Grading', 'name' => 'Input Nilai Semester Ganjil', 'code' => 'NILAI-2025G', 'start' => 95, 'end' => 135],
        ] as $period) {
            AcademicPeriod::updateOrCreate(
                [
                    'academic_year_id' => $year->id,
                    'type' => $period['type'],
                    'name' => $period['name'],
                ],
                [
                    'code' => $period['code'],
                    'start_at' => now()->addDays($period['start']),
                    'end_at' => now()->addDays($period['end']),
                    'is_active' => true,
                    'desc' => 'Periode akademik aktif.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );
        }

        return $year;
    }

    private function seedFaculties(): Collection
    {
        return collect([
            ['code' => 'FST', 'name' => 'Fakultas Sains dan Teknologi', 'short_name' => 'FST'],
            ['code' => 'FEB', 'name' => 'Fakultas Ekonomi dan Bisnis', 'short_name' => 'FEB'],
        ])->map(fn (array $row) => Faculty::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'is_active' => true,
                'desc' => 'Fakultas aktif dalam struktur akademik.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        ))->keyBy('code');
    }

    private function seedStudyPrograms(): Collection
    {
        return collect([
            ['code' => 'TI', 'faculty' => 'FST', 'name' => 'Teknik Informatika', 'short_name' => 'Informatika', 'degree' => 'S1', 'prefix' => 'S.T.'],
            ['code' => 'SI', 'faculty' => 'FST', 'name' => 'Sistem Informasi', 'short_name' => 'Sistem Informasi', 'degree' => 'S1', 'prefix' => 'S.Kom.'],
            ['code' => 'MN', 'faculty' => 'FEB', 'name' => 'Manajemen', 'short_name' => 'Manajemen', 'degree' => 'S1', 'prefix' => 'S.M.'],
        ])->map(fn (array $row) => StudyProgram::updateOrCreate(
            ['code' => $row['code']],
            [
                'faculty_id' => $this->faculties[$row['faculty']]->id,
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'degree' => $row['degree'],
                'prefix_degree' => $row['prefix'],
                'suffix_degree' => null,
                'is_active' => true,
                'desc' => 'Program studi aktif.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        ))->keyBy('code');
    }

    private function seedCourses(): Collection
    {
        $courseRows = [
            ['code' => 'TI101', 'program' => 'TI', 'name' => 'Algoritma dan Pemrograman', 'short' => 'Algoritma', 'credits' => 3, 'semester' => 1],
            ['code' => 'TI102', 'program' => 'TI', 'name' => 'Struktur Data', 'short' => 'Struktur Data', 'credits' => 3, 'semester' => 2],
            ['code' => 'TI103', 'program' => 'TI', 'name' => 'Basis Data', 'short' => 'Basis Data', 'credits' => 3, 'semester' => 2],
            ['code' => 'TI201', 'program' => 'TI', 'name' => 'Pemrograman Web', 'short' => 'Web', 'credits' => 3, 'semester' => 3],
            ['code' => 'TI202', 'program' => 'TI', 'name' => 'Kecerdasan Artifisial', 'short' => 'AI', 'credits' => 3, 'semester' => 5],
            ['code' => 'SI101', 'program' => 'SI', 'name' => 'Pengantar Sistem Informasi', 'short' => 'PSI', 'credits' => 3, 'semester' => 1],
            ['code' => 'SI102', 'program' => 'SI', 'name' => 'Analisis Proses Bisnis', 'short' => 'APB', 'credits' => 3, 'semester' => 2],
            ['code' => 'SI201', 'program' => 'SI', 'name' => 'Manajemen Proyek TI', 'short' => 'MPTI', 'credits' => 3, 'semester' => 4],
            ['code' => 'MN101', 'program' => 'MN', 'name' => 'Pengantar Manajemen', 'short' => 'Manajemen', 'credits' => 3, 'semester' => 1],
            ['code' => 'MN102', 'program' => 'MN', 'name' => 'Akuntansi Dasar', 'short' => 'Akuntansi', 'credits' => 3, 'semester' => 1],
        ];

        $courses = collect($courseRows)->map(function (array $row) {
            $course = Course::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'short_name' => $row['short'],
                    'credits' => $row['credits'],
                    'semester_recommendation' => $row['semester'],
                    'requirement_type' => 'Wajib',
                    'category_type' => 'Keilmuan',
                    'is_active' => true,
                    'desc' => 'Mata kuliah aktif sesuai kurikulum program studi.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            CourseScope::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'scope_type' => 'study_program',
                    'scope_id' => $this->programs[$row['program']]->id,
                ],
                ['created_by' => $this->admin->id],
            );

            $course->setAttribute('seed_program_code', $row['program']);

            return $course;
        })->keyBy('code');

        CoursePrerequisite::updateOrCreate(
            ['course_id' => $courses['TI102']->id, 'prerequisite_course_id' => $courses['TI101']->id],
            ['created_by' => $this->admin->id],
        );

        CoursePrerequisite::updateOrCreate(
            ['course_id' => $courses['TI201']->id, 'prerequisite_course_id' => $courses['TI102']->id],
            ['created_by' => $this->admin->id],
        );

        return $courses;
    }

    private function seedCurriculums(): Collection
    {
        return $this->programs->map(function (StudyProgram $program) {
            $curriculum = Curriculum::updateOrCreate(
                ['study_program_id' => $program->id, 'code' => $program->code.'-2025'],
                [
                    'name' => 'Kurikulum 2025 '.$program->short_name,
                    'code' => $program->code.'-2025',
                    'start_year' => 2025,
                    'end_year' => 2029,
                    'is_active' => true,
                    'desc' => 'Kurikulum aktif program studi.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            $this->courses
                ->filter(fn (Course $course) => $course->getAttribute('seed_program_code') === $program->code)
                ->values()
                ->each(function (Course $course, int $index) use ($curriculum) {
                    CurriculumCourse::updateOrCreate(
                        ['curriculum_id' => $curriculum->id, 'course_id' => $course->id],
                        [
                            'semester_no' => $course->semester_recommendation ?: ($index + 1),
                            'is_required' => true,
                            'sort_order' => $index + 1,
                            'credits_override' => $course->credits,
                            'is_active' => true,
                            'created_by' => $this->admin->id,
                            'updated_by' => $this->admin->id,
                        ],
                    );
                });

            return $curriculum;
        })->keyBy(fn (Curriculum $curriculum) => $curriculum->studyProgram?->code ?? $curriculum->code);
    }

    private function seedRooms(): Collection
    {
        $buildings = collect([
            ['code' => 'GDG-A', 'name' => 'Gedung A', 'address' => 'Jl. Kampus No. 1', 'floor_count' => 4],
            ['code' => 'GDG-B', 'name' => 'Gedung B', 'address' => 'Jl. Kampus No. 2', 'floor_count' => 5],
        ])->map(fn (array $row) => Building::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'address' => $row['address'],
                'floor_count' => $row['floor_count'],
                'is_active' => true,
                'desc' => 'Gedung perkuliahan aktif.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        ))->keyBy('code');

        return collect([
            ['code' => 'A-101', 'building' => 'GDG-A', 'name' => 'Ruang 101', 'floor' => '1', 'capacity' => 40, 'type' => 'Classroom'],
            ['code' => 'A-203', 'building' => 'GDG-A', 'name' => 'Laboratorium Komputer 203', 'floor' => '2', 'capacity' => 32, 'type' => 'Laboratory'],
            ['code' => 'B-301', 'building' => 'GDG-B', 'name' => 'Ruang Diskusi 301', 'floor' => '3', 'capacity' => 35, 'type' => 'Classroom'],
            ['code' => 'B-401', 'building' => 'GDG-B', 'name' => 'Ruang Seminar 401', 'floor' => '4', 'capacity' => 60, 'type' => 'Auditorium'],
        ])->map(fn (array $row) => Room::updateOrCreate(
            ['code' => $row['code']],
            [
                'building_id' => $buildings[$row['building']]->id,
                'name' => $row['name'],
                'floor' => $row['floor'],
                'capacity' => $row['capacity'],
                'type' => $row['type'],
                'is_active' => true,
                'desc' => 'Ruang aktif untuk kegiatan akademik.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        ))->keyBy('code');
    }

    private function seedLecturers(): Collection
    {
        return collect([
            ['email' => 'lecturer@example.com', 'first' => 'Dosen', 'last' => 'Utama', 'username' => 'lecturer', 'phone' => '0800000101', 'code' => 'USR-LEC-001', 'program' => 'TI', 'nidn' => '1100110011'],
            ['email' => 'lecturer.algoritma@example.com', 'first' => 'Ari', 'last' => 'Pranata', 'username' => 'ari.pranata', 'phone' => '0800000102', 'code' => 'USR-LEC-002', 'program' => 'TI', 'nidn' => '1100110012'],
            ['email' => 'lecturer.data@example.com', 'first' => 'Nadia', 'last' => 'Lestari', 'username' => 'nadia.lestari', 'phone' => '0800000103', 'code' => 'USR-LEC-003', 'program' => 'TI', 'nidn' => '1100110013'],
            ['email' => 'lecturer.si@example.com', 'first' => 'Raka', 'last' => 'Mahendra', 'username' => 'raka.mahendra', 'phone' => '0800000104', 'code' => 'USR-LEC-004', 'program' => 'SI', 'nidn' => '1100110014'],
            ['email' => 'lecturer.mn@example.com', 'first' => 'Maya', 'last' => 'Permata', 'username' => 'maya.permata', 'phone' => '0800000105', 'code' => 'USR-LEC-005', 'program' => 'MN', 'nidn' => '1100110015'],
        ])->map(function (array $row) {
            $user = $this->ensureUser($row['email'], $row['first'], $row['last'], $row['username'], $row['phone'], $row['code'], 'lecturer123');
            $this->assignRoleIfExists($user, 'lecturer');
            $program = $this->programs[$row['program']];

            return LecturerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'faculty_id' => $program->faculty_id,
                    'study_program_id' => $program->id,
                    'nidn' => $row['nidn'],
                    'employment_status' => 'Tetap',
                    'join_date' => now()->subYears(3)->subMonths((int) substr($row['nidn'], -1))->toDateString(),
                    'is_active' => true,
                    'desc' => 'Dosen aktif program studi.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );
        })->keyBy(fn (LecturerProfile $profile) => $profile->user?->email);
    }

    private function seedStudents(): Collection
    {
        $students = [
            ['email' => 'student@example.com', 'first' => 'Mahasiswa', 'last' => 'Nexa', 'username' => 'student', 'phone' => '0800000201', 'code' => 'USR-STU-001', 'program' => 'TI', 'nim' => '20250001', 'semester' => 1],
            ['email' => 'student.aktif@example.com', 'first' => 'Rania', 'last' => 'Putri', 'username' => 'rania.putri', 'phone' => '0800000202', 'code' => 'USR-STU-002', 'program' => 'TI', 'nim' => '20250002', 'semester' => 3],
            ['email' => 'student.yudisium@example.com', 'first' => 'Bagas', 'last' => 'Pratama', 'username' => 'bagas.pratama', 'phone' => '0800000203', 'code' => 'USR-STU-003', 'program' => 'TI', 'nim' => '20220015', 'semester' => 8],
            ['email' => 'student.ti.04@example.com', 'first' => 'Citra', 'last' => 'Anjani', 'username' => 'citra.anjani', 'phone' => '0800000204', 'code' => 'USR-STU-004', 'program' => 'TI', 'nim' => '20250004', 'semester' => 1],
            ['email' => 'student.ti.05@example.com', 'first' => 'Dimas', 'last' => 'Saputra', 'username' => 'dimas.saputra', 'phone' => '0800000205', 'code' => 'USR-STU-005', 'program' => 'TI', 'nim' => '20250005', 'semester' => 1],
            ['email' => 'student.ti.06@example.com', 'first' => 'Ela', 'last' => 'Kurnia', 'username' => 'ela.kurnia', 'phone' => '0800000206', 'code' => 'USR-STU-006', 'program' => 'TI', 'nim' => '20250006', 'semester' => 1],
            ['email' => 'student.ti.07@example.com', 'first' => 'Fajar', 'last' => 'Ramadhan', 'username' => 'fajar.ramadhan', 'phone' => '0800000207', 'code' => 'USR-STU-007', 'program' => 'TI', 'nim' => '20250007', 'semester' => 1],
            ['email' => 'student.si.01@example.com', 'first' => 'Gita', 'last' => 'Aulia', 'username' => 'gita.aulia', 'phone' => '0800000208', 'code' => 'USR-STU-008', 'program' => 'SI', 'nim' => '20251001', 'semester' => 1],
            ['email' => 'student.si.02@example.com', 'first' => 'Haris', 'last' => 'Nugroho', 'username' => 'haris.nugroho', 'phone' => '0800000209', 'code' => 'USR-STU-009', 'program' => 'SI', 'nim' => '20251002', 'semester' => 1],
            ['email' => 'student.si.03@example.com', 'first' => 'Indah', 'last' => 'Sari', 'username' => 'indah.sari', 'phone' => '0800000210', 'code' => 'USR-STU-010', 'program' => 'SI', 'nim' => '20251003', 'semester' => 1],
            ['email' => 'student.mn.01@example.com', 'first' => 'Joko', 'last' => 'Santoso', 'username' => 'joko.santoso', 'phone' => '0800000211', 'code' => 'USR-STU-011', 'program' => 'MN', 'nim' => '20252001', 'semester' => 1],
            ['email' => 'student.mn.02@example.com', 'first' => 'Kirana', 'last' => 'Wulandari', 'username' => 'kirana.wulandari', 'phone' => '0800000212', 'code' => 'USR-STU-012', 'program' => 'MN', 'nim' => '20252002', 'semester' => 1],
        ];

        return collect($students)->map(function (array $row) {
            $user = $this->ensureUser($row['email'], $row['first'], $row['last'], $row['username'], $row['phone'], $row['code'], 'student123');
            $this->assignRoleIfExists($user, 'student');
            $program = $this->programs[$row['program']];

            return StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'study_program_id' => $program->id,
                    'entry_academic_year_id' => $this->activeYear->id,
                    'nim' => $row['nim'],
                    'entry_year' => (int) substr($row['nim'], 0, 4),
                    'academic_status' => 'Aktif',
                    'entry_date' => now()->subMonths(max(1, $row['semester']) * 5)->toDateString(),
                    'current_semester' => $row['semester'],
                    'is_active' => true,
                    'desc' => 'Mahasiswa aktif.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );
        })->keyBy('nim');
    }

    private function seedCourseOfferings(): Collection
    {
        $rows = [
            ['course' => 'TI101', 'program' => 'TI', 'label' => 'Reguler A', 'lecturer' => 'lecturer@example.com', 'day' => 'Monday', 'start' => '08:00', 'end' => '10:00', 'room' => 'A-101', 'status' => 'Open'],
            ['course' => 'TI101', 'program' => 'TI', 'label' => 'Reguler B', 'lecturer' => 'lecturer.algoritma@example.com', 'day' => 'Tuesday', 'start' => '10:00', 'end' => '12:00', 'room' => 'A-203', 'status' => 'Open'],
            ['course' => 'TI102', 'program' => 'TI', 'label' => 'Reguler A', 'lecturer' => 'lecturer.data@example.com', 'day' => 'Wednesday', 'start' => '08:00', 'end' => '10:00', 'room' => 'A-203', 'status' => 'Open'],
            ['course' => 'TI103', 'program' => 'TI', 'label' => 'Reguler A', 'lecturer' => null, 'day' => null, 'start' => null, 'end' => null, 'room' => null, 'status' => 'Open'],
            ['course' => 'TI201', 'program' => 'TI', 'label' => 'Reguler A', 'lecturer' => 'lecturer@example.com', 'day' => 'Thursday', 'start' => '13:00', 'end' => '15:00', 'room' => 'B-301', 'status' => 'Open'],
            ['course' => 'TI202', 'program' => 'TI', 'label' => 'Reguler A', 'lecturer' => 'lecturer.data@example.com', 'day' => null, 'start' => null, 'end' => null, 'room' => null, 'status' => 'Open'],
            ['course' => 'SI101', 'program' => 'SI', 'label' => 'Reguler A', 'lecturer' => 'lecturer.si@example.com', 'day' => 'Monday', 'start' => '13:00', 'end' => '15:00', 'room' => 'B-301', 'status' => 'Open'],
            ['course' => 'SI102', 'program' => 'SI', 'label' => 'Reguler A', 'lecturer' => 'lecturer.si@example.com', 'day' => 'Friday', 'start' => '08:00', 'end' => '10:00', 'room' => 'B-401', 'status' => 'Open'],
            ['course' => 'MN101', 'program' => 'MN', 'label' => 'Reguler A', 'lecturer' => 'lecturer.mn@example.com', 'day' => 'Tuesday', 'start' => '13:00', 'end' => '15:00', 'room' => 'B-401', 'status' => 'Open'],
            ['course' => 'MN102', 'program' => 'MN', 'label' => 'Reguler A', 'lecturer' => 'lecturer.mn@example.com', 'day' => 'Wednesday', 'start' => '13:00', 'end' => '15:00', 'room' => 'B-301', 'status' => 'Open'],
        ];

        return collect($rows)->map(function (array $row) {
            $course = $this->courses[$row['course']];
            $program = $this->programs[$row['program']];
            $offering = CourseOffering::updateOrCreate(
                [
                    'academic_year_id' => $this->activeYear->id,
                    'study_program_id' => $program->id,
                    'course_id' => $course->id,
                    'label' => $row['label'],
                ],
                [
                    'curriculum_id' => $this->curriculums[$row['program']]->id,
                    'code' => $course->code.'-'.Str::slug($row['label'], '-'),
                    'semester_no' => $course->semester_recommendation ?: 1,
                    'capacity' => 40,
                    'credits' => $course->credits,
                    'total_meetings' => 14,
                    'class_start_date' => now()->startOfMonth()->toDateString(),
                    'class_end_date' => now()->startOfMonth()->addMonths(4)->toDateString(),
                    'is_required' => true,
                    'delivery_mode' => 'Offline',
                    'status' => $row['status'],
                    'notes' => 'Kelas aktif semester berjalan.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            if ($row['lecturer'] && isset($this->lecturers[$row['lecturer']])) {
                $lecturer = $this->lecturers[$row['lecturer']];
                CourseOfferingLecturer::updateOrCreate(
                    ['course_offering_id' => $offering->id, 'lecturer_profile_id' => $lecturer->id],
                    [
                        'role' => 'Primary',
                        'sort_order' => 1,
                        'notes' => 'Dosen pengampu utama.',
                        'is_active' => true,
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ],
                );

                if ($row['day'] && $row['room']) {
                    CourseSchedule::updateOrCreate(
                        [
                            'course_offering_id' => $offering->id,
                            'day_of_week' => $row['day'],
                            'start_time' => $row['start'],
                            'end_time' => $row['end'],
                        ],
                        [
                            'lecturer_profile_id' => $lecturer->id,
                            'room_id' => $this->rooms[$row['room']]->id,
                            'session_type' => 'Lecture',
                            'delivery_mode' => 'Offline',
                            'meeting_link' => null,
                            'notes' => 'Jadwal kelas reguler.',
                            'is_active' => true,
                            'created_by' => $this->admin->id,
                            'updated_by' => $this->admin->id,
                        ],
                    );
                }
            }

            return $offering;
        })->keyBy(fn (CourseOffering $offering) => $offering->course?->code.'|'.$offering->label);
    }

    private function seedRegistrationsAndStudyPlans(): Collection
    {
        return $this->students->map(function (StudentProfile $student) {
            $registration = StudentRegistration::updateOrCreate(
                ['student_profile_id' => $student->id, 'academic_year_id' => $this->activeYear->id],
                [
                    'semester_no' => min(8, max(1, $student->current_semester ?: 1)),
                    'registration_status' => 'Approved',
                    'academic_status' => 'Aktif',
                    'registration_date' => now()->toDateString(),
                    'submitted_at' => now()->subDays(3),
                    'approved_at' => now()->subDays(2),
                    'approved_by' => $this->admin->id,
                    'notes' => 'Registrasi disetujui.',
                    'is_active' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            $studyPlan = StudyPlan::updateOrCreate(
                ['student_profile_id' => $student->id, 'academic_year_id' => $this->activeYear->id],
                [
                    'student_registration_id' => $registration->id,
                    'semester_no' => min(8, max(1, $student->current_semester ?: 1)),
                    'status' => 'Approved',
                    'submitted_at' => now()->subDays(3),
                    'approved_at' => now()->subDays(2),
                    'approved_by' => $this->admin->id,
                    'notes' => 'KRS disetujui.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            $eligibleOfferings = $this->offerings
                ->filter(fn (CourseOffering $offering) => $offering->study_program_id === $student->study_program_id)
                ->take($student->current_semester > 2 ? 4 : 3);

            $eligibleOfferings->each(function (CourseOffering $offering) use ($studyPlan) {
                StudyPlanDetail::updateOrCreate(
                    ['study_plan_id' => $studyPlan->id, 'course_offering_id' => $offering->id],
                    [
                        'credits' => $offering->credits,
                        'is_repeat' => false,
                        'status' => 'Taken',
                        'notes' => 'Mata kuliah diambil pada semester berjalan.',
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ],
                );
            });

            return $studyPlan;
        })->keyBy('student_profile_id');
    }

    private function seedAttendance(Collection $studyPlans): void
    {
        $statusPattern = ['Present', 'Present', 'Present', 'Late', 'Excused', 'Sick', 'Absent'];

        $this->offerings->values()->each(function (CourseOffering $offering, int $offeringIndex) use ($studyPlans, $statusPattern) {
            $schedule = CourseSchedule::query()->where('course_offering_id', $offering->id)->first();
            $lecturer = CourseOfferingLecturer::query()->where('course_offering_id', $offering->id)->where('is_active', true)->first();
            $studentIds = $studyPlans
                ->filter(fn (StudyPlan $plan) => $plan->details()->where('course_offering_id', $offering->id)->exists())
                ->keys()
                ->values();

            $sessionTotal = $offeringIndex % 4 === 0 ? 3 : 5;

            for ($meeting = 1; $meeting <= $sessionTotal; $meeting++) {
                $status = $meeting === 1 && $offeringIndex === 0 ? 'Opened' : 'Closed';
                $session = AttendanceSession::updateOrCreate(
                    ['course_offering_id' => $offering->id, 'meeting_no' => $meeting],
                    [
                        'course_schedule_id' => $schedule?->id,
                        'lecturer_profile_id' => $lecturer?->lecturer_profile_id,
                        'meeting_date' => now()->startOfMonth()->addWeeks($meeting - 1)->addDays($offeringIndex % 5)->toDateString(),
                        'start_time' => $schedule?->start_time?->format('H:i') ?? '08:00',
                        'end_time' => $schedule?->end_time?->format('H:i') ?? '10:00',
                        'topic' => 'Pertemuan '.$meeting.' - '.$this->topicFor($offering->course?->name ?? 'Perkuliahan', $meeting),
                        'notes' => $status === 'Opened' ? 'Sesi sedang berjalan untuk absensi mandiri.' : 'Sesi perkuliahan selesai.',
                        'status' => $status,
                        'attendance_method' => $status === 'Opened' ? 'qr' : 'manual',
                        'qr_secret' => $status === 'Opened' ? Str::random(48) : null,
                        'qr_interval_seconds' => 2,
                        'opened_at' => $status === 'Opened' ? now()->subMinutes(10) : now()->startOfMonth()->addWeeks($meeting - 1)->addDays($offeringIndex % 5)->setTime(8, 0),
                        'closed_at' => $status === 'Closed' ? now()->startOfMonth()->addWeeks($meeting - 1)->addDays($offeringIndex % 5)->setTime(10, 0) : null,
                        'late_after_minutes' => 15,
                        'geo_required' => false,
                        'photo_required' => false,
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ],
                );

                $studentIds->each(function (int $studentId, int $studentIndex) use ($session, $statusPattern, $meeting) {
                    $recordStatus = $statusPattern[($studentIndex + $meeting) % count($statusPattern)];
                    $source = $session->status === 'Opened' && $recordStatus === 'Present' ? 'student_qr' : 'manual_lecturer';
                    AttendanceRecord::updateOrCreate(
                        ['attendance_session_id' => $session->id, 'student_profile_id' => $studentId],
                        [
                            'status' => $recordStatus,
                            'source' => $source,
                            'verification_status' => 'verified',
                            'recorded_at' => $session->meeting_date?->copy()->setTime(8, 5 + ($studentIndex % 20)),
                            'recorded_by' => $source === 'manual_lecturer' ? $this->admin->id : null,
                            'scanned_at' => $source === 'student_qr' ? now()->subMinutes(3) : null,
                            'latitude' => $source === 'student_qr' ? -6.2000000 : null,
                            'longitude' => $source === 'student_qr' ? 106.8166660 : null,
                            'location_accuracy' => $source === 'student_qr' ? 18.50 : null,
                            'token_slot' => $source === 'student_qr' ? intdiv(now()->timestamp, 2) : null,
                            'ip_address' => $source === 'student_qr' ? '127.0.0.1' : null,
                            'user_agent' => $source === 'student_qr' ? 'NexaCampus Seeder Browser' : null,
                            'notes' => $recordStatus === 'Excused' ? 'Izin kegiatan akademik.' : null,
                            'created_by' => $this->admin->id,
                            'updated_by' => $this->admin->id,
                        ],
                    );
                });
            }
        });
    }

    private function seedGrades(Collection $studyPlans): void
    {
        $studyPlans->each(function (StudyPlan $studyPlan, int $planIndex) {
            $details = StudyPlanDetail::query()->where('study_plan_id', $studyPlan->id)->with('courseOffering.course')->get();
            $totalCredits = 0;
            $totalPoints = 0;

            $details->each(function (StudyPlanDetail $detail, int $detailIndex) use (&$totalCredits, &$totalPoints, $planIndex, $studyPlan) {
                $score = 72 + (($planIndex + $detailIndex) % 5) * 4;
                [$letter, $point] = $this->gradeFromScore($score);
                $grade = StudentGrade::updateOrCreate(
                    ['study_plan_detail_id' => $detail->id],
                    [
                        'final_score' => $score,
                        'letter_grade' => $letter,
                        'grade_point' => $point,
                        'result_status' => $point >= 2 ? 'Passed' : 'Failed',
                        'grade_status' => $detailIndex % 3 === 0 ? 'Draft' : 'Finalized',
                        'graded_at' => now()->subDays(1),
                        'graded_by' => $this->admin->id,
                        'notes' => 'Nilai akademik semester berjalan.',
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ],
                );

                foreach ([
                    ['name' => 'Tugas', 'weight' => 20, 'score' => min(100, $score + 4), 'sort' => 1],
                    ['name' => 'Kuis', 'weight' => 20, 'score' => max(0, $score - 2), 'sort' => 2],
                    ['name' => 'UTS', 'weight' => 30, 'score' => $score, 'sort' => 3],
                    ['name' => 'UAS', 'weight' => 30, 'score' => min(100, $score + 2), 'sort' => 4],
                ] as $component) {
                    StudentGradeComponent::updateOrCreate(
                        ['student_grade_id' => $grade->id, 'name' => $component['name']],
                        [
                            'weight_percentage' => $component['weight'],
                            'score' => $component['score'],
                            'sort_order' => $component['sort'],
                            'notes' => null,
                            'created_by' => $this->admin->id,
                            'updated_by' => $this->admin->id,
                        ],
                    );
                }

                $credits = (int) $detail->credits;
                $totalCredits += $credits;
                $totalPoints += $point * $credits;

                TranscriptEntry::updateOrCreate(
                    ['student_profile_id' => $studyPlan->student_profile_id, 'course_id' => $detail->courseOffering?->course_id, 'student_grade_id' => $grade->id],
                    [
                        'academic_year_id' => $this->activeYear->id,
                        'semester_no' => $studyPlan->semester_no,
                        'credits' => $credits,
                        'final_score' => $grade->final_score,
                        'letter_grade' => $grade->letter_grade,
                        'grade_point' => $grade->grade_point,
                        'result_status' => $grade->result_status,
                        'is_counted_in_gpa' => true,
                        'is_best_grade' => true,
                        'notes' => 'Transkrip semester berjalan.',
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ],
                );
            });

            $gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;

            StudyResult::updateOrCreate(
                ['student_profile_id' => $studyPlan->student_profile_id, 'academic_year_id' => $this->activeYear->id],
                [
                    'study_plan_id' => $studyPlan->id,
                    'semester_no' => $studyPlan->semester_no,
                    'total_courses' => $details->count(),
                    'total_credits_taken' => $totalCredits,
                    'total_credits_passed' => $totalCredits,
                    'semester_gpa' => $gpa,
                    'cumulative_gpa' => $gpa,
                    'status' => 'Finalized',
                    'finalized_at' => now()->subHours(6),
                    'finalized_by' => $this->admin->id,
                    'published_at' => now()->subHours(5),
                    'published_by' => $this->admin->id,
                    'notes' => 'Snapshot hasil studi semester berjalan.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );
        });
    }

    private function seedLearningContent(Collection $studyPlans): void
    {
        if (! Schema::hasTable('course_materials') || ! Schema::hasTable('assignments')) {
            return;
        }

        $this->offerings->values()->take(6)->each(function (CourseOffering $offering, int $index) use ($studyPlans) {
            $lecturer = $offering->lecturers()->where('is_active', true)->first()?->lecturerProfile?->user;

            if (! $lecturer) {
                return;
            }

            CourseMaterial::updateOrCreate(
                ['course_offering_id' => $offering->id, 'title' => 'Modul '.$offering->course?->short_name.' Pertemuan 1'],
                [
                    'uploaded_by' => $lecturer->id,
                    'description' => 'Materi pengantar untuk pertemuan awal.',
                    'file_path' => 'samples/materials/'.$offering->code.'-modul-1.pdf',
                    'file_name' => Str::slug($offering->course?->short_name ?? 'materi').'-modul-1.pdf',
                    'file_type' => 'pdf',
                    'file_size' => 524288,
                    'category' => 'module',
                    'meeting_number' => 1,
                    'is_published' => true,
                    'download_count' => 3 + $index,
                    'likes_count' => 1 + ($index % 4),
                    'last_accessed_at' => now()->subHours($index + 1),
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            $assignmentPayload = [
                'created_by' => $lecturer->id,
                'description' => 'Tugas terstruktur untuk mengukur pemahaman materi.',
                'due_at' => now()->addDays(7 + $index),
                'max_score' => 100,
                'allowed_file_types' => ['pdf', 'docx'],
                'max_file_size_kb' => 5120,
                'max_files' => 2,
                'late_penalty_percentage' => 10,
                'allow_text_submission' => true,
                'allow_file_submission' => true,
                'allow_resubmission' => true,
                'accept_late_submission' => true,
                'is_published' => true,
                'published_at' => now()->subDays(2),
            ];

            if (Schema::hasColumn('assignments', 'due_date')) {
                $assignmentPayload['due_date'] = now()->addDays(7 + $index);
            }

            if (Schema::hasColumn('assignments', 'max_file_size_mb')) {
                $assignmentPayload['max_file_size_mb'] = 5;
            }

            if (Schema::hasColumn('assignments', 'allow_late_submission')) {
                $assignmentPayload['allow_late_submission'] = true;
            }

            $assignment = Assignment::updateOrCreate(
                ['course_offering_id' => $offering->id, 'title' => 'Tugas '.$offering->course?->short_name.' Minggu '.($index + 1)],
                $this->onlyExistingColumns('assignments', $assignmentPayload),
            );

            $studentIds = $studyPlans
                ->filter(fn (StudyPlan $plan) => $plan->details()->where('course_offering_id', $offering->id)->exists())
                ->keys()
                ->take(5);

            $studentIds->each(function (int $studentId, int $studentIndex) use ($assignment, $lecturer) {
                $submissionPayload = [
                    'content' => 'Jawaban ringkas mahasiswa untuk tugas terstruktur.',
                    'status' => 'graded',
                    'submitted_at' => now()->subDays(1)->addMinutes($studentIndex * 12),
                    'last_resubmitted_at' => null,
                ];

                if (Schema::hasColumn('assignment_submissions', 'submission_text')) {
                    $submissionPayload['submission_text'] = 'Jawaban ringkas mahasiswa untuk tugas terstruktur.';
                }

                $submission = AssignmentSubmission::updateOrCreate(
                    ['assignment_id' => $assignment->id, 'student_profile_id' => $studentId],
                    $this->onlyExistingColumns('assignment_submissions', $submissionPayload),
                );

                AssignmentGrade::updateOrCreate(
                    ['assignment_submission_id' => $submission->id],
                    [
                        'score' => 78 + ($studentIndex * 3),
                        'feedback' => 'Sudah sesuai instruksi, lanjutkan perbaikan minor.',
                        'status' => 'returned',
                        'graded_at' => now()->subHours(12),
                        'graded_by' => $lecturer->id,
                        'returned_at' => now()->subHours(10),
                        'returned_by' => $lecturer->id,
                        'return_note' => 'Nilai sudah dikembalikan.',
                    ],
                );
            });
        });
    }

    private function seedAcademicAdvising(): void
    {
        $advisor = $this->lecturers['lecturer@example.com'] ?? $this->lecturers->first();

        if (! $advisor) {
            return;
        }

        $this->students->where('study_program_id', $advisor->study_program_id)->take(6)->each(function (StudentProfile $student, int $index) use ($advisor) {
            $assignment = AcademicAdvisorAssignment::updateOrCreate(
                ['student_profile_id' => $student->id, 'academic_year_id' => $this->activeYear->id],
                [
                    'lecturer_profile_id' => $advisor->id,
                    'start_date' => now()->subMonths(4)->toDateString(),
                    'end_date' => now()->addMonths(8)->toDateString(),
                    'is_active' => true,
                    'notes' => 'Bimbingan akademik aktif.',
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );

            StudentAdvisorNote::updateOrCreate(
                ['academic_advisor_assignment_id' => $assignment->id, 'topic' => 'Konsultasi KRS '.$student->nim],
                [
                    'student_profile_id' => $student->id,
                    'lecturer_profile_id' => $advisor->id,
                    'notes' => 'Mahasiswa diarahkan mengambil mata kuliah sesuai kurikulum dan beban SKS aman.',
                    'recommendation' => $index % 2 === 0 ? 'Pertahankan beban SKS saat ini.' : 'Pantau kehadiran dan progres tugas.',
                    'follow_up_at' => now()->addWeeks(3)->toDateString(),
                    'status' => 'open',
                    'visible_to_student' => true,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ],
            );
        });
    }

    private function ensureUser(string $email, string $firstName, string $lastName, string $username, string $phone, string $code, string $password): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'photo' => 'default.jpg',
                'username' => $username,
                'phone' => $phone,
                'code' => $code,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );
    }

    private function assignRoleIfExists(User $user, string $roleName): void
    {
        if (! Role::query()->where('name', $roleName)->exists()) {
            return;
        }

        if (! $user->hasRole($roleName)) {
            $user->assignRole($roleName);
        }
    }

    private function gradeFromScore(float $score): array
    {
        return match (true) {
            $score >= 85 => ['A', 4.00],
            $score >= 80 => ['A-', 3.70],
            $score >= 75 => ['B+', 3.30],
            $score >= 70 => ['B', 3.00],
            $score >= 65 => ['C+', 2.50],
            $score >= 60 => ['C', 2.00],
            default => ['D', 1.00],
        };
    }

    private function topicFor(string $courseName, int $meeting): string
    {
        $topics = [
            1 => 'Kontrak kuliah dan pengantar '.$courseName,
            2 => 'Konsep dasar '.$courseName,
            3 => 'Studi kasus dan diskusi',
            4 => 'Praktik terarah',
            5 => 'Review dan kuis',
        ];

        return $topics[$meeting] ?? 'Pendalaman materi';
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, string $column) => Schema::hasColumn($table, $column))
            ->all();
    }
}
