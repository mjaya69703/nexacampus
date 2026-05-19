<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admission_periods')) {
            Schema::create('admission_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->year('academic_year');
                $table->unsignedTinyInteger('wave')->default(1);
                $table->date('opens_at');
                $table->date('closes_at');
                $table->boolean('is_active')->default(false);
                $table->boolean('is_published')->default(false);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'is_published'], 'adm_period_active_pub_idx');
                $table->index(['opens_at', 'closes_at'], 'adm_period_dates_idx');
            });
        }

        if (! Schema::hasTable('admission_applications')) {
            Schema::create('admission_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
                $table->string('application_number')->unique();
                $table->string('access_token', 80)->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('full_name');
                $table->string('email');
                $table->string('phone');
                $table->date('birth_date');
                $table->enum('gender', ['male', 'female']);
                $table->text('address');
                $table->string('emergency_contact_name');
                $table->string('emergency_contact_phone');
                $table->string('high_school_name');
                $table->string('high_school_major');
                $table->year('high_school_graduation_year');
                $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
                $table->string('class_type')->nullable();
                $table->enum('status', ['draft', 'submitted', 'under_review', 'accepted', 'rejected', 'waitlisted'])->default('submitted');
                $table->decimal('final_score', 6, 2)->nullable();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['admission_period_id', 'status'], 'adm_app_period_status_idx');
                $table->index(['faculty_id', 'study_program_id'], 'adm_app_faculty_program_idx');
                $table->index(['status', 'created_at'], 'adm_app_status_created_idx');
                $table->index(['email', 'phone'], 'adm_app_email_phone_idx');
            });
        }

        if (! Schema::hasTable('admission_document_requirements')) {
            Schema::create('admission_document_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_period_id')->nullable()->constrained('admission_periods')->cascadeOnDelete();
                $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
                $table->string('document_type');
                $table->string('label');
                $table->boolean('is_required')->default(true);
                $table->string('allowed_extensions')->nullable();
                $table->integer('max_size_kb')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['admission_period_id', 'study_program_id'], 'adm_doc_req_period_program_idx');
                $table->unique(['admission_period_id', 'study_program_id', 'document_type'], 'adm_doc_req_unique');
            });
        }

        if (! Schema::hasTable('admission_documents')) {
            Schema::create('admission_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->foreignId('document_requirement_id')->nullable()->constrained('admission_document_requirements')->nullOnDelete();
                $table->string('document_type');
                $table->string('file_path');
                $table->string('file_name');
                $table->integer('file_size');
                $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
                $table->text('verification_notes')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->index(['admission_application_id', 'verification_status'], 'adm_doc_app_status_idx');
            });
        }

        if (! Schema::hasTable('admission_status_histories')) {
            Schema::create('admission_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->text('notes')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['admission_application_id', 'created_at'], 'adm_status_app_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_status_histories');
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admission_document_requirements');
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('admission_periods');
    }
};
