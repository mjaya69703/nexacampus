<?php

namespace App\Models\Campus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Building extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('building')
            ->logOnly(['name', 'code', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'campus_location_id',
        'name',
        'code',
        'address',
        'floor_count',
        'is_active',
        'desc',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'floor_count' => 'integer',
        ];
    }

    public function campusLocation(): BelongsTo
    {
        return $this->belongsTo(CampusLocation::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
