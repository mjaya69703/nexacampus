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
        Schema::create('lecturer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained('faculties')
                ->nullOnDelete();

            $table->foreignId('study_program_id')
                ->nullable()
                ->constrained('study_programs')
                ->nullOnDelete();

            $table->string('nidn')->nullable()->unique();
            $table->string('nidk')->nullable()->unique();
            $table->string('nip')->nullable()->unique();

            $table->enum('employment_status', ['Tetap', 'Kontrak', 'Tidak Tetap', 'Tamu'])
                ->default('Tetap');

            $table->date('join_date')->nullable();
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
        Schema::dropIfExists('lecturer_profiles');
    }
};
