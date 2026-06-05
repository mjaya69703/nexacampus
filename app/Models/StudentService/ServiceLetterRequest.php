<?php

namespace App\Models\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceLetterRequest extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'request_number',
        'service_letter_type_id',
        'student_profile_id',
        'purpose',
        'request_data',
        'attachment_path',
        'attachment_name',
        'status',
        'fulfillment_method',
        'generated_file_path',
        'uploaded_file_path',
        'uploaded_file_name',
        'student_notes',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'issued_by',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('service_letter_request')
            ->logOnly(['request_number', 'service_letter_type_id', 'student_profile_id', 'status', 'fulfillment_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(ServiceLetterType::class, 'service_letter_type_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'issued' && filled($this->generated_file_path ?: $this->uploaded_file_path);
    }

    public function finalFilePath(): ?string
    {
        return $this->uploaded_file_path ?: $this->generated_file_path;
    }
}
