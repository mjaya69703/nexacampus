<?php

namespace App\Models\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InvoiceSchedule extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'invoice_kind',
        'generation_mode',
        'student_profile_id',
        'student_profile_ids',
        'academic_year_id',
        'semester',
        'invoice_type',
        'items',
        'due_date',
        'publish_at',
        'issue_immediately',
        'status',
        'is_active',
        'created_count',
        'skipped_count',
        'failed_count',
        'last_result',
        'last_run_at',
        'completed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'student_profile_ids' => 'array',
            'items' => 'array',
            'last_result' => 'array',
            'due_date' => 'date',
            'publish_at' => 'datetime',
            'issue_immediately' => 'boolean',
            'is_active' => 'boolean',
            'created_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'last_run_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoice_schedule')
            ->logOnly(['name', 'invoice_kind', 'generation_mode', 'publish_at', 'status', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
