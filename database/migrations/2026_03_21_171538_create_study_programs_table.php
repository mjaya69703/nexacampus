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
        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained('faculties')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('short_name', 50)->nullable();
            $table->enum('degree', ['D3', 'D4', 'S1', 'S2', 'S3']);
            $table->string('prefix_degree')->nullable();
            $table->string('suffix_degree')->nullable();
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
        Schema::dropIfExists('study_programs');
    }
};
