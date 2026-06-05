<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('module')->default('organization');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['module', 'is_active']);
        });

        Schema::create('approval_template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_template_id')->constrained('approval_templates')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('approver_type')->default('permission'); // user, role, permission, position, work_unit
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role')->nullable();
            $table->string('approver_permission')->nullable();
            $table->foreignId('organizational_position_id')->nullable()->constrained('organizational_positions')->nullOnDelete();
            $table->foreignId('work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['approval_template_id', 'step_order'], 'approval_template_step_order_unique');
            $table->index(['approver_type', 'step_order']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_template_id')->nullable()->constrained('approval_templates')->nullOnDelete();
            $table->nullableMorphs('approvable');
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('reference')->nullable();
            $table->string('status')->default('submitted'); // draft, submitted, in_progress, approved, rejected, cancelled
            $table->unsignedInteger('current_step_order')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'current_step_order']);
            $table->index(['requester_user_id', 'status']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_template_step_id')->nullable()->constrained('approval_template_steps')->nullOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('approver_type')->default('permission');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role')->nullable();
            $table->string('approver_permission')->nullable();
            $table->foreignId('organizational_position_id')->nullable()->constrained('organizational_positions')->nullOnDelete();
            $table->foreignId('work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, current, approved, rejected, skipped
            $table->boolean('is_required')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'step_order'], 'approval_request_step_order_unique');
            $table->index(['status', 'step_order']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->nullable()->constrained('approval_steps')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['approval_request_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_template_steps');
        Schema::dropIfExists('approval_templates');
    }
};
