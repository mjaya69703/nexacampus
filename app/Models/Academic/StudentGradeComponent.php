<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentGradeComponent extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'student_grade_components';

    protected $fillable = [
        'student_grade_id',
        'name',
        'weight_percentage',
        'score',
        'notes',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:2',
            'score' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_grade_component')
            ->logOnly(['student_grade_id', 'name', 'weight_percentage', 'score', 'sort_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentGrade(): BelongsTo
    {
        return $this->belongsTo(StudentGrade::class);
    }
}
