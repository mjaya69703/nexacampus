<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class System extends Model
{
    use LogsActivity;

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('system')
            ->logOnly(['app_name', 'app_version', 'app_description', 'app_url', 'app_email', 'app_favicon', 'app_logo_vertikal', 'app_logo_horizontal', 'maintenance_mode', 'enable_captcha', 'max_login_attempts', 'login_decay_seconds'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'systems';

    protected $fillable = [
        'app_name',
        'app_version',
        'app_description',
        'app_url',
        'app_email',
        'app_favicon',
        'app_logo_vertikal',
        'app_logo_horizontal',
        'maintenance_mode',
        'enable_captcha',
        'max_login_attempts',
        'login_decay_seconds',
    ];

    public function getAppFaviconAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/logo/logo-vertikal.jpg') : asset('storage/images/logo/'.$value);
    }

    public function getAppLogoVertikalAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/logo/logo-vertikal.jpg') : asset('storage/images/logo/'.$value);
    }

    public function getAppLogoHorizontalAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/logo/logo-horizontal.jpg') : asset('storage/images/logo/'.$value);
    }
}
