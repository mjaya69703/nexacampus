<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('graduation_document_requirements')) {
            Schema::create('graduation_document_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->string('document_type');
                $table->string('label');
                $table->boolean('is_required')->default(true);
                $table->string('allowed_extensions')->nullable();
                $table->unsignedInteger('max_size_kb')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['study_program_id', 'document_type'], 'grad_doc_req_scope_type_unique');
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('graduation_documents')) {
            Schema::create('graduation_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('graduation_application_id')->constrained('graduation_applications')->cascadeOnDelete();
                $table->foreignId('graduation_document_requirement_id')->nullable()->constrained('graduation_document_requirements')->nullOnDelete();
                $table->string('document_type');
                $table->string('file_path');
                $table->string('file_name');
                $table->unsignedInteger('file_size');
                $table->string('verification_status')->default('pending'); // pending, verified, rejected
                $table->text('verification_notes')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->unique(['graduation_application_id', 'graduation_document_requirement_id'], 'grad_docs_app_req_unique');
                $table->index(['graduation_application_id', 'verification_status'], 'grad_docs_app_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_documents');
        Schema::dropIfExists('graduation_document_requirements');
    }
};
