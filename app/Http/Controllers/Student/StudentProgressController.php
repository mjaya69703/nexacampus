<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use App\Support\StudentProgressAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan progres akademik mahasiswa.
 *
 * Seluruh agregasi tetap memakai StudentProgressAnalyticsService agar
 * angka pada halaman ini konsisten dengan dashboard dan portal dosen.
 */
class StudentProgressController extends Controller
{
    public function index(Request $request, StudentProgressAnalyticsService $analytics): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with(['user', 'studyProgram.faculty'])->first();

        return Inertia::render('Student/Progress/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Progres Akademik'),
            'hasProfile' => (bool) $profile,
            'progress' => $profile ? $analytics->summarize($profile) : null,
            'urls' => [
                'index' => route('student.progress.index'),
                'studyPlan' => route('student.study-plan.index'),
                'grades' => route('student.grades.index'),
                'transcript' => route('student.transcript.index'),
                'schedule' => route('student.schedule.index'),
                'invoices' => route('student.financial.invoices'),
            ],
        ]);
    }
}
