<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Campus extends Model
{
    use LogsActivity;

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('campus')
            ->logOnly(['name', 'phone', 'faximile', 'whatsapp', 'email_info', 'email_humas', 'domain', 'tahun_akademik_id', 'favicon', 'logo_vertikal', 'logo_horizontal', 'address', 'city', 'province', 'postal_code', 'latitude', 'longitude', 'tiktok', 'linkedin', 'xtwitter', 'facebook', 'instagram'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'campuses';

    protected $fillable = [
        'name',
        'phone',
        'faximile',
        'whatsapp',
        'email_info',
        'email_humas',
        'domain',
        'tahun_akademik_id',
        'favicon',
        'logo_vertikal',
        'logo_horizontal',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'tiktok',
        'linkedin',
        'xtwitter',
        'facebook',
        'instagram',

    ];

    public function getLogoVertikalAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/logo/logo-vertikal.jpg') : asset('storage/images/logo/'.$value);
    }

    public function getLogoHorizontalAttribute($value)
    {
        return $value == 'default.jpg' ? asset('storage/images/logo/logo-horizontal.jpg') : asset('storage/images/logo/'.$value);
    }
}
