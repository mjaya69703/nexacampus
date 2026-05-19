<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialClearancePolicy extends Model
{
    use LogsActivity;

    protected $fillable = [
        'invoice_type',
        'hold_type',
        'mode',
        'grace_days',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'grace_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('financial_clearance_policy')
            ->logOnly(['invoice_type', 'hold_type', 'mode', 'grace_days', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function holds(): HasMany
    {
        return $this->hasMany(FinancialHold::class);
    }
}
