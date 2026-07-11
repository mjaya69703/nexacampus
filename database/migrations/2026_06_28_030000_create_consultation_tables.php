<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consultation_slots')) {
            Schema::create('consultation_slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->cascadeOnDelete();
                $table->string('title')->default('Konsultasi Akademik');
                $table->unsignedTinyInteger('weekday');
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('slot_minutes')->default(30);
                $table->unsignedSmallInteger('capacity')->default(1);
                $table->string('consultation_mode', 20)->default('in_person');
                $table->string('location')->nullable();
                $table->string('meeting_link')->nullable();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['lecturer_profile_id', 'weekday', 'is_active'], 'consultation_slots_lecturer_day_active_idx');
            });
        }

        if (! Schema::hasTable('consultation_appointments')) {
            Schema::create('consultation_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consultation_slot_id')->constrained('consultation_slots')->cascadeOnDelete();
                $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->cascadeOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
                $table->date('appointment_date');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('status', 30)->default('requested');
                $table->string('topic');
                $table->text('student_notes')->nullable();
                $table->text('lecturer_notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['lecturer_profile_id', 'appointment_date', 'status'], 'consultation_appt_lecturer_date_status_idx');
                $table->index(['student_profile_id', 'appointment_date', 'status'], 'consultation_appt_student_date_status_idx');
                $table->unique(['consultation_slot_id', 'student_profile_id', 'starts_at'], 'consultation_appt_student_slot_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_appointments');
        Schema::dropIfExists('consultation_slots');
    }
};
