<?php

namespace App\Models\Campus;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FacilityMaintenanceTicket extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('facility_maintenance_ticket')
            ->logOnly(['ticket_number', 'title', 'priority', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'room_id',
        'campus_asset_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'priority',
        'status',
        'resolution_notes',
        'repair_cost',
        'resolved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'repair_cost' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function campusAsset(): BelongsTo
    {
        return $this->belongsTo(CampusAsset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
