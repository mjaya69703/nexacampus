<?php

namespace App\Models\Access;

use App\Models\User;
// use App\Models\User\Subrole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity, SoftDeletes;

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('role')
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name',
        'guard_name',
    ];

    protected $hidden = [
        'pivot',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
    }

    // public function subroles()
    // {
    //     return $this->hasMany(Subrole::class);
    // }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
