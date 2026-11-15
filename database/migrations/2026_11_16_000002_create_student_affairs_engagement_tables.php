<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->foreignId('student_organization_id')
                ->nullable()
                ->constrained('student_organizations')
                ->nullOnDelete();
            $table->string('organizer')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('registration_deadline')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->integer('points')->default(0);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->string('cover_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at'], 'student_events_status_starts_index');
            $table->index(['category', 'status'], 'student_events_category_status_index');
        });

        Schema::create('student_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_event_id')->constrained('student_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('registered');
            $table->dateTime('registered_at');
            $table->dateTime('attended_at')->nullable();
            $table->integer('points_awarded')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_event_id', 'user_id'], 'student_event_registration_unique');
            $table->index(['user_id', 'status'], 'student_event_registrations_user_status_index');
        });

        Schema::create('student_point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points');
            $table->string('category', 30);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description');
            $table->date('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'awarded_at'], 'student_point_entries_user_awarded_index');
            $table->index(['source_type', 'source_id'], 'student_point_entries_source_index');
        });

        Schema::table('room_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('student_organization_id')->nullable()->after('user_id');
            $table->index('student_organization_id', 'room_reservations_student_org_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('room_reservations', function (Blueprint $table) {
                $table->foreign('student_organization_id', 'room_reservations_student_org_foreign')
                    ->references('id')
                    ->on('student_organizations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('room_reservations', function (Blueprint $table) {
                $table->dropForeign('room_reservations_student_org_foreign');
            });
        }

        Schema::table('room_reservations', function (Blueprint $table) {
            $table->dropIndex('room_reservations_student_org_index');
            $table->dropColumn('student_organization_id');
        });

        Schema::dropIfExists('student_point_entries');
        Schema::dropIfExists('student_event_registrations');
        Schema::dropIfExists('student_events');
    }
};
