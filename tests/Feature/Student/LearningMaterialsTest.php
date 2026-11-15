<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialComment;
use App\Models\Academic\CourseMaterialFile;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Menyiapkan mahasiswa dengan dua kelas aktif, satu kelas yang sudah
 * dibatalkan, dan satu kelas milik program studi lain. Materi disiapkan pada
 * setiap kelas agar filter enrollment bisa diuji.
 *
 * @return array<string, mixed>
 */
function learningSetup(): array
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

    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT-LRN', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI-LRN',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $otherProgram = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Sistem Informasi',
        'code' => 'SI-LRN',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $year = AcademicYear::create([
        'name' => '2026/2027',
        'code' => '2026-LRN',
        'semester' => 'Ganjil',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'is_active' => true,
    ]);

    $user = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Materi']);
    $user->assignRole('student');

    $profile = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => '2026TI0201',
        'academic_status' => 'Aktif',
        'entry_date' => now()->subYear()->toDateString(),
        'current_semester' => 3,
        'is_active' => true,
    ]);

    $registration = StudentRegistration::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'semester_no' => 3,
        'registration_status' => 'Approved',
        'academic_status' => 'Aktif',
        'registration_date' => now()->subDays(5)->toDateString(),
        'submitted_at' => now()->subDays(4),
        'approved_at' => now()->subDays(3),
        'is_active' => true,
    ]);

    $plan = StudyPlan::create([
        'student_profile_id' => $profile->id,
        'academic_year_id' => $year->id,
        'student_registration_id' => $registration->id,
        'semester_no' => 3,
        'status' => 'Draft',
        'created_by' => $user->id,
    ]);

    $offerings = [];
    $materials = [];

    $definitions = [
        ['course' => ['TI311', 'Pemrograman Web', 3], 'offering' => 'A', 'semester' => 3, 'detail' => 'Taken', 'program' => $program, 'published' => true],
        ['course' => ['TI312', 'Basis Data Terdistribusi', 3], 'offering' => 'B', 'semester' => 3, 'detail' => 'Taken', 'program' => $program, 'published' => true],
        ['course' => ['TI313', 'Jaringan Lanjut', 3], 'offering' => 'C', 'semester' => 3, 'detail' => 'Dropped', 'program' => $program, 'published' => true],
        ['course' => ['SI321', 'Sistem Enterprise', 3], 'offering' => 'X', 'semester' => 3, 'detail' => null, 'program' => $otherProgram, 'published' => true],
    ];

    foreach ($definitions as $definition) {
        [$code, $name, $credits] = $definition['course'];

        $course = Course::create([
            'code' => $code,
            'name' => $name,
            'credits' => $credits,
            'semester_recommendation' => $definition['semester'],
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
        ]);

        $offering = CourseOffering::create([
            'academic_year_id' => $year->id,
            'study_program_id' => $definition['program']->id,
            'course_id' => $course->id,
            'label' => $definition['offering'],
            'code' => $code.'-'.$definition['offering'],
            'semester_no' => $definition['semester'],
            'capacity' => 40,
            'credits' => $credits,
            'is_required' => true,
            'delivery_mode' => 'Offline',
            'status' => 'Open',
        ]);

        if ($definition['detail'] !== null) {
            StudyPlanDetail::create([
                'study_plan_id' => $plan->id,
                'course_offering_id' => $offering->id,
                'credits' => $credits,
                'status' => $definition['detail'],
            ]);
        }

        $material = CourseMaterial::create([
            'course_offering_id' => $offering->id,
            'uploaded_by' => $user->id,
            'title' => 'Materi '.$name,
            'description' => '<p>Ringkasan materi '.$name.'</p>',
            'file_path' => 'materials/'.$code.'.pdf',
            'file_name' => $code.'.pdf',
            'file_type' => 'pdf',
            'file_size' => 2048,
            'category' => 'lecture_notes',
            'meeting_number' => 1,
            'is_published' => $definition['published'],
        ]);

        CourseMaterialFile::create([
            'course_material_id' => $material->id,
            'file_path' => 'materials/'.$code.'-slide.pdf',
            'file_name' => $code.'-slide.pdf',
            'file_type' => 'pdf',
            'file_size' => 4096,
            'download_count' => 2,
        ]);

        $offerings[$definition['offering']] = $offering;
        $materials[$definition['offering']] = $material;
    }

    // Materi draf pada kelas yang sama tidak boleh bocor ke mahasiswa.
    $unpublished = CourseMaterial::create([
        'course_offering_id' => $offerings['A']->id,
        'uploaded_by' => $user->id,
        'title' => 'Draf Materi Rahasia',
        'file_path' => 'materials/draft.pdf',
        'file_name' => 'draft.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024,
        'category' => 'lecture_notes',
        'is_published' => false,
    ]);

    return compact('user', 'profile', 'program', 'otherProgram', 'year', 'plan', 'offerings', 'materials', 'unpublished');
}

