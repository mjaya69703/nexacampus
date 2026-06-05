<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_letter_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('fulfillment_mode')->default('auto_generate'); // auto_generate, manual_upload, hybrid
            $table->string('template_key')->nullable();
            $table->boolean('requires_financial_clearance')->default(false);
            $table->string('clearance_hold_type')->nullable(); // transcript, graduation, registration, study_plan
            $table->json('required_fields')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->string('allowed_extensions')->nullable();
            $table->unsignedInteger('max_file_size_kb')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->string('signature_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'fulfillment_mode']);
            $table->index(['requires_financial_clearance', 'clearance_hold_type'], 'service_letter_clearance_idx');
        });

        Schema::create('service_letter_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('service_letter_type_id')->constrained('service_letter_types')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->string('purpose');
            $table->json('request_data')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, revision_requested, approved, rejected, issued, cancelled
            $table->string('fulfillment_method')->nullable(); // auto_generate, manual_upload
            $table->string('generated_file_path')->nullable();
            $table->string('uploaded_file_path')->nullable();
            $table->string('uploaded_file_name')->nullable();
            $table->text('student_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status']);
            $table->index(['service_letter_type_id', 'status'], 'service_letter_request_type_status_idx');
            $table->index(['status', 'created_at']);
        });

        Schema::create('service_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_letter_request_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_letter_request_id', 'created_at'], 'service_history_request_created_idx');
            $table->foreign('service_letter_request_id', 'service_history_request_fk')
                ->references('id')
                ->on('service_letter_requests')
                ->cascadeOnDelete();
        });

        $now = now();
        DB::table('service_letter_types')->insert([
            [
                'name' => 'Surat Keterangan Aktif Kuliah',
                'code' => 'ACTIVE_STUDENT',
                'description' => 'Surat keterangan bahwa mahasiswa masih aktif berkuliah.',
                'fulfillment_mode' => 'auto_generate',
                'template_key' => 'active_student',
                'requires_financial_clearance' => false,
                'clearance_hold_type' => null,
                'required_fields' => json_encode(['recipient', 'purpose']),
                'requires_attachment' => false,
                'signer_name' => 'Kepala Biro Akademik',
                'signer_position' => 'Kepala Biro Akademik',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Surat Rekomendasi Magang',
                'code' => 'INTERNSHIP_RECOMMENDATION',
                'description' => 'Surat rekomendasi magang/PKL untuk instansi tujuan.',
                'fulfillment_mode' => 'hybrid',
                'template_key' => 'internship_recommendation',
                'requires_financial_clearance' => false,
                'clearance_hold_type' => null,
                'required_fields' => json_encode(['company_name', 'company_address', 'internship_period']),
                'requires_attachment' => false,
                'signer_name' => 'Kepala Program Studi',
                'signer_position' => 'Kepala Program Studi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Permohonan Transkrip',
                'code' => 'TRANSCRIPT_REQUEST',
                'description' => 'Permohonan dokumen transkrip untuk kebutuhan administrasi.',
                'fulfillment_mode' => 'manual_upload',
                'template_key' => null,
                'requires_financial_clearance' => true,
                'clearance_hold_type' => 'transcript',
                'required_fields' => json_encode(['purpose']),
                'requires_attachment' => false,
                'signer_name' => 'Kepala Biro Akademik',
                'signer_position' => 'Kepala Biro Akademik',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_status_histories');
        Schema::dropIfExists('service_letter_requests');
        Schema::dropIfExists('service_letter_types');
    }
};
