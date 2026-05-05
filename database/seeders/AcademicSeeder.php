<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\CoursePrerequisite;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\CourseScope;
use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Models\Access\Role;
use App\Models\Campus\Building;
use App\Models\Campus\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AcademicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::query()->where('email', 'superuser@example.com')->first();

        if (! $adminUser) {
            $adminUser = User::create([
                'first_name' => 'SuperUser',
                'last_name' => 'NexaCampus',
                'photo' => 'default.jpg',
                'username' => 'superuser',
                'phone' => '0800000001',
                'code' => Str::random(6),
                'email' => 'superuser@example.com',
                'password' => Hash::make('admin123'),
                'is_active' => true,
            ]);
        }

        $this->assignRoleIfExists($adminUser, 'superuser');

        $faculty = Faculty::updateOrCreate([
            'code' => 'FST',
        ], [
            'name' => 'Fakultas Sains dan Teknologi',
            'short_name' => 'FST',
            'is_active' => true,
            'desc' => 'Fakultas fokus sains dan teknologi.',
            'created_by' => $adminUser->id,
        ]);

        $studyProgram = StudyProgram::updateOrCreate([
            'code' => 'TI',
        ], [
            'faculty_id' => $faculty->id,
            'name' => 'Teknik Informatika',
            'short_name' => 'Informatika',
            'degree' => 'S1',
            'prefix_degree' => 'S.T.',
            'suffix_degree' => null,
            'is_active' => true,
            'desc' => 'Program studi Teknik Informatika.',
            'created_by' => $adminUser->id,
        ]);

        $academicYear = AcademicYear::updateOrCreate([
            'code' => '2025G',
        ], [
            'name' => '2025/2026 Ganjil',
            'semester' => 'Ganjil',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addMonths(5)->toDateString(),
            'is_active' => true,
            'desc' => 'Tahun akademik aktif untuk semester ganjil.',
            'created_by' => $adminUser->id,
        ]);

        AcademicPeriod::updateOrCreate([
            'academic_year_id' => $academicYear->id,
            'type' => 'Student Registration',
            'name' => 'Registrasi Mahasiswa Ganjil',
        ], [
            'code' => 'REG-2025G',
            'start_at' => now()->subDays(10),
            'end_at' => now()->addDays(20),
            'is_active' => true,
            'desc' => 'Periode registrasi mahasiswa aktif.',
            'created_by' => $adminUser->id,
        ]);

        AcademicPeriod::updateOrCreate([
            'academic_year_id' => $academicYear->id,
            'type' => 'Study Plan',
            'name' => 'KRS Mahasiswa Ganjil',
        ], [
            'code' => 'KRS-2025G',
            'start_at' => now()->subDays(5),
            'end_at' => now()->addDays(25),
            'is_active' => true,
            'desc' => 'Periode pengisian KRS.',
            'created_by' => $adminUser->id,
        ]);

        $courses = collect([
            [
                'code' => 'TI101',
                'name' => 'Algoritma dan Pemrograman',
                'short_name' => 'Algoritma',
                'credits' => 3,
                'semester_recommendation' => 1,
                'requirement_type' => 'Wajib',
                'category_type' => 'Keilmuan',
            ],
            [
                'code' => 'TI102',
                'name' => 'Struktur Data',
                'short_name' => 'Struktur Data',
                'credits' => 3,
                'semester_recommendation' => 2,
                'requirement_type' => 'Wajib',
                'category_type' => 'Keilmuan',
            ],
            [
                'code' => 'TI103',
                'name' => 'Basis Data',
                'short_name' => 'Basis Data',
                'credits' => 3,
                'semester_recommendation' => 2,
                'requirement_type' => 'Wajib',
                'category_type' => 'Keilmuan',
            ],
        ])->map(function (array $courseData) use ($adminUser) {
            return Course::updateOrCreate([
                'code' => $courseData['code'],
            ], array_merge($courseData, [
                'is_active' => true,
                'desc' => 'Mata kuliah inti program studi.',
                'created_by' => $adminUser->id,
            ]));
        });

        $curriculum = Curriculum::updateOrCreate([
            'study_program_id' => $studyProgram->id,
            'name' => 'Kurikulum 2025',
        ], [
            'code' => 'TI-2025',
            'start_year' => 2025,
            'end_year' => 2029,
            'is_active' => true,
            'desc' => 'Kurikulum utama untuk mahasiswa angkatan 2025.',
            'created_by' => $adminUser->id,
        ]);

        $courses->values()->each(function (Course $course, int $index) use ($adminUser, $studyProgram, $curriculum) {
            CurriculumCourse::updateOrCreate([
                'curriculum_id' => $curriculum->id,
                'course_id' => $course->id,
            ], [
                'semester_no' => $index + 1,
                'is_required' => true,
                'sort_order' => $index + 1,
                'credits_override' => $course->credits,
                'is_active' => true,
                'created_by' => $adminUser->id,
            ]);

            CourseScope::updateOrCreate([
                'course_id' => $course->id,
                'scope_type' => 'study_program',
                'scope_id' => $studyProgram->id,
            ], [
                'created_by' => $adminUser->id,
            ]);
        });

        if ($courses->count() > 1) {
            CoursePrerequisite::updateOrCreate([
                'course_id' => $courses[1]->id,
                'prerequisite_course_id' => $courses[0]->id,
            ], [
                'created_by' => $adminUser->id,
            ]);
        }

        $building = Building::updateOrCreate([
            'code' => 'GDG-A',
        ], [
            'name' => 'Gedung A',
            'address' => 'Jl. Kampus No. 1',
            'floor_count' => 4,
            'is_active' => true,
            'desc' => 'Gedung perkuliahan utama.',
            'created_by' => $adminUser->id,
        ]);

        $room = Room::updateOrCreate([
            'code' => 'A-101',
        ], [
            'building_id' => $building->id,
            'name' => 'Ruang 101',
            'floor' => '1',
            'capacity' => 40,
            'type' => 'Classroom',
            'is_active' => true,
            'desc' => 'Ruang kelas reguler.',
            'created_by' => $adminUser->id,
        ]);

        $lecturerUser = User::firstOrCreate([
            'email' => 'lecturer@example.com',
        ], [
            'first_name' => 'Dosen',
            'last_name' => 'Utama',
            'photo' => 'default.jpg',
            'username' => 'lecturer',
            'phone' => '0800000101',
            'code' => Str::random(6),
            'password' => Hash::make('lecturer123'),
            'is_active' => true,
        ]);

        $this->assignRoleIfExists($lecturerUser, 'lecturer');

        $lecturerProfile = LecturerProfile::updateOrCreate([
            'user_id' => $lecturerUser->id,
        ], [
            'faculty_id' => $faculty->id,
            'study_program_id' => $studyProgram->id,
            'nidn' => '1100110011',
            'employment_status' => 'Tetap',
            'join_date' => now()->subYears(3)->toDateString(),
            'is_active' => true,
            'desc' => 'Dosen pengampu utama.',
            'created_by' => $adminUser->id,
        ]);

        $studentUser = User::firstOrCreate([
            'email' => 'student@example.com',
        ], [
            'first_name' => 'Mahasiswa',
            'last_name' => 'Nexa',
            'photo' => 'default.jpg',
            'username' => 'student',
            'phone' => '0800000201',
            'code' => Str::random(6),
            'password' => Hash::make('student123'),
            'is_active' => true,
        ]);

        $this->assignRoleIfExists($studentUser, 'student');

        $studentProfile = StudentProfile::updateOrCreate([
            'user_id' => $studentUser->id,
        ], [
            'study_program_id' => $studyProgram->id,
            'entry_academic_year_id' => $academicYear->id,
            'nim' => '20250001',
            'entry_year' => 2025,
            'academic_status' => 'Aktif',
            'entry_date' => now()->subMonths(8)->toDateString(),
            'current_semester' => 1,
            'is_active' => true,
            'desc' => 'Mahasiswa contoh.',
            'created_by' => $adminUser->id,
        ]);

        $studentRegistration = StudentRegistration::updateOrCreate([
            'student_profile_id' => $studentProfile->id,
            'academic_year_id' => $academicYear->id,
        ], [
            'semester_no' => 1,
            'registration_status' => 'Approved',
            'academic_status' => 'Aktif',
            'registration_date' => now()->toDateString(),
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
            'approved_by' => $adminUser->id,
            'notes' => 'Registrasi awal disetujui.',
            'is_active' => true,
            'created_by' => $adminUser->id,
        ]);

        $studyPlan = StudyPlan::updateOrCreate([
            'student_profile_id' => $studentProfile->id,
            'academic_year_id' => $academicYear->id,
        ], [
            'student_registration_id' => $studentRegistration->id,
            'semester_no' => 1,
            'status' => 'Approved',
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
            'approved_by' => $adminUser->id,
            'notes' => 'KRS disetujui.',
            'created_by' => $adminUser->id,
        ]);

        $classStartDate = now()->startOfMonth()->toDateString();
        $classEndDate = now()->startOfMonth()->addMonths(4)->toDateString();

        $courseOfferings = $courses->values()->map(function (Course $course, int $index) use ($adminUser, $academicYear, $studyProgram, $curriculum, $classStartDate, $classEndDate) {
            return CourseOffering::updateOrCreate([
                'academic_year_id' => $academicYear->id,
                'study_program_id' => $studyProgram->id,
                'course_id' => $course->id,
                'label' => 'Reguler A',
            ], [
                'curriculum_id' => $curriculum->id,
                'code' => $course->code.'-A',
                'semester_no' => $index + 1,
                'capacity' => 40,
                'credits' => $course->credits,
                'total_meetings' => 14,
                'class_start_date' => $classStartDate,
                'class_end_date' => $classEndDate,
                'is_required' => true,
                'delivery_mode' => 'Offline',
                'status' => 'Open',
                'notes' => 'Kelas reguler utama.',
                'created_by' => $adminUser->id,
            ]);
        });

        $primaryOffering = $courseOfferings->first();

        if ($primaryOffering) {
            CourseOfferingLecturer::updateOrCreate([
                'course_offering_id' => $primaryOffering->id,
                'lecturer_profile_id' => $lecturerProfile->id,
            ], [
                'role' => 'Primary',
                'sort_order' => 1,
                'notes' => 'Dosen utama kelas reguler.',
                'is_active' => true,
                'created_by' => $adminUser->id,
            ]);

            CourseSchedule::updateOrCreate([
                'course_offering_id' => $primaryOffering->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '10:00',
            ], [
                'lecturer_profile_id' => $lecturerProfile->id,
                'room_id' => $room->id,
                'session_type' => 'Lecture',
                'delivery_mode' => 'Offline',
                'meeting_link' => null,
                'notes' => 'Jadwal utama kelas.',
                'is_active' => true,
                'created_by' => $adminUser->id,
            ]);
        }

        $studyPlanDetail = null;

        if ($primaryOffering) {
            $studyPlanDetail = StudyPlanDetail::updateOrCreate([
                'study_plan_id' => $studyPlan->id,
                'course_offering_id' => $primaryOffering->id,
            ], [
                'credits' => $primaryOffering->credits,
                'is_repeat' => false,
                'status' => 'Taken',
                'notes' => 'Mata kuliah utama semester 1.',
                'created_by' => $adminUser->id,
            ]);
        }

        if ($studyPlanDetail) {
            $studentGrade = StudentGrade::updateOrCreate([
                'study_plan_detail_id' => $studyPlanDetail->id,
            ], [
                'final_score' => 88.5,
                'letter_grade' => 'A',
                'grade_point' => 4.00,
                'result_status' => 'Passed',
                'grade_status' => 'Finalized',
                'graded_at' => now()->subDays(1),
                'graded_by' => $lecturerUser->id,
                'notes' => 'Nilai akhir semester.',
                'created_by' => $adminUser->id,
            ]);

            $components = [
                ['name' => 'Tugas', 'weight_percentage' => 20.00, 'score' => 90.00, 'sort_order' => 1],
                ['name' => 'Kuis', 'weight_percentage' => 20.00, 'score' => 85.00, 'sort_order' => 2],
                ['name' => 'UTS', 'weight_percentage' => 30.00, 'score' => 88.00, 'sort_order' => 3],
                ['name' => 'UAS', 'weight_percentage' => 30.00, 'score' => 91.00, 'sort_order' => 4],
            ];

            foreach ($components as $component) {
                StudentGradeComponent::updateOrCreate([
                    'student_grade_id' => $studentGrade->id,
                    'name' => $component['name'],
                ], [
                    'weight_percentage' => $component['weight_percentage'],
                    'score' => $component['score'],
                    'sort_order' => $component['sort_order'],
                    'notes' => null,
                    'created_by' => $adminUser->id,
                ]);
            }

            StudyResult::updateOrCreate([
                'student_profile_id' => $studentProfile->id,
                'academic_year_id' => $academicYear->id,
            ], [
                'study_plan_id' => $studyPlan->id,
                'semester_no' => 1,
                'total_courses' => 1,
                'total_credits_taken' => $primaryOffering?->credits ?? 0,
                'total_credits_passed' => $primaryOffering?->credits ?? 0,
                'semester_gpa' => 4.00,
                'cumulative_gpa' => 4.00,
                'status' => 'Finalized',
                'finalized_at' => now()->subHours(6),
                'finalized_by' => $adminUser->id,
                'notes' => 'Snapshot hasil studi semester 1.',
                'created_by' => $adminUser->id,
            ]);

            if ($primaryOffering?->course_id) {
                TranscriptEntry::updateOrCreate([
                    'student_profile_id' => $studentProfile->id,
                    'course_id' => $primaryOffering->course_id,
                    'student_grade_id' => $studentGrade->id,
                ], [
                    'academic_year_id' => $academicYear->id,
                    'semester_no' => 1,
                    'credits' => $primaryOffering->credits,
                    'final_score' => $studentGrade->final_score,
                    'letter_grade' => $studentGrade->letter_grade,
                    'grade_point' => $studentGrade->grade_point,
                    'result_status' => $studentGrade->result_status,
                    'is_counted_in_gpa' => true,
                    'is_best_grade' => true,
                    'notes' => 'Transkrip awal mahasiswa.',
                    'created_by' => $adminUser->id,
                ]);
            }
        }
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
}
