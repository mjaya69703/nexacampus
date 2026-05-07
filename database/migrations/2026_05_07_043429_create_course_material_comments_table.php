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
        Schema::create('course_material_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')
                ->constrained('course_materials')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('course_material_comments')
                ->cascadeOnUpdate()
                ->nullOnDelete(); // If parent deleted, replies become top-level
            $table->text('content');
            $table->integer('likes_count')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            
            $table->index('course_material_id');
            $table->index('parent_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_material_comments');
    }
};
