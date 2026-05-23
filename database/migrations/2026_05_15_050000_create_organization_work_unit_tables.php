<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('work_unit_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_unit_id')->constrained('work_units')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position')->default('member'); // member, coordinator, head
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['work_unit_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['work_unit_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_unit_user');
        Schema::dropIfExists('work_units');
    }
};
