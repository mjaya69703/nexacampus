<?php

namespace App\Models\Financial;

use App\Models\Academic\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCreditBalance extends Model
{
    protected $fillable = [
        'student_profile_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StudentCreditTransaction::class, 'student_profile_id', 'student_profile_id');
    }
}
