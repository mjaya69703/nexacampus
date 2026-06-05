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

class FinancialSeeder extends Seeder
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
            ->each(fn (StudentProfile $student) => $this->seedFinancialJourney($student));
    }

    private function bootContext(): bool
    {
        $this->admin = User::query()->where('email', 'superuser@example.com')->first();
        $this->academicYear = AcademicYear::query()->where('code', '2025G')->first();
        $this->faculty = Faculty::query()->where('code', 'FST')->first();
        $this->studyProgram = StudyProgram::query()->where('code', 'TI')->first();

        return (bool) ($this->admin && $this->academicYear && $this->faculty && $this->studyProgram);
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
