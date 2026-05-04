<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('course_schedule_id')
                ->nullable()
                ->constrained('course_schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('lecturer_profile_id')
                ->nullable()
                ->constrained('lecturer_profiles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedSmallInteger('meeting_no')->nullable();
            $table->date('meeting_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('topic')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['Draft', 'Opened', 'Closed', 'Cancelled'])
                ->default('Draft');

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['course_offering_id', 'meeting_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
