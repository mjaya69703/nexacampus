<?php

namespace App\Models\StudentService;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationStatusHistory extends Model
{
    protected $fillable = [
        'graduation_application_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(GraduationApplication::class, 'graduation_application_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
