<?php

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Settings\System;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
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
function studentAffairsTestSetup(): array
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

    $admin = User::factory()->create([
        'first_name' => 'Ayu',
        'last_name' => 'Kemahasiswaan',
        'username' => 'ayu.kemahasiswaan.test',
    ]);
    $admin->assignRole('admin');

    $studentUser = User::factory()->create([
        'first_name' => 'Rania',
        'last_name' => 'Putri',
        'username' => 'rania.putri.affairs',
    ]);
    $studentUser->assignRole('student');

    $otherStudent = User::factory()->create([
        'first_name' => 'Nadia',
        'last_name' => 'Senior',
        'username' => 'nadia.senior.affairs',
    ]);
    $otherStudent->assignRole('student');

    $viewer = User::factory()->create([
        'first_name' => 'Vera',
        'last_name' => 'Pengamat',
        'username' => 'vera.pengamat.test',
    ]);
    $viewer->assignRole('viewer');

    foreach (studentAffairsPermissionNames() as $permission) {
        SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    SpatieRole::where('name', 'admin')->firstOrFail()
        ->givePermissionTo(studentAffairsPermissionNames());

    $faculty = Faculty::create(['name' => 'Fakultas Sains dan Teknologi', 'short_name' => 'FST', 'code' => 'FST', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'short_name' => 'Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    foreach ([[$studentUser, '2020010001'], [$otherStudent, '2020010002']] as [$user, $nim]) {
        StudentProfile::create([
            'user_id' => $user->id,
            'study_program_id' => $program->id,
            'nim' => $nim,
            'entry_year' => 2020,
            'academic_status' => 'Aktif',
            'current_semester' => 6,
            'is_active' => true,
        ]);
    }

    $organization = StudentOrganization::create([
        'name' => 'Himpunan Mahasiswa Teknik Informatika',
        'type' => OrganizationType::HIMA->value,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'description' => 'Wadah pengembangan keilmuan mahasiswa Informatika.',
        'founded_at' => '2019-09-01',
        'contact_email' => 'hima.ti@nexacampus.test',
        'instagram_url' => 'https://instagram.com/himati.nexacampus',
        'is_active' => true,
    ]);

    return compact(
        'admin', 'studentUser', 'otherStudent', 'viewer',
        'faculty', 'program', 'organization',
    );
}

/**
 * @return array<int, string>
 */
function studentAffairsPermissionNames(): array
{
    $permissions = [];

    foreach (['student-organization', 'student-achievement'] as $resource) {
        foreach (['viewAny', 'create', 'update', 'delete', 'view'] as $action) {
            $permissions[] = "{$resource}.{$action}";
        }
    }

    $permissions[] = 'student-achievement.verify';

    return array_values(array_unique($permissions));
}

function actingStudentAffairsAdmin(User $admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function actingStudentAffairsStudent(User $student)
{
    return test()->actingAs($student)->withSession(['active_role' => 'student']);
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makeStudentAffairsOrganization(array $setup, array $overrides = []): StudentOrganization
{
    return StudentOrganization::create(array_merge([
        'name' => 'Unit Kegiatan Mahasiswa Uji '.fake()->unique()->numerify('###'),
        'type' => OrganizationType::UKM->value,
        'description' => 'Organisasi uji.',
        'is_active' => true,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makeStudentAffairsAchievement(array $setup, array $overrides = []): StudentAchievement
{
    return StudentAchievement::create(array_merge([
        'user_id' => $setup['studentUser']->id,
        'student_organization_id' => $setup['organization']->id,
        'title' => 'Juara Uji '.fake()->unique()->numerify('###'),
        'category' => AchievementCategory::ACADEMIC->value,
        'level' => AchievementLevel::NATIONAL->value,
        'rank' => AchievementRank::WINNER->value,
        'achieved_at' => now()->subMonth()->toDateString(),
        'organizer' => 'Penyelenggara Uji',
        'status' => AchievementStatus::VERIFIED->value,
        'is_featured' => false,
    ], $overrides));
}

it('menampilkan daftar organisasi mahasiswa dengan statistik dan filter jenis', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Organizations/Index')
            ->where('can.create', true)
            ->where('can.update', true)
            ->where('can.delete', true)
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'Himpunan Mahasiswa Teknik Informatika')
            ->where('data.rows.0.typeShort', 'HIMA')
            ->where('stats.total', 1)
            ->where('stats.active', 1));

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.organizations.index', ['type' => OrganizationType::BEM->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('data.rows', 0));
});

it('menyimpan organisasi baru beserta logo', function () {
    Storage::fake('public');
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.organizations.store'), [
            'name' => 'Badan Eksekutif Mahasiswa',
            'type' => OrganizationType::BEM->value,
            'faculty_id' => $setup['faculty']->id,
            'study_program_id' => $setup['program']->id,
            'description' => 'Lembaga eksekutif mahasiswa.',
            'founded_at' => '2018-09-01',
            'contact_email' => 'bem@nexacampus.test',
            'contact_phone' => '081200000001',
            'instagram_url' => 'https://instagram.com/bemnexacampus',
            'is_active' => true,
            'logo' => UploadedFile::fake()->image('logo-bem.png', 300, 300),
        ])
        ->assertRedirect(route('admin.student-affairs.organizations.index'))
        ->assertSessionHas('success');

    $organization = StudentOrganization::where('name', 'Badan Eksekutif Mahasiswa')->firstOrFail();

    expect($organization->type)->toBe(OrganizationType::BEM)
        ->and($organization->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($organization->logo_path);
});

it('memperbarui organisasi dan memvalidasi data wajib', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->put(route('admin.student-affairs.organizations.update', $setup['organization']->id), [
            'name' => 'Himpunan Mahasiswa Informatika',
            'type' => OrganizationType::HIMA->value,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.student-affairs.organizations.index'));

    expect($setup['organization']->refresh())
        ->name->toBe('Himpunan Mahasiswa Informatika')
        ->is_active->toBeFalse();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.organizations.store'), [
            'name' => '',
            'type' => 'tidak-valid',
        ])
        ->assertSessionHasErrors(['name', 'type']);
});

it('menghapus organisasi tanpa prestasi tetapi menolak organisasi yang masih memiliki prestasi', function () {
    $setup = studentAffairsTestSetup();

    $deletable = makeStudentAffairsOrganization($setup);

    actingStudentAffairsAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.organizations.destroy', $deletable->id))
        ->assertRedirect(route('admin.student-affairs.organizations.index'));

    $this->assertSoftDeleted('student_organizations', ['id' => $deletable->id]);

    makeStudentAffairsAchievement($setup, ['student_organization_id' => $setup['organization']->id]);

    actingStudentAffairsAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.organizations.destroy', $setup['organization']->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('student_organizations', ['id' => $setup['organization']->id, 'deleted_at' => null]);
});

it('menambah anggota organisasi dan menolak duplikasi periode yang sama', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.organizations.members.store', $setup['organization']->id), [
            'user_id' => $setup['studentUser']->id,
            'position' => MemberPosition::CHAIRMAN->value,
            'period' => '2026/2027',
            'joined_at' => '2026-08-01',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($setup['organization']->members()->count())->toBe(1)
        ->and($setup['organization']->members()->first()->position)->toBe(MemberPosition::CHAIRMAN);

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.organizations.members.store', $setup['organization']->id), [
            'user_id' => $setup['studentUser']->id,
            'position' => MemberPosition::MEMBER->value,
            'period' => '2026/2027',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('user_id');
});

it('menolak pengguna non-mahasiswa sebagai anggota organisasi', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.organizations.members.store', $setup['organization']->id), [
            'user_id' => $setup['admin']->id,
            'position' => MemberPosition::MEMBER->value,
            'period' => '2026/2027',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('user_id');

    expect(StudentOrganizationMember::count())->toBe(0);
});

