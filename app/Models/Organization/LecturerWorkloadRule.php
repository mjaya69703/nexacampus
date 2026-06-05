<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LecturerWorkloadRule extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sks_value' => 'decimal:2',
            'maximum_sks' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
