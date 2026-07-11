<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('nim')->unique();
            $table->string('full_name');
            $table->date('graduation_date');
            $table->unsignedSmallInteger('graduation_year');
            $table->foreignId('study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('current_city')->nullable();
            $table->string('current_province')->nullable();
            $table->string('employment_status')->default('unemployed');
            $table->string('employer_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('job_industry')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('graduation_year', 'alumni_profile_grad_year_idx');
            $table->index('study_program_id', 'alumni_profile_study_program_idx');
            $table->index('employment_status', 'alumni_profile_employment_idx');
        });

        Schema::create('employer_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_partner_id')->nullable()->constrained('employer_partners')->nullOnDelete();
            $table->string('title');
            $table->string('company_name');
            $table->string('industry')->nullable();
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->string('location')->nullable();
            $table->string('job_type')->nullable();
            $table->string('salary_range')->nullable();
            $table->string('apply_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->date('posted_date');
            $table->date('deadline_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deadline_date'], 'job_posting_active_deadline_idx');
        });

        Schema::create('alumni_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type');
            $table->dateTime('event_date');
            $table->dateTime('end_date')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('meeting_url')->nullable();
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->dateTime('registration_deadline')->nullable();
            $table->string('poster_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'event_date'], 'alumni_event_published_date_idx');
        });

        Schema::create('alumni_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_event_id')->constrained('alumni_events')->cascadeOnDelete();
            $table->foreignId('alumni_profile_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->dateTime('registered_at');
            $table->dateTime('attended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['alumni_event_id', 'alumni_profile_id'], 'event_participant_unique');
        });

        Schema::create('tracer_study_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->json('target_graduation_years')->nullable();
            $table->json('target_study_program_ids')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('questions');
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_responded')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'tracer_campaign_status_idx');
        });

        Schema::create('tracer_study_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracer_study_campaign_id')->constrained('tracer_study_campaigns')->cascadeOnDelete();
            $table->foreignId('alumni_profile_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->json('answers');
            $table->string('employment_status')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('job_relevance')->nullable();
            $table->unsignedSmallInteger('time_to_employment_months')->nullable();
            $table->string('salary_range')->nullable();
            $table->boolean('further_study')->default(false);
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(['tracer_study_campaign_id', 'alumni_profile_id'], 'tracer_response_unique');
            $table->index('employment_status', 'tracer_response_employment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracer_study_responses');
        Schema::dropIfExists('tracer_study_campaigns');
        Schema::dropIfExists('alumni_event_participants');
        Schema::dropIfExists('alumni_events');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('employer_partners');
        Schema::dropIfExists('alumni_profiles');
    }
};
