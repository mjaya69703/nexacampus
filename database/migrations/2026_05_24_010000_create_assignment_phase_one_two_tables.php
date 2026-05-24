<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_offering_id')->constrained('course_offerings')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->longText('description')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->decimal('max_score', 8, 2)->default(100);
                $table->json('allowed_file_types')->nullable();
                $table->unsignedInteger('max_file_size_kb')->default(10240);
                $table->boolean('allow_text_submission')->default(true);
                $table->boolean('allow_file_submission')->default(true);
                $table->boolean('allow_resubmission')->default(true);
                $table->boolean('accept_late_submission')->default(true);
                $table->boolean('is_published')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['course_offering_id', 'due_at']);
                $table->index(['is_published', 'published_at']);
            });
        } else {
            Schema::table('assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('assignments', 'due_at')) {
                    $table->dateTime('due_at')->nullable()->after('description');
                }

                if (! Schema::hasColumn('assignments', 'max_file_size_kb')) {
                    $table->unsignedInteger('max_file_size_kb')->default(10240)->after('allowed_file_types');
                }

                if (! Schema::hasColumn('assignments', 'allow_text_submission')) {
                    $table->boolean('allow_text_submission')->default(true)->after('max_file_size_kb');
                }

                if (! Schema::hasColumn('assignments', 'allow_file_submission')) {
                    $table->boolean('allow_file_submission')->default(true)->after('allow_text_submission');
                }

                if (! Schema::hasColumn('assignments', 'accept_late_submission')) {
                    $table->boolean('accept_late_submission')->default(true)->after('allow_resubmission');
                }
            });

            if (Schema::hasColumn('assignments', 'due_date')) {
                DB::table('assignments')
                    ->whereNull('due_at')
                    ->update(['due_at' => DB::raw('due_date')]);
            }

            if (Schema::hasColumn('assignments', 'max_file_size_mb')) {
                DB::table('assignments')
                    ->whereNull('max_file_size_kb')
                    ->orWhere('max_file_size_kb', 10240)
                    ->update(['max_file_size_kb' => DB::raw('GREATEST(max_file_size_mb, 1) * 1024')]);
            }

            if (Schema::hasColumn('assignments', 'allow_late_submission')) {
                DB::table('assignments')->update(['accept_late_submission' => DB::raw('allow_late_submission')]);
            }
        }

        if (! Schema::hasTable('assignment_files')) {
            Schema::create('assignment_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_type', 30)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assignment_submissions')) {
            Schema::create('assignment_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
                $table->longText('content')->nullable();
                $table->string('status')->default('submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('last_resubmitted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['assignment_id', 'student_profile_id']);
                $table->index(['status', 'submitted_at']);
            });
        } else {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                if (! Schema::hasColumn('assignment_submissions', 'content')) {
                    $table->longText('content')->nullable()->after('student_profile_id');
                }

                if (! Schema::hasColumn('assignment_submissions', 'last_resubmitted_at')) {
                    $table->timestamp('last_resubmitted_at')->nullable()->after('submitted_at');
                }
            });

            if (Schema::hasColumn('assignment_submissions', 'submission_text')) {
                DB::table('assignment_submissions')
                    ->whereNull('content')
                    ->update(['content' => DB::raw('submission_text')]);
            }
        }

        if (! Schema::hasTable('assignment_submission_files')) {
            Schema::create('assignment_submission_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_type', 30)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('assignment_submission_files', function (Blueprint $table) {
                if (! Schema::hasColumn('assignment_submission_files', 'assignment_submission_id')) {
                    $table->unsignedBigInteger('assignment_submission_id')->nullable()->after('id');
                }
            });

            if (Schema::hasColumn('assignment_submission_files', 'submission_id')) {
                DB::table('assignment_submission_files')
                    ->whereNull('assignment_submission_id')
                    ->update(['assignment_submission_id' => DB::raw('submission_id')]);
            }
        }

        if (! Schema::hasTable('assignment_grades')) {
            Schema::create('assignment_grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_submission_id')->unique()->constrained('assignment_submissions')->cascadeOnDelete();
                $table->decimal('score', 8, 2)->nullable();
                $table->longText('feedback')->nullable();
                $table->string('status')->default('graded');
                $table->timestamp('graded_at')->nullable();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('returned_at')->nullable();
                $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->longText('return_note')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'graded_at']);
            });
        }

        if (Schema::hasColumn('assignment_submissions', 'score')) {
            DB::statement('
                insert into assignment_grades (assignment_submission_id, score, feedback, status, graded_at, graded_by, created_at, updated_at)
                select id, score, feedback, case when score is null then "graded" else "graded" end, graded_at, graded_by, now(), now()
                from assignment_submissions
                where score is not null or feedback is not null or graded_at is not null
                on duplicate key update score = values(score), feedback = values(feedback), status = values(status), graded_at = values(graded_at), graded_by = values(graded_by), updated_at = values(updated_at)
            ');
        }

        if (! Schema::hasTable('assignment_status_histories')) {
            Schema::create('assignment_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->nullable()->constrained('assignments')->cascadeOnDelete();
                $table->foreignId('assignment_submission_id')->nullable()->constrained('assignment_submissions')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status');
                $table->text('note')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['assignment_id', 'status'], 'assignment_hist_assignment_status_idx');
                $table->index(['assignment_submission_id', 'status'], 'assignment_hist_submission_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_status_histories');
        Schema::dropIfExists('assignment_grades');
        Schema::dropIfExists('assignment_submission_files');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignment_files');
        Schema::dropIfExists('assignments');
    }
};
