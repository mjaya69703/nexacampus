<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\TridharmaRecord;
use App\Models\Organization\UserDevelopmentRecord;
use App\Models\User;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class StudentServiceSeeder extends Seeder
{
    private ?User $admin = null;
    private ?AcademicYear $academicYear = null;
    private ?Faculty $faculty = null;
    private ?StudyProgram $studyProgram = null;
    public function run(): void
    {
        if (! $this->bootContext()) {
            return;
        }

        StudentProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->each(fn (StudentProfile $student) => $this->seedStudentServiceJourney($student));
    }

    private function bootContext(): bool
    {
        $this->admin = User::query()->where('email', 'superuser@example.com')->first();
        $this->academicYear = AcademicYear::query()->where('code', '2025G')->first();
        $this->faculty = Faculty::query()->where('code', 'FST')->first();
        $this->studyProgram = StudyProgram::query()->where('code', 'TI')->first();

        return (bool) ($this->admin && $this->academicYear && $this->faculty && $this->studyProgram);
    }

    private function seedStudentServiceJourney(StudentProfile $student): void
    {
        if (Schema::hasTable('service_letter_requests') && Schema::hasTable('service_letter_types')) {
            $typeId = DB::table('service_letter_types')->where('code', 'ACTIVE_STUDENT')->value('id');
            if ($typeId) {
                $this->upsertAndGetId('service_letter_requests', ['request_number' => 'SLR-'.$student->nim.'-001'], [
                    'service_letter_type_id' => $typeId,
                    'student_profile_id' => $student->id,
                    'purpose' => 'Pengajuan beasiswa dan administrasi eksternal.',
                    'request_data' => json_encode(['recipient' => 'Instansi Pemberi Beasiswa', 'purpose' => 'Beasiswa']),
                    'status' => 'issued',
                    'fulfillment_method' => 'auto_generate',
                    'student_notes' => 'Permohonan surat aktif kuliah untuk keperluan beasiswa.',
                    'admin_notes' => 'Surat diterbitkan otomatis.',
                    'reviewed_by' => $this->admin->id,
                    'reviewed_at' => now()->subDays(6),
                    'approved_by' => $this->admin->id,
                    'approved_at' => now()->subDays(5),
                    'issued_by' => $this->admin->id,
                    'issued_at' => now()->subDays(5),
                ]);
            }
        }

        if (Schema::hasTable('student_leave_applications') && $student->nim === '20250002') {
            $this->upsertAndGetId('student_leave_applications', ['application_number' => 'CUTI-'.$student->nim.'-001'], [
                'student_profile_id' => $student->id,
                'academic_year_id' => $this->academicYear->id,
                'semester' => $student->current_semester,
                'duration_semesters' => 1,
                'reason_category' => 'medical',
                'reason' => 'Pemulihan kesehatan keluarga.',
                'status' => 'approved',
                'student_notes' => 'Pengajuan cuti sementara.',
                'admin_notes' => 'Disetujui untuk simulasi layanan mahasiswa.',
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => now()->subDays(10),
                'approved_by' => $this->admin->id,
                'approved_at' => now()->subDays(9),
            ]);
        }

        if (Schema::hasTable('graduation_applications') && $student->nim === '20220015') {
            $period = AcademicPeriod::query()->where('type', 'Graduation')->first()
                ?? AcademicPeriod::query()->where('academic_year_id', $this->academicYear->id)->first();

            $this->upsertAndGetId('graduation_applications', ['application_number' => 'YUD-'.$student->nim.'-001'], [
                'student_profile_id' => $student->id,
                'academic_period_id' => $period?->id,
                'graduation_period' => '2025/2026 Ganjil',
                'thesis_title' => 'Sistem Informasi Akademik Berbasis Layanan Terpadu',
                'reason' => 'Mahasiswa telah menyelesaikan seluruh persyaratan akademik.',
                'status' => 'approved',
                'eligibility_snapshot' => json_encode(['credits' => 144, 'gpa' => 3.72, 'financial_clearance' => true]),
                'admin_checklist' => json_encode(['transcript' => true, 'thesis' => true, 'library' => true]),
                'student_notes' => 'Pengajuan yudisium setelah menyelesaikan persyaratan akademik.',
                'admin_notes' => 'Persyaratan administrasi terpenuhi.',
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => now()->subDays(8),
                'approved_by' => $this->admin->id,
                'approved_at' => now()->subDays(7),
            ]);
        }

        if (Schema::hasTable('student_complaints')) {
            $categoryId = null;
            if (Schema::hasTable('student_complaint_categories')) {
                $workUnitId = DB::table('work_units')->where('code', 'BAAK')->value('id');
                $categoryId = $this->upsertAndGetId('student_complaint_categories', ['code' => 'ACADEMIC_ADMIN'], [
                    'name' => 'Administrasi Akademik',
                    'default_work_unit_id' => $workUnitId,
                    'description' => 'Kategori untuk pertanyaan administrasi akademik.',
                    'default_sla_hours' => 48,
                    'is_active' => true,
                ]);
            }

            $complaintId = $this->upsertAndGetId('student_complaints', ['ticket_number' => 'CMP-'.$student->nim.'-001'], [
                'student_profile_id' => $student->id,
                'student_complaint_category_id' => $categoryId,
                'assigned_work_unit_id' => DB::table('work_units')->where('code', 'BAAK')->value('id'),
                'assigned_user_id' => User::query()->where('email', 'staff@example.com')->value('id'),
                'subject' => 'Klarifikasi jadwal layanan akademik',
                'description' => 'Mahasiswa menanyakan jadwal layanan administrasi akademik.',
                'priority' => 'normal',
                'status' => 'responded',
                'due_at' => now()->addHours(24),
                'last_message_at' => now()->subHours(2),
            ]);

            if (Schema::hasTable('student_complaint_messages')) {
                $this->upsertAndGetId('student_complaint_messages', [
                    'student_complaint_id' => $complaintId,
                    'sender_type' => 'student',
                    'message' => 'Mohon informasi jadwal layanan akademik minggu ini.',
                ], [
                    'user_id' => $student->user_id,
                    'is_internal_note' => false,
                ]);
            }
        }
    }

    private function upsertAndGetId(string $table, array $keys, array $values): int
    {
        $now = now();
        $payload = array_merge($values, ['updated_at' => $now]);

        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = $now;
        }

        DB::table($table)->updateOrInsert($keys, $payload);

        return (int) DB::table($table)->where($keys)->value('id');
    }
}
