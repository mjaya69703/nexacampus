<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_event_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_event_id')->constrained('student_events')->cascadeOnDelete();
            $table->string('category', 20)->default('photo');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_event_id', 'category'], 'student_event_documents_event_category_index');
        });

        Schema::create('student_event_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_event_id')->constrained('student_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('organization_rating')->nullable();
            $table->unsignedTinyInteger('benefit_rating')->nullable();
            $table->text('feedback')->nullable();
            $table->text('suggestions')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_event_id', 'user_id'], 'student_event_evaluation_unique');
            $table->index(['student_event_id', 'rating'], 'student_event_evaluations_event_rating_index');
        });

        Schema::create('student_event_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_event_id')->constrained('student_events')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('category', 30)->default('other');
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->decimal('realized_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_event_id', 'category'], 'student_event_budgets_event_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_event_budgets');
        Schema::dropIfExists('student_event_evaluations');
        Schema::dropIfExists('student_event_documents');
    }
};
