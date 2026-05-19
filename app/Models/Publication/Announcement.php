<?php

namespace App\Models\Publication;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Announcement extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'announcements';

    protected $fillable = [
        'target_type',
        'target_id',
        'created_by',
        'title',
        'content',
        'priority',
        'is_pinned',
        'is_published',
        'published_at',
        'scheduled_at',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'attachment_size',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'target_type'     => AnnouncementTargetType::class,
            'priority'        => AnnouncementPriority::class,
            'is_pinned'       => 'boolean',
            'is_published'    => 'boolean',
            'published_at'    => 'datetime',
            'scheduled_at'    => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('announcement')
            ->logOnly(['title', 'target_type', 'target_id', 'priority', 'is_pinned', 'is_published', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isReadBy(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    public function markAsReadBy(int $userId): void
    {
        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $this->id, 'user_id' => $userId],
            ['read_at' => now()]
        );
    }

    public function getFormattedAttachmentSizeAttribute(): string
    {
        if (! $this->attachment_size) {
            return '-';
        }

        $bytes = $this->attachment_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    // ─── Static: Student targeting ────────────────────────────────────────────

    /**
     * Get published announcements targeted to a student.
     */
    public static function queryForStudent(User $student)
    {
        $studentProfile = $student->studentProfile;

        if (! $studentProfile) {
            return static::whereRaw('0=1');
        }

        $facultyId      = $studentProfile->studyProgram?->faculty_id;
        $studyProgramId = $studentProfile->study_program_id;

        return static::published()
            ->where(function ($query) use ($studentProfile, $facultyId, $studyProgramId) {
                // Global
                $query->where(function ($q) {
                    $q->where('target_type', AnnouncementTargetType::GLOBAL->value)
                        ->whereNull('target_id');
                });

                // Faculty
                if ($facultyId) {
                    $query->orWhere(function ($q) use ($facultyId) {
                        $q->where('target_type', AnnouncementTargetType::FACULTY->value)
                            ->where('target_id', $facultyId);
                    });
                }

                // Study program
                if ($studyProgramId) {
                    $query->orWhere(function ($q) use ($studyProgramId) {
                        $q->where('target_type', AnnouncementTargetType::STUDY_PROGRAM->value)
                            ->where('target_id', $studyProgramId);
                    });
                }

                // Course offerings (enrolled)
                $query->orWhere(function ($q) use ($studentProfile) {
                    $q->where('target_type', AnnouncementTargetType::COURSE_OFFERING->value)
                        ->whereIn('target_id', function ($sub) use ($studentProfile) {
                            $sub->select('course_offering_id')
                                ->from('study_plan_details')
                                ->join('study_plans', 'study_plan_details.study_plan_id', '=', 'study_plans.id')
                                ->where('study_plans.student_profile_id', $studentProfile->id);
                        });
                });

                // Personal
                $query->orWhere(function ($q) use ($studentProfile) {
                    $q->where('target_type', AnnouncementTargetType::STUDENT->value)
                        ->where('target_id', $studentProfile->id);
                });
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }

    /**
     * Count unread announcements for a student.
     */
    public static function unreadCountForStudent(User $student): int
    {
        return static::queryForStudent($student)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $student->id))
            ->count();
    }

    // ─── Static: Lecturer targeting ───────────────────────────────────────────

    /**
     * Get published announcements visible to a lecturer:
     * - Global announcements
     * - Announcements targeted specifically to this lecturer profile
     * - Announcements for course offerings they teach
     */
    public static function queryForLecturer(User $lecturer)
    {
        $lecturerProfile = $lecturer->lecturerProfile;

        return static::published()
            ->where(function ($query) use ($lecturer, $lecturerProfile) {
                // Global
                $query->where(function ($q) {
                    $q->where('target_type', AnnouncementTargetType::GLOBAL->value)
                        ->whereNull('target_id');
                });

                // Targeted to this lecturer profile
                if ($lecturerProfile) {
                    $query->orWhere(function ($q) use ($lecturerProfile) {
                        $q->where('target_type', AnnouncementTargetType::LECTURER->value)
                            ->where('target_id', $lecturerProfile->id);
                    });

                    // Course offerings they teach
                    $query->orWhere(function ($q) use ($lecturerProfile) {
                        $q->where('target_type', AnnouncementTargetType::COURSE_OFFERING->value)
                            ->whereIn('target_id', function ($sub) use ($lecturerProfile) {
                                $sub->select('course_offering_id')
                                    ->from('course_offering_lecturers')
                                    ->where('lecturer_profile_id', $lecturerProfile->id)
                                    ->where('is_active', true);
                            });
                    });
                }
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }

    /**
     * Count unread announcements for a lecturer.
     */
    public static function unreadCountForLecturer(User $lecturer): int
    {
        return static::queryForLecturer($lecturer)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $lecturer->id))
            ->count();
    }
}
