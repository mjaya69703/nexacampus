<?php

namespace App\Models\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentScholarship extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'student_profile_id',
        'scholarship_id',
        'academic_year_id',
        'semester',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_scholarship')
            ->logOnly(['student_profile_id', 'scholarship_id', 'academic_year_id', 'semester', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function matchesInvoice(StudentInvoice $invoice): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->academic_year_id && $this->academic_year_id !== $invoice->academic_year_id) {
            return false;
        }

        if ($this->semester && $this->semester !== $invoice->semester) {
            return false;
        }

        return $this->student_profile_id === $invoice->student_profile_id;
    }
}
