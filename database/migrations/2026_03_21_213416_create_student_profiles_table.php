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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('study_program_id')
                ->constrained('study_programs')
                ->restrictOnDelete();

            $table->foreignId('entry_academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            $table->string('nim')->unique();
            $table->year('entry_year')->nullable();

            $table->enum('academic_status', [
                'Aktif',
                'Cuti',
                'Lulus',
                'Drop Out',
                'Nonaktif',
                'Keluar',
            ])->default('Aktif');

            $table->date('entry_date')->nullable();
            $table->date('graduation_date')->nullable();

            $table->unsignedTinyInteger('current_semester')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('desc')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
