<?php

namespace App\Http\Controllers\Home;

use App\Enums\AnnouncementTargetType;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Publication\Agenda;
use App\Models\Publication\Announcement;
use App\Models\Publication\News;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends \App\Http\Controllers\Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $loginUrl = route('auth.signin-index');

        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute)
            ? route($dashboardRoute)
            : route('auth.select-role');

        return Inertia::render('Home/Welcome', [
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'stats' => [
                'studyPrograms' => StudyProgram::where('is_active', true)->count(),
                'admissionOpen' => AdmissionPeriod::where('is_active', true)->where('is_published', true)->exists(),
            ],
            'announcements' => Announcement::published()
                ->where('target_type', AnnouncementTargetType::GLOBAL)
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Announcement $announcement) => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'excerpt' => Str::limit(strip_tags($announcement->content), 140),
                    'publishedAt' => $announcement->published_at?->format('d M Y') ?? '-',
                    'isPinned' => (bool) $announcement->is_pinned,
                ])
                ->values()
                ->all(),
            'agenda' => Agenda::published()
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->upcoming()
                ->orderBy('event_date')
                ->orderBy('event_time')
                ->limit(3)
                ->get()
                ->map(fn (Agenda $agenda) => [
                    'id' => $agenda->id,
                    'title' => $agenda->title,
                    'detail' => $agenda->location ?: 'Agenda kampus',
                    'date' => $agenda->event_date?->format('d') ?? '--',
                    'month' => strtoupper($agenda->event_date?->format('M') ?? '---'),
                ])
                ->values()
                ->all(),
            'news' => News::published()
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn (News $news) => [
                    'id' => $news->id,
                    'title' => $news->title,
                    'excerpt' => $news->excerpt ?: Str::limit(strip_tags($news->content), 140),
                    'publishedAt' => $news->published_at?->format('d M Y') ?? '-',
                ])
                ->values()
                ->all(),
            'user' => PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => $loginUrl,
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ]);
    }
}
