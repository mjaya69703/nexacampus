<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Curriculum;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Mahasiswa demo semester lanjut untuk QA grafik tren IPS/IPK.
 *
 * Profil ini sengaja memiliki riwayat 9 semester (semester 1-9) dan sedang
 * menempuh semester 10, sehingga grafik dashboard harus melebar melewati
 * sumbu default 1-8. Kredensial: student.senior@example.com / student123.
 */
class DemoSeniorStudentSeeder extends Seeder
{
    /**
     * Mata kuliah per semester. Semua kode unik agar transkrip demo tidak
     * berisi mata kuliah ganda pada beberapa semester sekaligus.
     *
     * @var array<int, array<int, string>>
     */
    private const SEMESTER_COURSES = [
        1 => ['TI101', 'TI102'],
        2 => ['TI103', 'TI201'],
        3 => ['TI202', 'SI201'],
        4 => ['SI101', 'SI102'],
        5 => ['MN101', 'MN102'],
        6 => ['TI203', 'TI204'],
        7 => ['TI301', 'TI302'],
        8 => ['TI303', 'TI304'],
        9 => ['TI401', 'TI402'],
    ];

    /**
     * Nilai demo per semester; sengaja naik-turun agar grafik terlihat wajar.
     *
     * @var array<int, array<int, float>>
     */
    private const SEMESTER_GRADES = [
        1 => [3.00, 3.30],
        2 => [3.30, 3.70],
        3 => [3.70, 3.30],
        4 => [3.70, 4.00],
        5 => [3.70, 3.70],
        6 => [4.00, 3.70],
        7 => [4.00, 3.70],
        8 => [4.00, 4.00],
        9 => [3.70, 4.00],
    ];

    public function run(): void
    {
        $program = StudyProgram::query()->where('code', 'TI')->first();

        if (! $program) {
            $this->command?->warn('DemoSeniorStudentSeeder dilewati: program studi TI belum tersedia.');

            return;
        }

        $admin = User::query()->where('username', 'superuser')->first();
        $role = Role::query()->where('name', 'student')->where('guard_name', 'web')->first();

        DB::transaction(function () use ($program, $admin, $role) {
            $user = User::updateOrCreate(
                ['email' => 'student.senior@example.com'],
                [
                    'first_name' => 'Nadia',
                    'last_name' => 'Prameswari',
                    'photo' => 'default.jpg',
                    'username' => 'nadia.senior',
                    'phone' => '0800000213',
                    'code' => 'USR-STU-013',
                    'password' => Hash::make('student123'),
                    'is_active' => true,
                ],
            );

            if ($role && ! $user->hasRole($role->name)) {
                $user->assignRole($role);
            }

            $years = [];

            foreach (self::SEMESTER_COURSES as $semester => $codes) {
                $startYear = 2021 + intdiv($semester - 1, 2);
                $semesterName = $semester % 2 === 1 ? 'Ganjil' : 'Genap';
                $years[$semester] = $this->ensureAcademicYear($startYear, $semesterName, $admin?->id);
            }

            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'study_program_id' => $program->id,
                    'entry_academic_year_id' => $years[1]->id,
                    'nim' => '20210001',
                    'entry_year' => 2021,
                    'academic_status' => 'Aktif',
                    'entry_date' => '2021-08-01',
                    'current_semester' => 10,
                    'is_active' => true,
                    'desc' => 'Mahasiswa demo dengan riwayat 9 semester untuk QA grafik IPS/IPK.',
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );

            $courses = $this->ensureCourses($admin?->id);

            $cumulativeCredits = 0;
            $cumulativePoints = 0.0;

            foreach (self::SEMESTER_COURSES as $semester => $codes) {
                $semesterCredits = 0;
                $semesterPoints = 0.0;

                foreach ($codes as $index => $code) {
                    $course = $courses[$code] ?? null;

                    if (! $course) {
                        continue;
                    }

                    $gradePoint = self::SEMESTER_GRADES[$semester][$index] ?? 3.00;
                    $credits = (int) $course->credits;
                    [$letter, $score] = $this->gradeFromPoint($gradePoint);

                    $semesterCredits += $credits;
                    $semesterPoints += $gradePoint * $credits;

                    TranscriptEntry::updateOrCreate(
                        [
                            'student_profile_id' => $profile->id,
                            'course_id' => $course->id,
                            'student_grade_id' => null,
                        ],
                        [
                            'academic_year_id' => $years[$semester]->id,
                            'semester_no' => $semester,
                            'credits' => $credits,
                            'final_score' => $score,
                            'letter_grade' => $letter,
                            'grade_point' => $gradePoint,
                            'result_status' => 'Passed',
                            'is_counted_in_gpa' => true,
                            'is_best_grade' => true,
                            'notes' => 'Data demo tren IPS/IPK.',
                            'created_by' => $admin?->id,
                            'updated_by' => $admin?->id,
                        ],
                    );
                }

                if ($semesterCredits === 0) {
                    continue;
                }

                $cumulativeCredits += $semesterCredits;
                $cumulativePoints += $semesterPoints;

                $ips = round($semesterPoints / $semesterCredits, 2);
                $ipk = round($cumulativePoints / $cumulativeCredits, 2);
                $publishedAt = now()->subMonths(max(0, (9 - $semester) * 6));

                StudyResult::updateOrCreate(
                    [
                        'student_profile_id' => $profile->id,
                        'academic_year_id' => $years[$semester]->id,
                    ],
                    [
                        'study_plan_id' => null,
                        'semester_no' => $semester,
                        'total_courses' => count($codes),
                        'total_credits_taken' => $semesterCredits,
                        'total_credits_passed' => $semesterCredits,
                        'semester_gpa' => $ips,
                        'cumulative_gpa' => $ipk,
                        'status' => 'Published',
                        'finalized_at' => $publishedAt,
                        'finalized_by' => $admin?->id,
                        'published_at' => $publishedAt,
                        'published_by' => $admin?->id,
                        'notes' => 'Hasil studi demo semester '.$semester.'.',
                        'created_by' => $admin?->id,
                        'updated_by' => $admin?->id,
                    ],
                );
            }

            $this->seedCurrentSemesterPlan($profile, $program, $years[9], $courses, $admin?->id);
        });

