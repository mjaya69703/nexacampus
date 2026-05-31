<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicPeriod;
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

class SystemWideDemoSeeder extends Seeder
{
    private ?User $admin = null;
    private ?AcademicYear $academicYear = null;
    private ?Faculty $faculty = null;
    private ?StudyProgram $studyProgram = null;

    public function run(): void
    {
        $this->admin = User::query()->where('email', 'superuser@example.com')->first();
        $this->academicYear = AcademicYear::query()->where('code', '2025G')->first();
        $this->faculty = Faculty::query()->where('code', 'FST')->first();
        $this->studyProgram = StudyProgram::query()->where('code', 'TI')->first();

        if (! $this->admin || ! $this->academicYear || ! $this->faculty || ! $this->studyProgram) {
            return;
        }

        $students = collect([
            $this->ensureStudent('student@example.com', 'Mahasiswa', 'Nexa', '20250001', 1),
            $this->ensureStudent('student.aktif@example.com', 'Rania', 'Putri', '20250002', 3),
            $this->ensureStudent('student.yudisium@example.com', 'Bagas', 'Pratama', '20220015', 8),
        ])->filter();

        $this->seedAdmissionJourney();
        $students->each(fn (StudentProfile $student) => $this->seedFinancialJourney($student));
        $students->each(fn (StudentProfile $student) => $this->seedStudentServiceJourney($student));
        $this->seedOrganizationJourney();
        $this->seedDevelopmentRecords($students);
        $this->seedTridharmaJourney($students);
    }

