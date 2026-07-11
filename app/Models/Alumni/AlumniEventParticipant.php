<?php

namespace App\Models\Alumni;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniEventParticipant extends Model
{
    protected $fillable = [
        'alumni_event_id',
        'alumni_profile_id',
        'registered_at',
        'attended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AlumniEvent::class, 'alumni_event_id');
    }

    public function alumniProfile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class);
    }
}