it('memperbarui dan menghapus anggota organisasi', function () {
    $setup = studentAffairsTestSetup();

    $member = StudentOrganizationMember::create([
        'student_organization_id' => $setup['organization']->id,
        'user_id' => $setup['studentUser']->id,
        'position' => MemberPosition::MEMBER->value,
        'period' => '2026/2027',
        'is_active' => true,
    ]);

    actingStudentAffairsAdmin($setup['admin'])
        ->put(route('admin.student-affairs.organizations.members.update', [$setup['organization']->id, $member->id]), [
            'user_id' => $setup['studentUser']->id,
            'position' => MemberPosition::SECRETARY->value,
            'period' => '2026/2027',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($member->refresh())
        ->position->toBe(MemberPosition::SECRETARY)
        ->is_active->toBeFalse();

    actingStudentAffairsAdmin($setup['admin'])
        ->delete(route('admin.student-affairs.organizations.members.destroy', [$setup['organization']->id, $member->id]))
        ->assertRedirect();

    $this->assertSoftDeleted('student_organization_members', ['id' => $member->id]);
});

it('menampilkan detail organisasi beserta anggota dan prestasi', function () {
    $setup = studentAffairsTestSetup();

    StudentOrganizationMember::create([
        'student_organization_id' => $setup['organization']->id,
        'user_id' => $setup['studentUser']->id,
        'position' => MemberPosition::CHAIRMAN->value,
        'period' => '2026/2027',
        'is_active' => true,
    ]);

    makeStudentAffairsAchievement($setup);

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.organizations.show', $setup['organization']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Organizations/Show')
            ->where('organization.name', 'Himpunan Mahasiswa Teknik Informatika')
            ->where('stats.members', 1)
            ->where('stats.activeMembers', 1)
            ->where('stats.achievements', 1)
            ->has('members', 1)
            ->has('achievements', 1)
            ->where('can.manageMembers', true));
});

