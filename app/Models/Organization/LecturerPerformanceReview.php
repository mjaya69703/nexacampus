<?php

namespace App\Models\Organization;

use App\Models\Academic\LecturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerPerformanceReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'edom_score' => 'decimal:2',
            'teaching_compliance_score' => 'decimal:2',
            'attendance_compliance_score' => 'decimal:2',
            'workload_total_sks' => 'decimal:2',
            'final_score' => 'decimal:2',
            'snapshot' => 'array',
            'calculated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function edomPeriod(): BelongsTo
    {
        return $this->belongsTo(EdomPeriod::class);
    }

    public function workloadPeriod(): BelongsTo
    {
        return $this->belongsTo(LecturerWorkloadPeriod::class, 'lecturer_workload_period_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lecturerProfile(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
