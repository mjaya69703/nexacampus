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
        Schema::create('student_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('semester_no')->nullable();

            $table->enum('registration_status', [
                'Draft',
                'Submitted',
                'Approved',
                'Rejected',
                'Cancelled',
            ])->default('Draft');

            $table->enum('academic_status', [
                'Aktif',
                'Cuti',
                'Nonaktif',
                'Lulus',
                'Drop Out',
                'Keluar',
            ])->default('Aktif');

            $table->date('registration_date')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            // $table->enum('source', ['Student', 'Admin', 'Import'])->default('Admin');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['student_profile_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_registrations');
    }
};
