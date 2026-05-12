<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NimGenerationRule extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'pattern',
        'sequence_scope',
        'sequence_padding',
        'sequence_start',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence_padding' => 'integer',
            'sequence_start' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('nim_generation_rule')
            ->logOnly(['name', 'pattern', 'sequence_scope', 'sequence_padding', 'sequence_start', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function counters(): HasMany
    {
        return $this->hasMany(NimSequenceCounter::class);
    }
}
