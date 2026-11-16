<?php

use App\Enums\EventBudgetCategory;
use App\Enums\EventDocumentCategory;
use App\Enums\EventRegistrationStatus;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Models\Settings\System;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventBudget;
use App\Models\StudentAffairs\StudentEventDocument;
use App\Models\StudentAffairs\StudentEventEvaluation;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function studentAffairsSliceThreeSetup(): array
{
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['admin', 'student', 'viewer'] as $role) {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    foreach (studentAffairsSliceThreePermissions() as $permission) {
        SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    SpatieRole::where('name', 'admin')->firstOrFail()
        ->givePermissionTo(studentAffairsSliceThreePermissions());

    $admin = User::factory()->create([
        'first_name' => 'Ayu',
        'last_name' => 'Kemahasiswaan',
        'username' => 'ayu.kemahasiswaan.slice3',
    ]);
    $admin->assignRole('admin');

    $studentUser = User::factory()->create([
        'first_name' => 'Rania',
        'last_name' => 'Putri',
        'username' => 'rania.putri.slice3',
    ]);
    $studentUser->assignRole('student');

    $otherStudent = User::factory()->create([
        'first_name' => 'Nadia',
        'last_name' => 'Senior',
        'username' => 'nadia.senior.slice3',
    ]);
    $otherStudent->assignRole('student');

    $viewer = User::factory()->create([
        'first_name' => 'Vera',
        'last_name' => 'Pengamat',
        'username' => 'vera.pengamat.slice3',
    ]);
    $viewer->assignRole('viewer');

    return compact('admin', 'studentUser', 'otherStudent', 'viewer');
}

/**
 * @return array<int, string>
 */
function studentAffairsSliceThreePermissions(): array
{
    $permissions = [];

    foreach (['viewAny', 'create', 'update', 'delete', 'view', 'publish'] as $action) {
        $permissions[] = "student-event.{$action}";
    }

    return $permissions;
}

function actingSliceThreeAdmin(User $admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function actingSliceThreeStudent(User $student)
{
    return test()->actingAs($student)->withSession(['active_role' => 'student']);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeSliceThreeEvent(array $overrides = []): StudentEvent
{
    return StudentEvent::create(array_merge([
        'title' => 'Event Uji '.fake()->unique()->numerify('###'),
        'slug' => 'event-uji-'.fake()->unique()->numerify('###'),
        'category' => StudentEventCategory::SEMINAR->value,
        'description' => 'Event uji kemahasiswaan.',
        'organizer' => 'BEM Uji',
        'location' => 'Aula Uji',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHours(3),
        'registration_deadline' => now()->subDays(5),
        'quota' => 50,
        'points' => 10,
        'status' => StudentEventStatus::COMPLETED->value,
        'is_featured' => false,
    ], $overrides));
}

function makeSliceThreeRegistration(StudentEvent $event, User $user, string $status = 'attended'): StudentEventRegistration
{
    return StudentEventRegistration::create([
        'student_event_id' => $event->id,
        'user_id' => $user->id,
        'status' => $status,
        'registered_at' => now()->subDays(6),
        'attended_at' => $status === EventRegistrationStatus::ATTENDED->value ? now()->subDays(2) : null,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeSliceThreeEvaluation(StudentEvent $event, User $user, array $overrides = []): StudentEventEvaluation
{
    return StudentEventEvaluation::create(array_merge([
        'student_event_id' => $event->id,
        'user_id' => $user->id,
        'rating' => 5,
        'organization_rating' => 5,
        'benefit_rating' => 5,
        'feedback' => 'Kegiatan berjalan baik.',
        'suggestions' => 'Pertahankan kualitas acara.',
        'is_anonymous' => false,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeSliceThreeBudget(StudentEvent $event, array $overrides = []): StudentEventBudget
{
    return StudentEventBudget::create(array_merge([
        'student_event_id' => $event->id,
        'item_name' => 'Konsumsi '.fake()->unique()->numerify('###'),
        'category' => EventBudgetCategory::CONSUMPTION->value,
        'planned_amount' => 1000000,
        'realized_amount' => 900000,
        'notes' => 'Anggaran uji.',
    ], $overrides));
}

it('menampilkan ringkasan dokumentasi anggaran dan evaluasi pada detail event admin', function () {
    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent(['title' => 'Event Lengkap']);

    makeSliceThreeRegistration($event, $setup['studentUser']);
    makeSliceThreeRegistration($event, $setup['otherStudent']);

    makeSliceThreeEvaluation($event, $setup['studentUser'], ['rating' => 5, 'feedback' => 'Sangat baik']);
    makeSliceThreeEvaluation($event, $setup['otherStudent'], [
        'rating' => 4,
        'is_anonymous' => true,
        'feedback' => 'Cukup baik',
    ]);

    makeSliceThreeBudget($event, ['item_name' => 'Konsumsi', 'planned_amount' => 1000000, 'realized_amount' => 800000]);
    makeSliceThreeBudget($event, ['item_name' => 'Transportasi', 'planned_amount' => 500000, 'realized_amount' => 600000]);

    actingSliceThreeAdmin($setup['admin'])
        ->get(route('admin.student-affairs.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Events/Show')
            ->has('documents', 0)
            ->has('budgets', 2)
            ->where('evaluationSummary.count', 2)
            ->where('evaluationSummary.average', 4.5)
            ->where('evaluationSummary.responseRate', 100)
            ->where('evaluations.0.isAnonymous', true)
            ->where('evaluations.0.student', 'Anonim')
            ->where('budgetSummary.items', 2)
            ->where('budgetSummary', fn ($summary) => (float) $summary['planned'] === 1500000.0
                && (float) $summary['realized'] === 1400000.0
                && (float) $summary['variance'] === 100000.0)
            ->where('options.budgetCategories.consumption', 'Konsumsi'));
});

it('mengunggah mengunduh dan menghapus dokumentasi event', function () {
    Storage::fake('public');

    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent(['status' => StudentEventStatus::PUBLISHED->value]);

    actingSliceThreeAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.documents.store', $event->id), [
            'category' => EventDocumentCategory::REPORT->value,
            'title' => 'Laporan Kegiatan',
            'description' => 'Laporan pertanggungjawaban kegiatan.',
            'file' => UploadedFile::fake()->create('laporan.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = StudentEventDocument::firstOrFail();

    expect($document)
        ->student_event_id->toBe($event->id)
        ->category->toBe(EventDocumentCategory::REPORT)
        ->original_name->toBe('laporan.pdf')
        ->uploaded_by->toBe($setup['admin']->id);

    Storage::disk('public')->assertExists($document->file_path);

    actingSliceThreeAdmin($setup['admin'])
        ->get(route('admin.student-affairs.events.documents.download', [$event->id, $document->id]))
        ->assertOk();

    actingSliceThreeAdmin($setup['admin'])
        ->get(route('admin.student-affairs.events.show', $event->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.title', 'Laporan Kegiatan')
            ->where('documents.0.categoryLabel', 'Laporan Kegiatan'));

    actingSliceThreeAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.events.documents.destroy', [$event->id, $document->id]))
        ->assertRedirect();

    expect(StudentEventDocument::find($document->id))->toBeNull();
    expect(StudentEventDocument::withTrashed()->find($document->id))->not->toBeNull();
    Storage::disk('public')->assertMissing($document->file_path);
});

it('menghapus event ikut membersihkan berkas dokumentasi', function () {
    Storage::fake('public');

    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent();

    actingSliceThreeAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.documents.store', $event->id), [
            'category' => EventDocumentCategory::PHOTO->value,
            'title' => 'Foto Kegiatan',
            'file' => UploadedFile::fake()->create('foto.jpg', 80, 'image/jpeg'),
        ])
        ->assertRedirect();

    $document = StudentEventDocument::firstOrFail();
    Storage::disk('public')->assertExists($document->file_path);

    actingSliceThreeAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.events.destroy', $event->id))
        ->assertRedirect(route('admin.student-affairs.events.index'));

    Storage::disk('public')->assertMissing($document->file_path);
});

it('mengelola realisasi anggaran event', function () {
    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent();

    actingSliceThreeAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.budgets.store', $event->id), [
            'item_name' => 'Honor Pembicara',
            'category' => EventBudgetCategory::SPEAKER->value,
            'planned_amount' => 2500000,
            'realized_amount' => 2000000,
            'notes' => 'Dibayarkan setelah acara.',
        ])
        ->assertRedirect();

    $budget = StudentEventBudget::firstOrFail();

    expect($budget)
        ->item_name->toBe('Honor Pembicara')
        ->category->toBe(EventBudgetCategory::SPEAKER)
        ->planned_amount->toBe('2500000.00')
        ->realized_amount->toBe('2000000.00');

    actingSliceThreeAdmin($setup['admin'])
        ->put(route('admin.student-affairs.events.budgets.update', [$event->id, $budget->id]), [
            'item_name' => 'Honor Pembicara',
            'category' => EventBudgetCategory::SPEAKER->value,
            'planned_amount' => 3000000,
            'realized_amount' => 2750000,
            'notes' => 'Realisasi diperbarui.',
        ])
        ->assertRedirect();

    expect($budget->refresh())
        ->planned_amount->toBe('3000000.00')
        ->realized_amount->toBe('2750000.00');

    actingSliceThreeAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.budgets.store', $event->id), [
            'item_name' => 'Anggaran Salah',
            'category' => EventBudgetCategory::OTHER->value,
            'planned_amount' => -100,
        ])
        ->assertSessionHasErrors('planned_amount');

    actingSliceThreeAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.events.budgets.destroy', [$event->id, $budget->id]))
        ->assertRedirect();

    expect(StudentEventBudget::find($budget->id))->toBeNull();
    expect(StudentEventBudget::withTrashed()->find($budget->id))->not->toBeNull();
});

it('mahasiswa yang hadir dapat mengirim evaluasi sekali', function () {
    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent(['title' => 'Bakti Sosial Uji']);

    makeSliceThreeRegistration($event, $setup['studentUser']);

    actingSliceThreeStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.evaluate', $event->id), [
            'rating' => 5,
            'organization_rating' => 4,
            'benefit_rating' => 5,
            'feedback' => 'Kegiatan sangat bermanfaat.',
            'suggestions' => 'Tambah durasi kegiatan.',
            'is_anonymous' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $evaluation = StudentEventEvaluation::firstOrFail();

    expect($evaluation)
        ->student_event_id->toBe($event->id)
        ->user_id->toBe($setup['studentUser']->id)
        ->rating->toBe(5)
        ->organization_rating->toBe(4)
        ->is_anonymous->toBeTrue();

    actingSliceThreeStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.evaluate', $event->id), [
            'rating' => 3,
        ])
        ->assertRedirect()
        ->assertSessionHas('info');

    expect(StudentEventEvaluation::where('student_event_id', $event->id)->count())->toBe(1);

    actingSliceThreeStudent($setup['studentUser'])
        ->get(route('student.student-affairs.events'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudentAffairs/Events')
            ->where('events.0.id', $event->id)
            ->where('events.0.canEvaluate', false)
            ->where('events.0.myEvaluation.rating', 5));
});

it('menolak evaluasi dari bukan peserta hadir atau event yang belum selesai', function () {
    $setup = studentAffairsSliceThreeSetup();
    $completed = makeSliceThreeEvent(['title' => 'Event Selesai']);
    makeSliceThreeRegistration($completed, $setup['studentUser']);

    actingSliceThreeStudent($setup['otherStudent'])
        ->post(route('student.student-affairs.events.evaluate', $completed->id), ['rating' => 5])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(StudentEventEvaluation::count())->toBe(0);

    $published = makeSliceThreeEvent([
        'title' => 'Event Berjalan',
        'status' => StudentEventStatus::PUBLISHED->value,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(3),
        'registration_deadline' => now()->addDay(),
    ]);
    makeSliceThreeRegistration($published, $setup['studentUser']);

    actingSliceThreeStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.evaluate', $published->id), ['rating' => 5])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(StudentEventEvaluation::count())->toBe(0);

    actingSliceThreeStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.evaluate', $completed->id), ['rating' => 0])
        ->assertSessionHasErrors('rating');
});

it('hanya menampilkan event selesai yang dihadiri pada portal mahasiswa', function () {
    $setup = studentAffairsSliceThreeSetup();

    $attended = makeSliceThreeEvent(['title' => 'Event Dihadiri', 'starts_at' => now()->subDays(10)]);
    makeSliceThreeRegistration($attended, $setup['studentUser']);

    $registeredOnly = makeSliceThreeEvent(['title' => 'Event Hanya Terdaftar', 'starts_at' => now()->subDays(9)]);
    makeSliceThreeRegistration($registeredOnly, $setup['studentUser'], EventRegistrationStatus::REGISTERED->value);

    $otherAttended = makeSliceThreeEvent(['title' => 'Event Orang Lain', 'starts_at' => now()->subDays(8)]);
    makeSliceThreeRegistration($otherAttended, $setup['otherStudent']);

    $published = makeSliceThreeEvent([
        'title' => 'Event Terbuka',
        'status' => StudentEventStatus::PUBLISHED->value,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(3),
        'registration_deadline' => now()->addDays(2),
    ]);

    actingSliceThreeStudent($setup['studentUser'])
        ->get(route('student.student-affairs.events'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudentAffairs/Events')
            ->has('events', 2)
            ->where('events.0.id', $attended->id)
            ->where('events.0.canEvaluate', true)
            ->where('events.1.id', $published->id));
});

it('menolak akses fitur dokumentasi dan anggaran tanpa permission', function () {
    $setup = studentAffairsSliceThreeSetup();
    $event = makeSliceThreeEvent();

    actingSliceThreeAdmin($setup['viewer'])
        ->get(route('admin.student-affairs.events.show', $event->id))
        ->assertForbidden();

    actingSliceThreeAdmin($setup['viewer'])
        ->post(route('admin.student-affairs.events.documents.store', $event->id), [
            'category' => EventDocumentCategory::PHOTO->value,
            'title' => 'Foto',
            'file' => UploadedFile::fake()->create('foto.jpg', 50, 'image/jpeg'),
        ])
        ->assertForbidden();

    actingSliceThreeAdmin($setup['viewer'])
        ->post(route('admin.student-affairs.events.budgets.store', $event->id), [
            'item_name' => 'Konsumsi',
            'category' => EventBudgetCategory::CONSUMPTION->value,
            'planned_amount' => 100000,
        ])
        ->assertForbidden();
});
