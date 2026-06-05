<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
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

class AdmissionSeeder extends Seeder
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

        $this->seedAdmissionJourney();
    }

    private function bootContext(): bool
    {
        $this->admin = User::query()->where('email', 'superuser@example.com')->first();
        $this->academicYear = AcademicYear::query()->where('code', '2025G')->first();
        $this->faculty = Faculty::query()->where('code', 'FST')->first();
        $this->studyProgram = StudyProgram::query()->where('code', 'TI')->first();

        return (bool) ($this->admin && $this->academicYear && $this->faculty && $this->studyProgram);
    }

    private function seedAdmissionJourney(): void
    {
        if (! Schema::hasTable('admission_periods') || ! Schema::hasTable('admission_applications')) {
            return;
        }

        $periodId = $this->upsertAndGetId('admission_periods', ['code' => 'PMB-2026-W1'], [
            'name' => 'PMB 2026 Gelombang 1',
            'academic_year' => 2026,
            'wave' => 1,
            'opens_at' => now()->subMonth()->toDateString(),
            'closes_at' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'is_published' => true,
            'description' => 'Periode PMB aktif dengan peserta dan dokumen lengkap.',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $requirementId = null;

        if (Schema::hasTable('admission_document_requirements')) {
            $requirementId = $this->upsertAndGetId('admission_document_requirements', [
                'admission_period_id' => $periodId,
                'study_program_id' => $this->studyProgram->id,
                'document_type' => 'report_card',
            ], [
                'label' => 'Rapor Semester Akhir',
                'is_required' => true,
                'allowed_extensions' => 'pdf,jpg,jpeg,png',
                'max_size_kb' => 5120,
                'sort_order' => 1,
            ]);
        }

        $applicationId = $this->upsertAndGetId('admission_applications', ['application_number' => 'PMB-2026-0001'], [
            'admission_period_id' => $periodId,
            'access_token' => 'pmb-2026-0001-token',
            'user_id' => null,
            'full_name' => 'Calon Mahasiswa Jalur Reguler',
            'email' => 'calon.mahasiswa@example.com',
            'phone' => '081234560001',
            'birth_date' => now()->subYears(18)->toDateString(),
            'gender' => 'female',
            'address' => 'Jl. Pendidikan No. 45',
            'emergency_contact_name' => 'Kontak Keluarga',
            'emergency_contact_phone' => '081234560002',
            'high_school_name' => 'SMA Negeri Nusantara',
            'high_school_major' => 'IPA',
            'high_school_graduation_year' => 2026,
            'faculty_id' => $this->faculty->id,
            'study_program_id' => $this->studyProgram->id,
            'class_type' => 'regular',
            'status' => 'accepted',
            'final_score' => 87.50,
            'review_notes' => 'Lulus seleksi administrasi dan akademik.',
            'reviewed_by' => $this->admin->id,
            'submitted_at' => now()->subDays(12),
            'reviewed_at' => now()->subDays(8),
            'accepted_at' => now()->subDays(7),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        if (Schema::hasTable('admission_documents')) {
            $this->upsertAndGetId('admission_documents', [
                'admission_application_id' => $applicationId,
                'document_type' => 'report_card',
            ], [
                'document_requirement_id' => $requirementId,
                'file_path' => 'samples/admission/report-card.pdf',
                'file_name' => 'report-card.pdf',
                'file_size' => 245760,
                'verification_status' => 'verified',
                'verification_notes' => 'Dokumen telah diverifikasi.',
                'verified_by' => $this->admin->id,
                'verified_at' => now()->subDays(8),
            ]);
        }

        if (Schema::hasTable('admission_status_histories')) {
            $this->upsertAndGetId('admission_status_histories', [
                'admission_application_id' => $applicationId,
                'to_status' => 'accepted',
            ], [
                'from_status' => 'under_review',
                'notes' => 'Diterima melalui proses seleksi PMB.',
                'changed_by' => $this->admin->id,
            ]);
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
