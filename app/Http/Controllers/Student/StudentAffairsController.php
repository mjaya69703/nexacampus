<?php

namespace App\Http\Controllers\Student;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentAffairsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $memberships = StudentOrganizationMember::with([
            'organization.faculty:id,name,short_name',
            'organization.studyProgram:id,name',
            'organization.advisor:id,first_name,last_name',
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('is_active')
            ->orderByDesc('period')
            ->get()
            ->map(fn (StudentOrganizationMember $member) => [
                'id' => $member->id,
                'organizationId' => $member->student_organization_id,
                'organizationName' => $member->organization?->name ?? '-',
                'organizationType' => $member->organization?->type?->label(),
                'organizationTypeShort' => $member->organization?->type?->shortLabel(),
                'organizationTypeTone' => $member->organization?->type?->tone() ?? 'gray',
                'faculty' => $member->organization?->faculty?->short_name,
                'studyProgram' => $member->organization?->studyProgram?->name,
                'advisor' => $member->organization?->advisor?->name,
                'position' => $member->position->value,
                'positionLabel' => $member->position->label(),
                'positionTone' => $member->position->tone(),
                'period' => $member->period,
                'joinedAt' => $member->joined_at?->format('d M Y'),
                'leftAt' => $member->left_at?->format('d M Y'),
                'isActive' => (bool) $member->is_active,
                'notes' => $member->notes,
            ]);

        $achievements = StudentAchievement::with('organization:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->get()
            ->map(fn (StudentAchievement $achievement) => [
                'id' => $achievement->id,
                'title' => $achievement->title,
                'organization' => $achievement->organization?->name,
                'category' => $achievement->category->value,
                'categoryLabel' => $achievement->category->label(),
                'categoryTone' => $achievement->category->tone(),
                'level' => $achievement->level->value,
                'levelLabel' => $achievement->level->label(),
                'levelTone' => $achievement->level->tone(),
                'rankLabel' => $achievement->rank->label(),
                'rankTone' => $achievement->rank->tone(),
                'achievedAt' => $achievement->achieved_at?->format('d M Y'),
                'organizer' => $achievement->organizer,
                'description' => $achievement->description,
                'status' => $achievement->status->value,
                'statusLabel' => $achievement->status->label(),
                'statusTone' => $achievement->status->tone(),
                'rejectionReason' => $achievement->rejection_reason,
                'isFeatured' => (bool) $achievement->is_featured,
                'certificateUrl' => $achievement->certificate_path
                    ? route('student.student-affairs.certificate', $achievement->id)
                    : null,
                'certificateName' => $achievement->certificate_original_name,
            ]);

        $organizations = StudentOrganization::withCount('activeMembers')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (StudentOrganization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'typeLabel' => $organization->type->label(),
                'typeShort' => $organization->type->shortLabel(),
                'typeTone' => $organization->type->tone(),
                'description' => $organization->description,
                'activeMembersCount' => (int) $organization->active_members_count,
                'logoUrl' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
            ]);

        return Inertia::render('Student/StudentAffairs/Index', [
            'shell' => ShellProps::make($user, 'Kemahasiswaan', 'Organisasi & Prestasi'),
            'student' => [
                'name' => $user->name,
                'nim' => $user->studentProfile?->nim,
                'program' => $user->studentProfile?->studyProgram?->name,
                'semester' => $user->studentProfile?->current_semester,
            ],
            'stats' => [
                'activeMemberships' => $memberships->where('isActive', true)->count(),
                'memberships' => $memberships->count(),
                'achievements' => $achievements->count(),
                'verified' => $achievements->where('status', AchievementStatus::VERIFIED->value)->count(),
                'organizations' => StudentOrganization::where('is_active', true)->count(),
            ],
            'memberships' => $memberships->values(),
            'achievements' => $achievements->values(),
            'organizations' => $organizations,
            'options' => [
                'categories' => AchievementCategory::options(),
                'levels' => AchievementLevel::options(),
                'ranks' => AchievementRank::options(),
                'statuses' => AchievementStatus::options(),
                'positions' => MemberPosition::options(),
                'organizationTypes' => OrganizationType::options(),
            ],
            'urls' => [
                'publicOrganizations' => route('root.kemahasiswaan.organisasi'),
                'publicAchievements' => route('root.kemahasiswaan.prestasi'),
            ],
        ]);
    }

    public function downloadCertificate(Request $request, int $id)
    {
        $achievement = StudentAchievement::where('user_id', $request->user()->id)->findOrFail($id);

        if (blank($achievement->certificate_path) || ! Storage::disk('public')->exists($achievement->certificate_path)) {
            abort(404);
        }

        return Storage::disk('public')->download(
            $achievement->certificate_path,
            $achievement->certificate_original_name ?: 'sertifikat-prestasi'
        );
    }
}
