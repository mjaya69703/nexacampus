<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LecturerPerformanceRubric extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'edom_weight' => 'decimal:2',
            'teaching_weight' => 'decimal:2',
            'attendance_weight' => 'decimal:2',
            'workload_weight' => 'decimal:2',
            'target_workload_sks' => 'decimal:2',
            'minimum_responses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): self
    {
        return static::query()->where('is_active', true)->latest('updated_at')->first()
            ?: static::query()->create([
                'code' => 'DEFAULT',
                'name' => 'Rubrik Performa Standar',
                'edom_weight' => 40,
                'teaching_weight' => 30,
                'attendance_weight' => 20,
                'workload_weight' => 10,
                'minimum_responses' => 3,
                'target_workload_sks' => 12,
                'is_active' => true,
            ]);
    }

    public function totalWeight(): float
    {
        return (float) $this->edom_weight
            + (float) $this->teaching_weight
            + (float) $this->attendance_weight
            + (float) $this->workload_weight;
    }
}
