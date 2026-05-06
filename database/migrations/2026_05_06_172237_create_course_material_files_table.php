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
        Schema::create('course_material_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')
                ->constrained('course_materials')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->integer('file_size')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamps();

            $table->index('course_material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_material_files');
    }
};
