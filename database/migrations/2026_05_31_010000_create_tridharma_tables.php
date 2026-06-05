<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tridharma_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->nullable()->constrained('lecturer_profiles')->nullOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('type'); // research, community_service, publication
            $table->string('title');
            $table->string('scheme')->nullable();
            $table->text('abstract')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('funding_amount', 14, 2)->default(0);
            $table->string('funding_source')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->index(['type', 'status']);
            $table->index(['is_verified', 'status']);
        });

        Schema::create('tridharma_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tridharma_record_id')->constrained('tridharma_records')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_name')->nullable();
            $table->string('institution')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('member');
            $table->boolean('is_external')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tridharma_record_id', 'role']);
        });

        Schema::create('tridharma_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tridharma_record_id')->constrained('tridharma_records')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tridharma_record_id', 'status']);
        });

        Schema::create('tridharma_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tridharma_record_id')->constrained('tridharma_records')->cascadeOnDelete();
            $table->string('category');
            $table->string('description')->nullable();
            $table->decimal('planned_amount', 14, 2)->default(0);
            $table->decimal('realized_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tridharma_record_id', 'category']);
        });

        Schema::create('tridharma_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tridharma_record_id')->constrained('tridharma_records')->cascadeOnDelete();
            $table->string('output_type');
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('indexing')->nullable();
            $table->string('doi')->nullable();
            $table->string('url')->nullable();
            $table->date('published_at')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tridharma_record_id', 'output_type']);
            $table->index(['status', 'published_at']);
        });

        Schema::create('tridharma_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tridharma_record_id')->constrained('tridharma_records')->cascadeOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('document_type')->default('evidence');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tridharma_record_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tridharma_attachments');
        Schema::dropIfExists('tridharma_outputs');
        Schema::dropIfExists('tridharma_budgets');
        Schema::dropIfExists('tridharma_milestones');
        Schema::dropIfExists('tridharma_members');
        Schema::dropIfExists('tridharma_records');
    }
};
