<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Scholarship extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'discount_type',
        'discount_percentage',
        'fixed_amount',
        'duration_semesters',
        'requirements',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'duration_semesters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('scholarship')
            ->logOnly(['name', 'type', 'discount_type', 'discount_percentage', 'fixed_amount', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentScholarship::class);
    }

    public function calculateDiscount(float $amount): float
    {
        if (! $this->is_active) {
            return 0;
        }

        if ($this->discount_type === 'fixed') {
            return min($amount, max(0, (float) $this->fixed_amount));
        }

        return min($amount, $amount * max(0, (float) $this->discount_percentage) / 100);
    }
}
