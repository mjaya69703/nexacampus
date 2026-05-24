<?php

namespace App\Models\Academic;

use App\Models\Financial\FinancialHold;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentCreditTransaction;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentProfile extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'student_profiles';

    protected $fillable = [
        'user_id',
        'study_program_id',
        'entry_academic_year_id',
        'nim',
        'entry_year',
        'academic_status',
        'entry_date',
        'graduation_date',
        'current_semester',
        'class_type',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'graduation_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student_profile')
            ->logOnly(['nim', 'academic_status', 'current_semester', 'class_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function entryAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'entry_academic_year_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(StudentRegistration::class);
    }

    public function studyResults(): HasMany
    {
        return $this->hasMany(StudyResult::class);
    }

    public function transcriptEntries(): HasMany
    {
        return $this->hasMany(TranscriptEntry::class);
    }

    public function advisorAssignments(): HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }

    public function studyPlans(): HasMany
    {
        return $this->hasMany(StudyPlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function financialHolds(): HasMany
    {
        return $this->hasMany(FinancialHold::class);
    }

    public function creditBalance(): HasOne
    {
        return $this->hasOne(StudentCreditBalance::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(StudentCreditTransaction::class);
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(StudentScholarship::class);
    }

    public function serviceLetterRequests(): HasMany
    {
        return $this->hasMany(ServiceLetterRequest::class);
    }

    public function graduationApplications(): HasMany
    {
        return $this->hasMany(GraduationApplication::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(StudentLeaveApplication::class);
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(StudentTransferRequest::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
