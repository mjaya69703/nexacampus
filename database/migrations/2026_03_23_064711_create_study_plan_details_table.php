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
        Schema::create('study_plan_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_plan_id')
                ->constrained('study_plans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('credits')->nullable();
            $table->boolean('is_repeat')->default(false);

            $table->enum('status', [
                'Draft',
                'Taken',
                'Dropped',
                'Cancelled',
            ])->default('Draft');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['study_plan_id', 'course_offering_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_plan_details');
    }
};
