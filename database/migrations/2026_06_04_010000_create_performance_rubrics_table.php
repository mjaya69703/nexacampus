<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_performance_rubrics', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('edom_weight', 5, 2)->default(40);
            $table->decimal('teaching_weight', 5, 2)->default(30);
            $table->decimal('attendance_weight', 5, 2)->default(20);
            $table->decimal('workload_weight', 5, 2)->default(10);
            $table->unsignedSmallInteger('minimum_responses')->default(3);
            $table->decimal('target_workload_sks', 8, 2)->default(12);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_performance_rubrics');
    }
};