    private function ensureStudent(string $email, string $firstName, string $lastName, string $nim, int $semester): ?StudentProfile
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'photo' => 'default.jpg',
                'username' => Str::slug($firstName.'.'.$lastName),
                'phone' => '08'.random_int(1000000000, 9999999999),
                'code' => Str::upper(Str::random(6)),
                'password' => Hash::make('student123'),
                'is_active' => true,
            ],
        );

        $this->assignRoleIfExists($user, 'student');

        return StudentProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'study_program_id' => $this->studyProgram->id,
                'entry_academic_year_id' => $this->academicYear->id,
                'nim' => $nim,
                'entry_year' => (int) substr($nim, 0, 4),
                'academic_status' => 'Aktif',
                'entry_date' => now()->subMonths($semester * 6)->toDateString(),
                'current_semester' => $semester,
                'is_active' => true,
                'desc' => 'Mahasiswa aktif dengan histori akademik lintas layanan.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ],
        );
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

    private function seedFinancialJourney(StudentProfile $student): void
    {
        if (! Schema::hasTable('student_invoices')) {
            return;
        }

        $tuitionTotal = 6500000;
        $scholarshipAmount = $student->nim === '20250002' ? 1500000 : 0;
        $paidAmount = $student->nim === '20250001' ? 7000000 : ($tuitionTotal - $scholarshipAmount);
        $invoiceTotal = $tuitionTotal - $scholarshipAmount;
        $outstanding = max($invoiceTotal - min($paidAmount, $invoiceTotal), 0);
        $invoiceNumber = 'INV-'.$student->nim.'-2025G';

        if (Schema::hasTable('tuition_fees')) {
            $this->upsertAndGetId('tuition_fees', [
                'academic_year_id' => $this->academicYear->id,
                'study_program_id' => $this->studyProgram->id,
                'semester' => $student->current_semester ?: 1,
            ], [
                'base_fee' => 5500000,
                'lab_fee' => 750000,
                'library_fee' => 150000,
                'activity_fee' => 100000,
                'late_penalty_per_day' => 0,
                'payment_deadline' => now()->addDays(14)->toDateString(),
                'is_active' => true,
                'notes' => 'Tarif aktif untuk periode akademik berjalan.',
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ]);
        }

        $invoiceId = $this->upsertAndGetId('student_invoices', ['invoice_number' => $invoiceNumber], [
            'student_profile_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'semester' => $student->current_semester ?: 1,
            'invoice_type' => 'tuition',
            'source_type' => null,
            'source_id' => null,
            'total_amount' => $invoiceTotal,
            'paid_amount' => min($paidAmount, $invoiceTotal),
            'outstanding_amount' => $outstanding,
            'status' => $outstanding > 0 ? 'partial' : 'paid',
            'due_date' => now()->addDays(14)->toDateString(),
            'paid_at' => $outstanding > 0 ? null : now()->subDays(3),
            'issued_at' => now()->subDays(20),
            'issued_by' => $this->admin->id,
            'notes' => 'Invoice terhubung ke histori pembayaran mahasiswa.',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        if (Schema::hasTable('invoice_items')) {
            foreach ([
                ['item_type' => 'tuition', 'description' => 'SPP/UKT Semester', 'amount' => 5500000, 'sort_order' => 1],
                ['item_type' => 'lab', 'description' => 'Biaya Laboratorium', 'amount' => 750000, 'sort_order' => 2],
                ['item_type' => 'activity', 'description' => 'Biaya Aktivitas Kampus', 'amount' => 250000, 'sort_order' => 3],
            ] as $item) {
                $this->upsertAndGetId('invoice_items', [
                    'student_invoice_id' => $invoiceId,
                    'description' => $item['description'],
                ], $item);
            }
        }

        if ($scholarshipAmount > 0 && Schema::hasTable('scholarships') && Schema::hasTable('student_scholarships')) {
            $scholarshipId = $this->upsertAndGetId('scholarships', ['name' => 'Beasiswa Prestasi Akademik'], [
                'description' => 'Beasiswa prestasi untuk potongan invoice mahasiswa.',
                'type' => 'partial',
                'discount_type' => 'fixed',
                'discount_percentage' => null,
                'fixed_amount' => $scholarshipAmount,
                'duration_semesters' => 2,
                'requirements' => 'IPK minimal 3.50',
                'is_active' => true,
            ]);

            $this->upsertAndGetId('student_scholarships', [
                'student_profile_id' => $student->id,
                'scholarship_id' => $scholarshipId,
                'academic_year_id' => $this->academicYear->id,
                'semester' => $student->current_semester ?: 1,
            ], [
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addMonths(5)->toDateString(),
                'status' => 'active',
                'notes' => 'Beasiswa aktif pada semester berjalan.',
                'created_by' => $this->admin->id,
            ]);

            if (Schema::hasTable('invoice_adjustments')) {
                $this->upsertAndGetId('invoice_adjustments', [
                    'student_invoice_id' => $invoiceId,
                    'adjustment_type' => 'scholarship',
                ], [
                    'amount' => $scholarshipAmount,
                    'source_type' => 'scholarship',
                    'source_id' => $scholarshipId,
                    'reason' => 'Potongan beasiswa prestasi akademik.',
                    'created_by' => $this->admin->id,
                ]);
            }
        }

        if (Schema::hasTable('payments')) {
            $paymentId = $this->upsertAndGetId('payments', ['payment_number' => 'PAY-'.$student->nim.'-001'], [
                'student_invoice_id' => $invoiceId,
                'student_profile_id' => $student->id,
                'invoice_installment_id' => null,
                'amount' => $paidAmount,
                'payment_method' => 'bank_transfer',
                'transaction_reference' => 'TRX-'.$student->nim.'-DEMO',
                'proof_file_path' => 'samples/payments/'.$student->nim.'.pdf',
                'status' => 'verified',
                'paid_at' => now()->subDays(4),
                'submitted_by' => $student->user_id,
                'verified_by' => $this->admin->id,
                'verified_at' => now()->subDays(3),
                'notes' => 'Pembayaran terhubung ke invoice mahasiswa.',
                'verification_notes' => 'Bukti pembayaran valid.',
            ]);

            if ($paidAmount > $invoiceTotal && Schema::hasTable('student_credit_balances') && Schema::hasTable('student_credit_transactions')) {
                $creditAmount = $paidAmount - $invoiceTotal;
                $this->upsertAndGetId('student_credit_balances', ['student_profile_id' => $student->id], [
                    'balance' => $creditAmount,
                ]);
                $this->upsertAndGetId('student_credit_transactions', [
                    'student_profile_id' => $student->id,
                    'student_invoice_id' => $invoiceId,
                    'payment_id' => $paymentId,
                    'transaction_type' => 'overpayment',
                ], [
                    'amount' => $creditAmount,
                    'notes' => 'Kelebihan bayar otomatis menjadi saldo kredit.',
                    'created_by' => $this->admin->id,
                ]);
            }
        }
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

    private function seedOrganizationJourney(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        $profiles = EmployeeProfile::query()->with('user')->where('is_active', true)->get();
        $sourceId = Schema::hasTable('employee_attendance_sources')
            ? DB::table('employee_attendance_sources')->where('code', 'MANUAL_ADMIN')->value('id')
            : null;

        foreach ($profiles as $index => $profile) {
            if (Schema::hasTable('employee_attendance_records') && $sourceId) {
                foreach ([1, 2, 3] as $offset) {
                    $date = now()->subDays($offset + $index)->toDateString();
                    $this->upsertAndGetId('employee_attendance_records', [
                        'employee_profile_id' => $profile->id,
                        'attendance_date' => $date,
                        'employee_attendance_source_id' => $sourceId,
                    ], [
                        'work_unit_id' => $profile->primary_work_unit_id,
                        'sourceable_type' => null,
                        'sourceable_id' => null,
                        'status' => $offset === 3 ? 'late' : 'present',
                        'check_in_at' => now()->subDays($offset + $index)->setTime(8, $offset === 3 ? 25 : 0),
                        'check_out_at' => now()->subDays($offset + $index)->setTime(16, 30),
                        'work_minutes' => $offset === 3 ? 485 : 510,
                        'notes' => 'Absensi tercatat dari sumber administrasi.',
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ]);
                }
            }

            if (Schema::hasTable('employee_leave_requests')) {
                $leaveTypeId = DB::table('employee_leave_types')->where('code', 'ANNUAL_LEAVE')->value('id');
                if ($leaveTypeId) {
                    $this->upsertAndGetId('employee_leave_requests', ['request_number' => 'ELV-'.$profile->employee_number.'-001'], [
                        'employee_profile_id' => $profile->id,
                        'employee_leave_type_id' => $leaveTypeId,
                        'approval_request_id' => null,
                        'starts_at' => now()->addDays(10)->toDateString(),
                        'ends_at' => now()->addDays(11)->toDateString(),
                        'total_days' => 2,
                        'status' => 'approved',
                        'reason' => 'Cuti tahunan.',
                        'employee_notes' => 'Rencana keperluan keluarga.',
                        'admin_notes' => 'Disetujui sesuai kuota cuti.',
                        'reviewed_by' => $this->admin->id,
                        'reviewed_at' => now()->subDays(2),
                        'approved_by' => $this->admin->id,
                        'approved_at' => now()->subDay(),
                        'created_by' => $profile->user_id,
                        'updated_by' => $this->admin->id,
                    ]);
                }
            }
        }
    }

    private function seedDevelopmentRecords($students): void
    {
        if (! Schema::hasTable('user_development_records')) {
            return;
        }

        $users = collect([
            User::query()->where('email', 'lecturer@example.com')->first(),
            User::query()->where('email', 'staff@example.com')->first(),
            $this->admin,
        ])->merge($students->map(fn (StudentProfile $student) => $student->user))->filter();

        foreach ($users as $index => $user) {
            $record = UserDevelopmentRecord::updateOrCreate([
                'user_id' => $user->id,
                'type' => $index % 2 === 0 ? 'certification' : 'training',
                'title' => $index % 2 === 0 ? 'Sertifikasi Transformasi Digital Kampus' : 'Pelatihan Layanan Akademik Terpadu',
            ], [
                'organizer' => 'NexaCampus Academy',
                'credential_number' => 'NC-DEV-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->subMonths(2)->addDays(2)->toDateString(),
                'expires_at' => now()->addYears(2)->toDateString(),
                'cost' => $index % 2 === 0 ? 750000 : 0,
                'description' => 'Riwayat pengembangan kompetensi untuk profil dan verifikasi admin.',
                'is_verified' => $index !== 1,
                'verified_by' => $index !== 1 ? $this->admin->id : null,
                'verified_at' => $index !== 1 ? now()->subMonth() : null,
                'verification_notes' => $index !== 1 ? 'Dokumen telah diverifikasi.' : 'Menunggu pemeriksaan dokumen.',
            ]);

            if (Schema::hasTable('user_development_attachments')) {
                $this->upsertAndGetId('user_development_attachments', [
                    'user_development_record_id' => $record->id,
                    'document_type' => 'certificate',
                ], [
                    'file_path' => 'samples/user-developments/'.$record->id.'.pdf',
                    'file_name' => Str::slug($record->title).'.pdf',
                    'file_size' => 182400,
                ]);
            }
        }
    }

    private function seedTridharmaJourney($students): void
    {
        if (! Schema::hasTable('tridharma_records')) {
            return;
        }

        foreach ([
            'tridharma-record.viewAny',
            'tridharma-record.view',
            'tridharma-record.create',
            'tridharma-record.update',
            'tridharma-record.delete',
            'tridharma-record.verify',
            'tridharma-record.approve',
            'tridharma-record.complete',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if ($this->admin->hasRole('superuser')) {
            $this->admin->roles()->where('name', 'superuser')->first()?->givePermissionTo(Permission::where('guard_name', 'web')->get());
        }

        app(TridharmaRecordService::class)->ensureDefaultApprovalTemplate($this->admin);

        $lecturer = User::query()->where('email', 'lecturer@example.com')->with(['lecturerProfile', 'employeeProfile'])->first();
        $staff = User::query()->where('email', 'staff@example.com')->with(['lecturerProfile', 'employeeProfile'])->first();
        $studentUser = $students->first()?->user?->load(['lecturerProfile', 'employeeProfile']);

        if ($studentUser) {
            TridharmaRecord::withTrashed()
                ->where('user_id', $studentUser->id)
                ->where('title', 'Dashboard Layanan Akademik Terpadu untuk Perguruan Tinggi')
                ->forceDelete();
        }

        $records = collect([
            [
                'user' => $lecturer,
                'type' => 'research',
                'title' => 'Model Prediksi Retensi Mahasiswa Berbasis Data Akademik',
                'scheme' => 'Hibah Internal',
                'status' => 'approved',
                'funding_amount' => 18000000,
                'funding_source' => 'LPPM Kampus',
            ],
            [
                'user' => $staff,
                'type' => 'community_service',
                'title' => 'Pelatihan Literasi Digital untuk Administrasi Sekolah',
                'scheme' => 'Pengabdian Institusi',
                'status' => 'active',
                'funding_amount' => 7500000,
                'funding_source' => 'Unit Kepegawaian',
            ],
            [
                'user' => $this->admin->load(['lecturerProfile', 'employeeProfile']),
                'type' => 'publication',
                'title' => 'Dashboard Layanan Akademik Terpadu untuk Perguruan Tinggi',
                'scheme' => 'Publikasi Institusi',
                'status' => 'completed',
                'funding_amount' => 0,
                'funding_source' => 'Internal Kampus',
            ],
        ])->filter(fn ($item) => $item['user']);

        foreach ($records as $index => $item) {
            $user = $item['user'];
            $record = TridharmaRecord::updateOrCreate([
                'user_id' => $user->id,
                'type' => $item['type'],
                'title' => $item['title'],
            ], [
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'scheme' => $item['scheme'],
                'abstract' => 'Kegiatan Tridharma yang melibatkan pengelolaan proposal, tim, anggaran, luaran, dan dokumen pendukung.',
                'starts_at' => now()->subMonths(3)->toDateString(),
                'ends_at' => now()->addMonths(3 + $index)->toDateString(),
                'status' => $item['status'],
                'funding_amount' => $item['funding_amount'],
                'funding_source' => $item['funding_source'],
                'is_verified' => $index !== 1,
                'verified_by' => $index !== 1 ? $this->admin->id : null,
                'verified_at' => $index !== 1 ? now()->subMonth() : null,
                'verification_notes' => $index !== 1 ? 'Record telah diverifikasi.' : 'Menunggu verifikasi lapangan.',
                'approved_by' => $this->admin->id,
                'approved_at' => now()->subMonths(2),
                'completed_by' => $item['status'] === 'completed' ? $this->admin->id : null,
                'completed_at' => $item['status'] === 'completed' ? now()->subWeek() : null,
                'created_by' => $user->id,
                'updated_by' => $this->admin->id,
            ]);

            $record->members()->updateOrCreate(
                ['user_id' => $user->id, 'role' => 'leader'],
                ['is_external' => false, 'sort_order' => 1],
            );
            $record->members()->updateOrCreate(
                ['member_name' => 'Mitra Eksternal', 'role' => 'partner'],
                ['institution' => 'Institusi Mitra', 'email' => 'mitra@example.com', 'is_external' => true, 'sort_order' => 2],
            );

            if ($studentUser && $index === 0) {
                $record->members()->updateOrCreate(
                    ['user_id' => $studentUser->id, 'role' => 'student_collaborator'],
                    ['is_external' => false, 'sort_order' => 3],
                );
            }

            foreach ([['Proposal', 25, 'completed'], ['Pelaksanaan', 70, $item['status'] === 'completed' ? 'completed' : 'in_progress'], ['Luaran', $item['status'] === 'completed' ? 100 : 30, $item['status'] === 'completed' ? 'completed' : 'in_progress']] as $order => [$title, $progress, $status]) {
                $record->milestones()->updateOrCreate(
                    ['title' => $title],
                    [
                        'description' => 'Target '.$title,
                        'due_date' => now()->addWeeks($order + 1)->toDateString(),
                        'status' => $status,
                        'progress_percentage' => $progress,
                        'completed_at' => $status === 'completed' ? now()->subDays($order + 1) : null,
                        'completed_by' => $status === 'completed' ? $user->id : null,
                        'sort_order' => $order + 1,
                    ],
                );
            }

            foreach ([['Honorarium', 5000000, 2500000], ['Operasional', 3000000, 1250000]] as [$category, $planned, $realized]) {
                $record->budgets()->updateOrCreate(
                    ['category' => $category],
                    ['description' => 'Anggaran '.$category, 'planned_amount' => $planned, 'realized_amount' => $realized],
                );
            }

            $record->outputs()->updateOrCreate(
                ['output_type' => $item['type'] === 'publication' ? 'article' : 'report', 'title' => 'Luaran '.$item['title']],
                [
                    'publisher' => $item['type'] === 'publication' ? 'Jurnal Teknologi Pendidikan' : 'Repository LPPM',
                    'indexing' => $item['type'] === 'publication' ? 'Sinta' : null,
                    'doi' => $item['type'] === 'publication' ? '10.0000/nexacampus.'.$record->id : null,
                    'url' => 'https://example.com/tridharma/'.$record->id,
                    'published_at' => $item['status'] === 'completed' ? now()->subDays(10)->toDateString() : null,
                    'status' => $item['status'] === 'completed' ? 'published' : 'draft',
                ],
            );

            $record->attachments()->updateOrCreate(
                ['document_type' => 'proposal'],
                [
                    'file_path' => 'samples/tridharma/'.$record->id.'-proposal.pdf',
                    'file_name' => Str::slug($record->title).'-proposal.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 225280,
                    'uploaded_by' => $user->id,
                ],
            );
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
