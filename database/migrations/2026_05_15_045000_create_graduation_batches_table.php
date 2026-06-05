<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->date('yudisium_date')->nullable();
            $table->string('sk_number')->nullable();
            $table->date('sk_date')->nullable();
            $table->string('status')->default('draft'); // draft, open, review, finalized, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_period_id', 'status'], 'grad_batches_period_status_idx');
            $table->index(['study_program_id', 'status'], 'grad_batches_program_status_idx');
        });

        Schema::table('graduation_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('graduation_applications', 'graduation_batch_id')) {
                $table->foreignId('graduation_batch_id')
                    ->nullable()
                    ->after('academic_period_id')
                    ->constrained('graduation_batches')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('graduation_applications', function (Blueprint $table) {
            if (Schema::hasColumn('graduation_applications', 'graduation_batch_id')) {
                $table->dropConstrainedForeignId('graduation_batch_id');
            }
        });

        Schema::dropIfExists('graduation_batches');
    }
};
