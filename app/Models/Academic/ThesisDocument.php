<?php

namespace App\Models\Academic;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThesisDocument extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'thesis_id',
        'uploaded_by',
        'category',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'version',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('thesis_document')
            ->logOnly(['thesis_id', 'category', 'title', 'file_name', 'version'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return Thesis::documentCategoryLabel((string) $this->category);
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }
}
