<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TranscriptEntry extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'transcript_entries';

    protected $fillable = [
        'student_profile_id',
        'course_id',
        'student_grade_id',
        'academic_year_id',
        'semester_no',
        'credits',
        'final_score',
        'letter_grade',
        'grade_point',
        'result_status',
        'is_counted_in_gpa',
        'is_best_grade',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_counted_in_gpa' => 'boolean',
            'is_best_grade' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transcript_entry')
            ->logOnly([
                'student_profile_id',
                'course_id',
                'student_grade_id',
                'academic_year_id',
                'semester_no',
                'credits',
                'letter_grade',
                'grade_point',
                'result_status',
                'is_counted_in_gpa',
                'is_best_grade',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function studentGrade(): BelongsTo
    {
        return $this->belongsTo(StudentGrade::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
