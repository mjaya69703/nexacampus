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
        Schema::create('curriculums', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_program_id')
                ->constrained('study_programs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable();

            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();

            $table->boolean('is_active')->default(false);
            $table->text('desc')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['study_program_id', 'name']);
            $table->unique(['study_program_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculums');
    }
};
