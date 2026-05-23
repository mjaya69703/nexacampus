<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Organization\WorkUnit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['first_name', 'last_name', 'email', 'username', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $with = ['roles'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'photo',
        'username',
        'phone',
        'instagram',
        'facebook',
        'linkedin',
        'identity_number',
        'religion',
        'blood_type',
        'gender',
        'citizenship',
        'height',
        'weight',
        'place_of_birth',
        'date_of_birth',
        'code',
        'email',
        'password',
        'is_active',
        'fst_setup',
        'tfa_setup',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getRoleAttribute()
    {
        return $this->roles->pluck('name')->join(', ');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getPhotoAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/profile/default.jpg') : asset('storage/images/profile/'.$value);
    }

    public function getNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function hasActivePermission(string $permission): bool
    {
        $activeRole = session('active_role');

        if (! $activeRole) {
            return false;
        }

        return $this->roles()
            ->where('name', $activeRole)
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function lecturerProfile(): HasOne
    {
        return $this->hasOne(LecturerProfile::class);
    }

    public function workUnits(): BelongsToMany
    {
        return $this->belongsToMany(WorkUnit::class, 'work_unit_user')
            ->withPivot(['position', 'is_active'])
            ->withTimestamps();
    }

    public function activeWorkUnits(): BelongsToMany
    {
        return $this->workUnits()->wherePivot('is_active', true);
    }

    // Prefix untuk route names berdasarkan active role
    public function getPrefixAttribute(): string
    {
        $activeRole = session('active_role');

        if (! $activeRole) {
            return '';
        }

        return match ($activeRole) {
            'admin', 'superuser' => 'admin.',
            'student' => 'student.',
            'lecturer' => 'lecturer.',
            default => 'admin.',
        };
    }
}
