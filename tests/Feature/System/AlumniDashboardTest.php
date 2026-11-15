<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function alumniDashboardSetup(): array
{
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    Role::firstOrCreate(['name' => 'alumni', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('alumni');

    $facultyId = DB::table('faculties')->insertGetId([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);
    $programId = DB::table('study_programs')->insertGetId([
        'faculty_id' => $facultyId, 'name' => 'Teknik Informatika', 'code' => 'TI',
        'degree' => 'S1', 'is_active' => true,
    ]);

    $profileId = DB::table('alumni_profiles')->insertGetId([
        'user_id' => $user->id,
        'nim' => '2019TI0001',
        'full_name' => $user->name,
        'graduation_date' => now()->subYear()->toDateString(),
        'graduation_year' => now()->subYear()->year,
        'study_program_id' => $programId,
        'faculty_id' => $facultyId,
        'gpa' => 3.5,
        'email' => $user->email,
        'phone' => '081234567890',
        'current_city' => 'Jakarta',
        'employment_status' => 'working',
        'employer_name' => 'PT Contoh',
        'job_title' => 'Engineer',
        'linkedin_url' => 'https://linkedin.com/in/contoh',
        'is_active' => true,
    ]);

    return compact('user', 'profileId', 'programId');
}

it('redirects guests away from the alumni dashboard to login', function () {
    alumniDashboardSetup();

    $this->get('/alumni/dashboard')->assertRedirect('/auth/login');
});

it('renders the empty state without an alumni profile', function () {
    alumniDashboardSetup();

    $plain = User::factory()->create();
    $plain->assignRole('alumni');

    $this->actingAs($plain)
        ->withSession(['active_role' => 'alumni'])
        ->get('/alumni/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Dashboard')
            ->where('hasProfile', false));
});

it('renders the alumni dashboard with career, feeds, and tracer states', function () {
    $setup = alumniDashboardSetup();
    $user = $setup['user'];

    $eventId = DB::table('alumni_events')->insertGetId([
        'title' => 'Reuni Akbar',
        'event_type' => 'alumni_meet',
        'event_date' => now()->addWeek(),
        'location' => 'Kampus Utama',
        'is_published' => true,
    ]);
    DB::table('alumni_event_participants')->insert([
        'alumni_event_id' => $eventId,
        'alumni_profile_id' => $setup['profileId'],
        'registered_at' => now(),
        'attended_at' => now()->subDay(),
    ]);
    $upcomingId = DB::table('alumni_events')->insertGetId([
        'title' => 'Webinar Karir',
        'event_type' => 'webinar',
        'event_date' => now()->addMonth(),
        'is_online' => true,
        'is_published' => true,
    ]);
    DB::table('alumni_event_participants')->insert([
        'alumni_event_id' => $upcomingId,
        'alumni_profile_id' => $setup['profileId'],
        'registered_at' => now(),
        'attended_at' => null,
    ]);

    DB::table('job_postings')->insert([
        'title' => 'Backend Developer',
        'company_name' => 'PT Contoh',
        'description' => 'Lowongan backend.',
        'job_type' => 'full-time',
        'posted_date' => now()->toDateString(),
        'deadline_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $activeCampaign = DB::table('tracer_study_campaigns')->insertGetId([
        'title' => 'Tracer 2026',
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'questions' => json_encode([]),
        'status' => 'active',
    ]);
    $doneCampaign = DB::table('tracer_study_campaigns')->insertGetId([
        'title' => 'Tracer 2025',
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->subMonths(6)->toDateString(),
        'questions' => json_encode([]),
        'status' => 'closed',
    ]);
    DB::table('tracer_study_responses')->insert([
        'tracer_study_campaign_id' => $doneCampaign,
        'alumni_profile_id' => $setup['profileId'],
        'answers' => json_encode([]),
        'submitted_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_role' => 'alumni'])
        ->get('/alumni/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Dashboard')
            ->where('hasProfile', true)
            ->where('stats.eventsJoined', 2)
            ->where('stats.eventsAttended', 1)
            ->where('stats.tracerFilled', 1)
            ->where('stats.completeness', 100)
            ->has('jobs.items', 1)
            ->has('events.items', 2)
            ->has('tracerActive', 1)
            ->has('tracerDone', 1)
            ->where('career.statusLabel', 'Bekerja'));
});
