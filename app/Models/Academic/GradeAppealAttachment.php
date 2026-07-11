<?php

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeAppealAttachment extends Model
{
    protected $fillable = [
        'grade_appeal_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function appeal(): BelongsTo
    {
        return $this->belongsTo(GradeAppeal::class, 'grade_appeal_id');
    }
}
