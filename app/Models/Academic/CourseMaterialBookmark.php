<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMaterialBookmark extends Model
{
    protected $fillable = [
        'course_material_id',
        'student_profile_id',
    ];

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_profile_id');
    }
}