        $this->command?->info('Mahasiswa demo siap: student.senior@example.com / student123 (NIM 20210001, semester 10).');
    }

    private function ensureAcademicYear(int $startYear, string $semester, ?int $userId): AcademicYear
    {
        $name = $startYear.'/'.($startYear + 1).' '.$semester;
        $isGanjil = $semester === 'Ganjil';

        return AcademicYear::firstOrCreate(
            ['name' => $name],
            [
                'code' => $startYear.($isGanjil ? 'G' : 'N'),
                'semester' => $semester,
                'start_date' => ($isGanjil ? $startYear : $startYear + 1).($isGanjil ? '-08-01' : '-02-01'),
                'end_date' => ($startYear + 1).($isGanjil ? '-01-31' : '-07-31'),
                'is_active' => false,
                'desc' => 'Tahun akademik demo.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * Pastikan 18 mata kuliah demo tersedia tanpa mengubah data yang sudah ada.
     *
     * @return array<string, Course>
     */
    private function ensureCourses(?int $userId): array
    {
        $definitions = [
            'TI101' => ['Algoritma dan Pemrograman', 3, 1],
            'TI102' => ['Struktur Data', 3, 2],
            'TI103' => ['Basis Data', 3, 2],
            'TI201' => ['Pemrograman Web', 3, 3],
            'TI202' => ['Kecerdasan Artifisial', 3, 5],
            'SI101' => ['Pengantar Sistem Informasi', 3, 1],
            'SI102' => ['Analisis Proses Bisnis', 3, 2],
            'SI201' => ['Manajemen Proyek TI', 3, 4],
            'MN101' => ['Pengantar Manajemen', 3, 1],
            'MN102' => ['Akuntansi Dasar', 3, 1],
            'TI203' => ['Jaringan Komputer', 3, 3],
            'TI204' => ['Sistem Operasi', 3, 4],
            'TI301' => ['Rekayasa Perangkat Lunak', 3, 5],
            'TI302' => ['Keamanan Informasi', 3, 6],
            'TI303' => ['Data Mining', 3, 6],
            'TI304' => ['Pemrograman Mobile', 3, 6],
            'TI401' => ['Cloud Computing', 3, 7],
            'TI402' => ['Proyek Akhir', 3, 8],
        ];

        $courses = [];

        foreach ($definitions as $code => [$name, $credits, $semester]) {
            $courses[$code] = Course::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'short_name' => $name,
                    'credits' => $credits,
                    'semester_recommendation' => $semester,
                    'requirement_type' => 'Wajib',
                    'category_type' => 'Keilmuan',
                    'is_active' => true,
                    'desc' => 'Mata kuliah demo untuk grafik tren IPS/IPK.',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        return $courses;
    }

    /**
     * @param  array<string, Course>  $courses
     */
    private function seedCurrentSemesterPlan(StudentProfile $profile, StudyProgram $program, AcademicYear $fallbackYear, array $courses, ?int $userId): void
    {
        $activeYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first() ?? $fallbackYear;

        $registration = StudentRegistration::updateOrCreate(
            ['student_profile_id' => $profile->id, 'academic_year_id' => $activeYear->id],
            [
                'semester_no' => 10,
                'registration_status' => 'Approved',
                'academic_status' => 'Aktif',
                'registration_date' => now()->toDateString(),
                'submitted_at' => now()->subDays(5),
                'approved_at' => now()->subDays(4),
                'approved_by' => $userId,
                'notes' => 'Registrasi demo semester lanjut.',
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );

        $studyPlan = StudyPlan::updateOrCreate(
            ['student_profile_id' => $profile->id, 'academic_year_id' => $activeYear->id],
            [
                'student_registration_id' => $registration->id,
                'semester_no' => 10,
                'status' => 'Approved',
                'submitted_at' => now()->subDays(5),
                'approved_at' => now()->subDays(4),
                'approved_by' => $userId,
                'notes' => 'KRS demo semester 10.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );

        $offerings = CourseOffering::query()
            ->where('academic_year_id', $activeYear->id)
            ->where('study_program_id', $program->id)
            ->orderBy('id')
            ->take(2)
            ->get();

        if ($offerings->isEmpty()) {
            $offerings = $this->ensureCurrentOfferings($activeYear, $program, $courses, $userId);
        }

        foreach ($offerings as $offering) {
            StudyPlanDetail::updateOrCreate(
                ['study_plan_id' => $studyPlan->id, 'course_offering_id' => $offering->id],
                [
                    'credits' => $offering->credits,
                    'is_repeat' => false,
                    'status' => 'Taken',
                    'notes' => 'Mata kuliah demo semester berjalan.',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }
    }

    /**
     * Buat offering semester berjalan bila tahun akademik aktif belum punya kelas.
     *
     * @param  array<string, Course>  $courses
     * @return Collection<int, CourseOffering>
     */
    private function ensureCurrentOfferings(AcademicYear $activeYear, StudyProgram $program, array $courses, ?int $userId): Collection
    {
        $curriculum = Curriculum::query()
            ->where('study_program_id', $program->id)
            ->orderBy('id')
            ->first();

        if (! $curriculum) {
            return collect();
        }

        return collect(['TI301', 'TI302'])
            ->map(function (string $code) use ($courses, $activeYear, $program, $curriculum, $userId): ?CourseOffering {
                $course = $courses[$code] ?? null;

                if (! $course) {
                    return null;
                }

                return CourseOffering::updateOrCreate(
                    [
                        'academic_year_id' => $activeYear->id,
                        'study_program_id' => $program->id,
                        'course_id' => $course->id,
                        'label' => 'Reguler A',
                    ],
                    [
                        'curriculum_id' => $curriculum->id,
                        'code' => $course->code.'-reguler-a',
                        'semester_no' => 10,
                        'capacity' => 40,
                        'credits' => $course->credits,
                        'total_meetings' => 14,
                        'class_start_date' => $activeYear->start_date,
                        'class_end_date' => $activeYear->end_date,
                        'is_required' => true,
                        'delivery_mode' => 'Offline',
                        'status' => 'Open',
                        'notes' => 'Kelas demo semester lanjut.',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function gradeFromPoint(float $point): array
    {
        return match (true) {
            $point >= 4.00 => ['A', 90.0],
            $point >= 3.70 => ['A-', 82.0],
            $point >= 3.30 => ['B+', 77.0],
            $point >= 3.00 => ['B', 72.0],
            $point >= 2.70 => ['B-', 68.0],
            $point >= 2.30 => ['C+', 64.0],
            $point >= 2.00 => ['C', 60.0],
            default => ['D', 45.0],
        };
    }
}
