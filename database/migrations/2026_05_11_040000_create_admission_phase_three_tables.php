<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nim_generation_rules')) {
            Schema::create('nim_generation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('pattern');
                $table->string('sequence_scope')->default('study_program_year');
                $table->unsignedTinyInteger('sequence_padding')->default(4);
                $table->unsignedInteger('sequence_start')->default(1);
                $table->boolean('is_active')->default(false);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'sequence_scope'], 'nim_rule_active_scope_idx');
            });
        }

        if (! Schema::hasTable('nim_sequence_counters')) {
            Schema::create('nim_sequence_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nim_generation_rule_id')->constrained('nim_generation_rules')->cascadeOnDelete();
                $table->string('scope_key');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['nim_generation_rule_id', 'scope_key'], 'nim_counter_rule_scope_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nim_sequence_counters');
        Schema::dropIfExists('nim_generation_rules');
    }
};
