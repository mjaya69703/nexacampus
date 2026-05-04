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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('short_name', 100)->nullable();

            $table->unsignedTinyInteger('credits');
            $table->unsignedTinyInteger('semester_recommendation')->nullable();

            $table->enum('requirement_type', ['Wajib', 'Pilihan'])->default('Wajib');
            $table->enum('category_type', ['Umum', 'MKWU', 'MKU', 'Keilmuan', 'Praktikum', 'Tugas Akhir', 'Magang'])
                ->default('Keilmuan');

            $table->boolean('is_active')->default(true);
            $table->text('desc')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
