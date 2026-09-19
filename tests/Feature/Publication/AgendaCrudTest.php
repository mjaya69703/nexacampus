<?php

use App\Models\Publication\Agenda;
use App\Models\Publication\PublicationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function agendaCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = App\Models\User::factory()->create();
    $operator->assignRole('operator');

    return compact('operator');
}

function giveAgendaPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAgendaOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function makeAgenda(array $overrides = []): Agenda
{
    $creatorId = $overrides['created_by'] ?? App\Models\User::factory()->create()->id;
    unset($overrides['created_by']);

    return Agenda::create(array_merge([
        'title' => 'Seminar Nasional',
        'slug' => 'seminar-nasional-'.Str::random(6),
        'event_date' => now()->addWeek()->format('Y-m-d'),
        'is_published' => false,
        'created_by' => $creatorId,
    ], $overrides));
}

it('menampilkan daftar agenda dengan status jadwal', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.viewAny']);

    makeAgenda(['is_published' => true, 'published_at' => now()]);

    actingAgendaOperator($setup['operator'])
        ->get('/admin/publication/agendas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Agenda/Index')
            ->where('stats.total', 1)
            ->where('stats.upcoming', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.isUpcoming', true));
});

it('menampilkan pratinjau agenda', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.view']);

    $agenda = makeAgenda();

    actingAgendaOperator($setup['operator'])
        ->get("/admin/publication/agendas/{$agenda->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Agenda/Show')
            ->where('agenda.title', 'Seminar Nasional'));
});

it('menyimpan agenda dan menolak tanggal selesai mundur', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.create']);

    actingAgendaOperator($setup['operator'])
        ->post('/admin/publication/agendas', [
            'title' => 'Seminar Nasional',
            'slug' => 'seminar-nasional',
            'location' => 'Aula Utama',
            'event_date' => '2026-10-01',
            'event_time' => '09:00',
            'is_published' => true,
            'published_at' => now()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect('/admin/publication/agendas')
        ->assertSessionHas('success');

    $agenda = Agenda::where('slug', 'seminar-nasional')->firstOrFail();

    expect($agenda->event_time)->toBe('09:00:00')
        ->and($agenda->is_published)->toBeTrue();

    actingAgendaOperator($setup['operator'])
        ->post('/admin/publication/agendas', [
            'title' => 'Mundur',
            'event_date' => '2026-10-05',
            'event_end_date' => '2026-10-01',
        ])
        ->assertSessionHasErrors('event_end_date');

    expect(Agenda::where('title', 'Mundur')->count())->toBe(0);
});

it('menerapkan scope published ketat pada agenda', function () {
    agendaCrudSetup();

    $future = makeAgenda([
        'title' => 'Terjadwal', 'is_published' => true,
        'published_at' => now()->addDay(),
    ]);
    $visible = makeAgenda([
        'title' => 'Tampil', 'slug' => 'tampil-'.Str::random(6),
        'is_published' => true, 'published_at' => now()->subHour(),
    ]);

    expect(Agenda::published()->whereKey($future->id)->count())->toBe(0)
        ->and(Agenda::published()->whereKey($visible->id)->count())->toBe(1);
});

it('mengelola sampah agenda dan mengekspornya', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.viewAny', 'agenda.delete']);

    $agenda = makeAgenda(['title' => 'Sementara']);

    actingAgendaOperator($setup['operator'])
        ->delete("/admin/publication/agendas/{$agenda->id}")
        ->assertRedirect('/admin/publication/agendas');

    actingAgendaOperator($setup['operator'])
        ->get('/admin/publication/agendas?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAgendaOperator($setup['operator'])
        ->post("/admin/publication/agendas/{$agenda->id}/restore")
        ->assertSessionHas('success');

    expect(Agenda::find($agenda->id))->not->toBeNull();

    $response = actingAgendaOperator($setup['operator'])
        ->get('/admin/publication/agendas/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.viewAny']);

    makeAgenda();

    $response = actingAgendaOperator($setup['operator'])
        ->get('/admin/publication/agendas/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeAgendaImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['title', 'slug', 'category', 'location', 'event_date', 'event_time', 'event_end_date', 'is_published'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-agendas').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'agendas.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor agenda valid', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.create']);

    PublicationCategory::create(['name' => 'Seminar', 'slug' => 'seminar', 'is_active' => true]);

    $response = actingAgendaOperator($setup['operator'])
        ->get('/admin/publication/agendas/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-agendas.xlsx');

    actingAgendaOperator($setup['operator'])
        ->post('/admin/publication/agendas/import', ['file' => makeAgendaImportFile([
            ['Seminar Satu', 'seminar-satu', 'Seminar', 'Aula', '2026-10-01', '09:00', '', '1'],
            ['Rapat Rutin', 'rapat-rutin', '', '', '2026-10-02', '', '', '0'],
        ])])
        ->assertRedirect('/admin/publication/agendas')
        ->assertSessionHas('success');

    expect(Agenda::where('slug', 'seminar-satu')->firstOrFail()->event_time)->toBe('09:00:00')
        ->and(Agenda::where('slug', 'rapat-rutin')->firstOrFail()->event_time)->toBeNull();
});

it('menolak seluruh file agenda bila ada baris tidak valid', function () {
    $setup = agendaCrudSetup();
    giveAgendaPermissionsToOperator(['agenda.create']);

    $response = actingAgendaOperator($setup['operator'])
        ->post('/admin/publication/agendas/import', ['file' => makeAgendaImportFile([
            ['Valid', 'valid', '', '', '2026-10-01', '', '', ''],
            ['Tanggal Rusak', 'tanggal-rusak', '', '', 'bukan-tanggal', '', '', ''],
        ])])
        ->assertRedirect('/admin/publication/agendas');

    expect(Agenda::where('slug', 'valid')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
