<?php

namespace App\Models\StudentService;

use App\Models\Organization\WorkUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentComplaintCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'default_work_unit_id',
        'description',
        'default_sla_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_sla_hours' => 'integer',
        ];
    }

    public function defaultWorkUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'default_work_unit_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(StudentComplaint::class);
    }
}