function learningGet(User $user, string $routeName, array $params = [], array $query = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get(route($routeName, $params + $query));
}

function learningPost(User $user, string $routeName, array $params = [], array $payload = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post(route($routeName, $params), $payload);
}

it('hanya menampilkan materi dari kelas yang aktif di KRS', function () {
    $setup = learningSetup();

    learningGet($setup['user'], 'student.course-materials.index')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Learning/Materials')
            ->where('hasProfile', true)
            ->has('materials', 2)
            ->where('stats.total', 2)
            ->where('stats.courses', 2)
            ->where('stats.withFiles', 2)
            ->has('courses', 2)
            ->where('materials', function ($materials) {
                return collect($materials)->pluck('title')->doesntContain('Draf Materi Rahasia');
            }));
});

it('mengabaikan materi kelas yang detail KRS-nya dibatalkan', function () {
    $setup = learningSetup();

    $response = learningGet($setup['user'], 'student.course-materials.index');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('materials', function ($materials) {
            $titles = collect($materials)->pluck('title')->sort()->values()->all();

            return $titles === [
                'Materi Basis Data Terdistribusi',
                'Materi Pemrograman Web',
            ];
        }));
});

it('mendukung filter offering baru maupun offeringId lama', function () {
    $setup = learningSetup();

    learningGet($setup['user'], 'student.course-materials.index', [], ['offering' => $setup['offerings']['A']->id])
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 1)
            ->where('materials.0.title', 'Materi Pemrograman Web')
            ->where('filters.offeringId', $setup['offerings']['A']->id));

    learningGet($setup['user'], 'student.course-materials.index', [], ['offeringId' => $setup['offerings']['B']->id])
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 1)
            ->where('materials.0.title', 'Materi Basis Data Terdistribusi'));

    // Kelas milik program studi lain tidak boleh membocorkan materi.
    learningGet($setup['user'], 'student.course-materials.index', [], ['offering' => $setup['offerings']['X']->id])
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 2)
            ->where('filters.offeringId', null));
});

