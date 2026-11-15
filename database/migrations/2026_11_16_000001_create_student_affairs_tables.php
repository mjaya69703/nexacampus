<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30);
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->date('founded_at')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('instagram_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index('faculty_id');
            $table->index('study_program_id');
        });

        Schema::create('student_organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_organization_id')
                ->constrained('student_organizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('position', 30);
            $table->string('period', 20);
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_organization_id', 'user_id', 'period'], 'student_org_member_unique');
            $table->index(['user_id', 'is_active']);
            $table->index(['student_organization_id', 'is_active']);
        });

        Schema::create('student_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_organization_id')
                ->nullable()
                ->constrained('student_organizations')
                ->nullOnDelete();
            $table->string('title');
            $table->string('category', 30);
            $table->string('level', 30);
            $table->string('rank', 30);
            $table->date('achieved_at');
            $table->string('organizer')->nullable();
            $table->text('description')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('certificate_original_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index(['level', 'category']);
            $table->index('achieved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('student_organization_members');
        Schema::dropIfExists('student_organizations');
    }
};