it('mencari mahasiswa dan dosen untuk form organisasi', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->getJson(route('admin.student-affairs.organizations.search-students', ['q' => 'Rania']))
        ->assertOk()
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.meta.nim', '2020010001');

    actingStudentAffairsAdmin($setup['admin'])
        ->getJson(route('admin.student-affairs.organizations.search-lecturers', ['q' => 'Tidak Ada']))
        ->assertOk()
        ->assertJsonCount(0, 'options');
});

it('menyimpan prestasi baru dengan sertifikat dan status awal menunggu verifikasi', function () {
    Storage::fake('public');
    $setup = studentAffairsTestSetup();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.store'), [
            'user_id' => $setup['studentUser']->id,
            'student_organization_id' => $setup['organization']->id,
            'title' => 'Juara 1 Kompetisi Pemrograman Nasional',
            'category' => AchievementCategory::ACADEMIC->value,
            'level' => AchievementLevel::NATIONAL->value,
            'rank' => AchievementRank::WINNER->value,
            'achieved_at' => now()->subWeek()->toDateString(),
            'organizer' => 'Kementerian Pendidikan',
            'description' => 'Menjuarai kompetisi pemrograman tingkat nasional.',
            'status' => AchievementStatus::PENDING->value,
            'is_featured' => false,
            'certificate' => UploadedFile::fake()->create('sertifikat.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.student-affairs.achievements.index'))
        ->assertSessionHas('success');

    $achievement = StudentAchievement::where('title', 'Juara 1 Kompetisi Pemrograman Nasional')->firstOrFail();

    expect($achievement->status)->toBe(AchievementStatus::PENDING)
        ->and($achievement->is_featured)->toBeFalse()
        ->and($achievement->certificate_path)->not->toBeNull()
        ->and($achievement->certificate_original_name)->toBe('sertifikat.pdf');

    Storage::disk('public')->assertExists($achievement->certificate_path);
});

it('memverifikasi, menolak, dan menyorot prestasi mahasiswa', function () {
    $setup = studentAffairsTestSetup();

    $achievement = makeStudentAffairsAchievement($setup, [
        'status' => AchievementStatus::PENDING->value,
        'is_featured' => false,
    ]);

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.verify', $achievement->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($achievement->refresh())
        ->status->toBe(AchievementStatus::VERIFIED)
        ->verified_by->toBe($setup['admin']->id);

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.feature', $achievement->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($achievement->refresh()->is_featured)->toBeTrue();

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.reject', $achievement->id), [
            'rejection_reason' => 'Dokumen sertifikat tidak terbaca.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($achievement->refresh())
        ->status->toBe(AchievementStatus::REJECTED)
        ->is_featured->toBeFalse()
        ->rejection_reason->toBe('Dokumen sertifikat tidak terbaca.');
});

it('menolak penolakan prestasi tanpa alasan dan sorotan pada prestasi belum terverifikasi', function () {
    $setup = studentAffairsTestSetup();

    $pending = makeStudentAffairsAchievement($setup, [
        'status' => AchievementStatus::PENDING->value,
        'is_featured' => false,
    ]);

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.reject', $pending->id), [])
        ->assertSessionHasErrors('rejection_reason');

    actingStudentAffairsAdmin($setup['admin'])
        ->post(route('admin.student-affairs.achievements.feature', $pending->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($pending->refresh()->is_featured)->toBeFalse();
});

it('menampilkan daftar prestasi dengan filter status dan tingkat', function () {
    $setup = studentAffairsTestSetup();

    makeStudentAffairsAchievement($setup, ['title' => 'Prestasi Terverifikasi']);
    makeStudentAffairsAchievement($setup, [
        'title' => 'Prestasi Menunggu',
        'status' => AchievementStatus::PENDING->value,
        'level' => AchievementLevel::LOCAL->value,
    ]);

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.achievements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Achievements/Index')
            ->where('can.verify', true)
            ->has('data.rows', 2)
            ->where('stats.total', 2)
            ->where('stats.pending', 1)
            ->where('stats.verified', 1));

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.achievements.index', ['status' => AchievementStatus::PENDING->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.title', 'Prestasi Menunggu'));

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.achievements.index', ['level' => AchievementLevel::LOCAL->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.level', AchievementLevel::LOCAL->value));
});

