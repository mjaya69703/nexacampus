<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->unsignedTinyInteger('minimum_semester')->default(8);
            $table->unsignedSmallInteger('minimum_passed_credits')->default(144);
            $table->decimal('minimum_gpa', 3, 2)->default(2.00);
            $table->boolean('require_active_status')->default(true);
            $table->boolean('require_no_financial_hold')->default(true);
            $table->boolean('require_no_incomplete_grade')->default(true);
            $table->boolean('require_open_yudisium_period')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'study_program_id'], 'graduation_policy_active_program_idx');
        });

        DB::table('graduation_policies')->insert([
            'name' => 'Default Yudisium Policy',
            'study_program_id' => null,
            'minimum_semester' => 8,
            'minimum_passed_credits' => 144,
            'minimum_gpa' => 2.00,
            'require_active_status' => true,
            'require_no_financial_hold' => true,
            'require_no_incomplete_grade' => true,
            'require_open_yudisium_period' => true,
            'is_active' => true,
            'description' => 'Default global yudisium eligibility policy. Adjust from dashboard for each campus or study program.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_policies');
    }
};