it('menampilkan detail materi beserta lampiran', function () {
    $setup = learningSetup();

    learningGet($setup['user'], 'student.learning.show', ['material' => $setup['materials']['A']->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Learning/Show')
            ->where('material.title', 'Materi Pemrograman Web')
            ->where('material.bookmarked', false)
            ->where('material.liked', false)
            ->has('attachments', 1)
            ->where('attachments.0.fileName', 'TI311-slide.pdf')
            ->where('comments.total', 0));

    learningGet($setup['user'], 'student.learning.show', ['material' => $setup['unpublished']->id])
        ->assertNotFound();

    learningGet($setup['user'], 'student.learning.show', ['material' => $setup['materials']['X']->id])
        ->assertForbidden();
});

it('menolak kelas yang tidak diambil pada halaman materi kelas', function () {
    $setup = learningSetup();

    learningGet($setup['user'], 'student.course-materials.offering', ['offering' => $setup['offerings']['A']->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 1)
            ->where('filters.lockedOffering', true));

    learningGet($setup['user'], 'student.course-materials.offering', ['offering' => $setup['offerings']['X']->id])
        ->assertForbidden();
});

it('mengubah bookmark dan like materi', function () {
    $setup = learningSetup();
    $material = $setup['materials']['A'];

    learningPost($setup['user'], 'student.learning.bookmark', ['material' => $material->id])
        ->assertSessionHas('success');

    expect($material->fresh()->bookmarks()->count())->toBe(1);

    learningGet($setup['user'], 'student.learning.show', ['material' => $material->id])
        ->assertInertia(fn (Assert $page) => $page->where('material.bookmarked', true));

    learningPost($setup['user'], 'student.learning.bookmark', ['material' => $material->id])
        ->assertSessionHas('success');

    expect($material->fresh()->bookmarks()->count())->toBe(0);

    learningPost($setup['user'], 'student.learning.like', ['material' => $material->id])
        ->assertSessionHas('success');

    expect($material->fresh()->likes_count)->toBe(1);

    learningPost($setup['user'], 'student.learning.like', ['material' => $material->id])
        ->assertSessionHas('success');

    expect($material->fresh()->likes_count)->toBe(0);

    learningPost($setup['user'], 'student.learning.bookmark', ['material' => $setup['materials']['X']->id])
        ->assertForbidden();
});

it('menambahkan komentar dan balasan pada diskusi materi', function () {
    $setup = learningSetup();
    $material = $setup['materials']['A'];

    learningPost($setup['user'], 'student.learning.comments.store', ['material' => $material->id], [
        'content' => 'Apakah materi ini tersedia dalam bentuk video?',
    ])->assertSessionHas('success');

    $comment = CourseMaterialComment::query()->first();
    expect($comment)->not->toBeNull()
        ->and($comment->parent_id)->toBeNull()
        ->and($comment->content)->toContain('video');

    learningPost($setup['user'], 'student.learning.comments.store', ['material' => $material->id], [
        'content' => 'Video akan diunggah minggu depan.',
        'parent_id' => $comment->id,
    ])->assertSessionHas('success');

    $reply = CourseMaterialComment::query()->whereNotNull('parent_id')->first();
    expect($reply)->not->toBeNull()
        ->and($reply->parent_id)->toBe($comment->id);

    // Balasan tidak boleh bertingkat lebih dari satu.
    learningPost($setup['user'], 'student.learning.comments.store', ['material' => $material->id], [
        'content' => 'Balasan bertingkat',
        'parent_id' => $reply->id,
    ])->assertSessionHasErrors('content');

    expect(CourseMaterialComment::query()->count())->toBe(2);

    learningGet($setup['user'], 'student.learning.show', ['material' => $material->id])
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.total', 1)
            ->has('comments.items', 1)
            ->has('comments.items.0.replies', 1)
            ->where('comments.items.0.canEdit', true)
            ->where('comments.items.0.replies.0.content', 'Video akan diunggah minggu depan.'));
});

it('membatasi edit dan hapus komentar hanya untuk pemiliknya', function () {
    $setup = learningSetup();
    $material = $setup['materials']['A'];

    $comment = CourseMaterialComment::create([
        'course_material_id' => $material->id,
        'user_id' => $setup['user']->id,
        'content' => 'Komentar awal',
        'likes_count' => 0,
    ]);

    $other = User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Lain']);
    $other->assignRole('student');

    test()->actingAs($other)
        ->withSession(['active_role' => 'student'])
        ->patch(route('student.learning.comments.update', $comment->id), ['content' => 'Diubah orang lain'])
        ->assertForbidden();

    test()->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->patch(route('student.learning.comments.update', $comment->id), ['content' => 'Komentar revisi'])
        ->assertSessionHas('success');

    $comment->refresh();
    expect($comment->content)->toBe('Komentar revisi')
        ->and($comment->is_edited)->toBeTrue()
        ->and($comment->edited_at)->not->toBeNull();

    $reply = CourseMaterialComment::create([
        'course_material_id' => $material->id,
        'user_id' => $setup['user']->id,
        'parent_id' => $comment->id,
        'content' => 'Balasan yang ikut terhapus',
        'likes_count' => 0,
    ]);

    test()->actingAs($setup['user'])
        ->withSession(['active_role' => 'student'])
        ->delete(route('student.learning.comments.destroy', $comment->id))
        ->assertSessionHas('success');

    expect(CourseMaterialComment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(CourseMaterialComment::query()->whereKey($reply->id)->exists())->toBeFalse();
});

it('mengubah like komentar dan menolak komentar pada materi asing', function () {
    $setup = learningSetup();
    $material = $setup['materials']['A'];

    $comment = CourseMaterialComment::create([
        'course_material_id' => $material->id,
        'user_id' => $setup['user']->id,
        'content' => 'Komentar untuk di-like',
        'likes_count' => 0,
    ]);

    learningPost($setup['user'], 'student.learning.comments.like', ['comment' => $comment->id])
        ->assertSessionHas('success');

    expect($comment->fresh()->likes_count)->toBe(1);

    learningPost($setup['user'], 'student.learning.comments.like', ['comment' => $comment->id]);

    expect($comment->fresh()->likes_count)->toBe(0);

    learningPost($setup['user'], 'student.learning.comments.store', ['material' => $setup['materials']['X']->id], [
        'content' => 'Mencoba menulis di kelas orang lain',
    ])->assertForbidden();
});