it('membatasi unduhan sertifikat prestasi untuk admin dan pemilik saja', function () {
    Storage::fake('public');
    $setup = studentAffairsTestSetup();

    $path = 'student-affairs/achievements/1/sertifikat.pdf';
    Storage::disk('public')->put($path, 'isi-sertifikat');

    $achievement = makeStudentAffairsAchievement($setup, [
        'certificate_path' => $path,
        'certificate_original_name' => 'sertifikat.pdf',
    ]);

    actingStudentAffairsAdmin($setup['admin'])
        ->get(route('admin.student-affairs.achievements.certificate', $achievement->id))
        ->assertOk()
        ->assertDownload('sertifikat.pdf');

    actingStudentAffairsStudent($setup['studentUser'])
        ->get(route('student.student-affairs.certificate', $achievement->id))
        ->assertOk()
        ->assertDownload('sertifikat.pdf');

    actingStudentAffairsStudent($setup['otherStudent'])
        ->get(route('student.student-affairs.certificate', $achievement->id))
        ->assertNotFound();
});

it('menampilkan portal kemahasiswaan mahasiswa dengan keanggotaan dan prestasi pribadi', function () {
    $setup = studentAffairsTestSetup();

    StudentOrganizationMember::create([
        'student_organization_id' => $setup['organization']->id,
        'user_id' => $setup['studentUser']->id,
        'position' => MemberPosition::CHAIRMAN->value,
        'period' => '2026/2027',
        'is_active' => true,
    ]);

    makeStudentAffairsAchievement($setup, ['title' => 'Prestasi Rania']);
    makeStudentAffairsAchievement($setup, [
        'user_id' => $setup['otherStudent']->id,
        'title' => 'Prestasi Nadia',
    ]);

    actingStudentAffairsStudent($setup['studentUser'])
        ->get(route('student.student-affairs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudentAffairs/Index')
            ->where('student.nim', '2020010001')
            ->where('stats.activeMemberships', 1)
            ->where('stats.memberships', 1)
            ->where('stats.achievements', 1)
            ->where('stats.verified', 1)
            ->has('memberships', 1)
            ->has('achievements', 1)
            ->where('achievements.0.title', 'Prestasi Rania')
            ->has('organizations', 1));
});

it('menampilkan organisasi aktif pada halaman publik organisasi', function () {
    $setup = studentAffairsTestSetup();

    makeStudentAffairsOrganization($setup, [
        'name' => 'Organisasi Nonaktif',
        'type' => OrganizationType::UKM->value,
        'is_active' => false,
    ]);

    StudentOrganizationMember::create([
        'student_organization_id' => $setup['organization']->id,
        'user_id' => $setup['studentUser']->id,
        'position' => MemberPosition::MEMBER->value,
        'period' => '2026/2027',
        'is_active' => true,
    ]);

    test()->get(route('root.kemahasiswaan.organisasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/Organisasi')
            ->has('organizations', 1)
            ->where('organizations.0.name', 'Himpunan Mahasiswa Teknik Informatika')
            ->where('organizations.0.activeMembers', 1)
            ->where('stats.organizations', 1)
            ->where('stats.members', 1)
            ->has('types'));
});

it('menampilkan hanya prestasi terverifikasi pada halaman publik prestasi', function () {
    $setup = studentAffairsTestSetup();

    makeStudentAffairsAchievement($setup, [
        'title' => 'Prestasi Terverifikasi',
        'is_featured' => true,
    ]);
    makeStudentAffairsAchievement($setup, [
        'title' => 'Prestasi Menunggu',
        'status' => AchievementStatus::PENDING->value,
    ]);
    makeStudentAffairsAchievement($setup, [
        'title' => 'Prestasi Ditolak',
        'status' => AchievementStatus::REJECTED->value,
    ]);

    test()->get(route('root.kemahasiswaan.prestasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/Prestasi')
            ->has('achievements', 1)
            ->where('achievements.0.title', 'Prestasi Terverifikasi')
            ->where('achievements.0.isFeatured', true)
            ->where('stats.total', 1)
            ->where('stats.featured', 1)
            ->where('stats.national', 1)
            ->where('stats.students', 1));
});

it('menolak akses admin kemahasiswaan tanpa permission aktif', function () {
    $setup = studentAffairsTestSetup();

    actingStudentAffairsStudent($setup['studentUser'])
        ->get(route('admin.student-affairs.organizations.index'))
        ->assertForbidden();

    test()->actingAs($setup['viewer'])
        ->withSession(['active_role' => 'viewer'])
        ->get(route('admin.student-affairs.achievements.index'))
        ->assertForbidden();

    test()->actingAs($setup['viewer'])
        ->withSession(['active_role' => 'viewer'])
        ->get(route('student.student-affairs.index'))
        ->assertForbidden();
});
