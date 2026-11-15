<?php

use App\Enums\FaqType;
use App\Models\Publication\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function faqCrudSetup(): array
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

function giveFaqPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingFaqOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar FAQ dengan label tipe', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.viewAny']);

    Faq::create([
        'type' => FaqType::ADMISSION->value, 'category' => 'Pendaftaran',
        'question' => 'Bagaimana cara mendaftar?', 'answer' => 'Melalui laman PMB.',
        'sort_order' => 0, 'is_active' => true,
    ]);

    actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Publication/Faq/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.type', 'admission')
            ->where('data.rows.0.category', 'Pendaftaran'));
});

it('menyimpan FAQ dan menolak tipe tidak valid', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.create']);

    actingFaqOperator($setup['operator'])
        ->post('/admin/publication/faqs', [
            'type' => 'general', 'category' => 'Umum',
            'question' => 'Apa itu NexaCampus?', 'answer' => 'Sistem informasi akademik.',
            'sort_order' => 0, 'is_active' => true,
        ])
        ->assertRedirect('/admin/publication/faqs')
        ->assertSessionHas('success');

    expect(Faq::where('category', 'Umum')->firstOrFail()->question)->toBe('Apa itu NexaCampus?');

    actingFaqOperator($setup['operator'])
        ->post('/admin/publication/faqs', [
            'type' => 'tidak-ada', 'category' => 'Umum',
            'question' => 'X?', 'answer' => 'Y.',
        ])
        ->assertSessionHasErrors('type');
});

it('memfilter FAQ berdasarkan tipe', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.viewAny']);

    Faq::create([
        'type' => FaqType::ADMISSION->value, 'category' => 'Pendaftaran',
        'question' => 'Cara daftar?', 'answer' => 'Via PMB.',
        'sort_order' => 0, 'is_active' => true,
    ]);
    Faq::create([
        'type' => FaqType::ACADEMIC->value, 'category' => 'KRS',
        'question' => 'Cara isi KRS?', 'answer' => 'Via portal.',
        'sort_order' => 0, 'is_active' => true,
    ]);

    actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs?type=admission')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.type', 'admission'));
});

it('mengalihkan status FAQ lewat toggle', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.update']);

    $faq = Faq::create([
        'type' => FaqType::GENERAL->value, 'category' => 'Umum',
        'question' => 'Tanya?', 'answer' => 'Jawab.',
        'sort_order' => 0, 'is_active' => true,
    ]);

    actingFaqOperator($setup['operator'])
        ->post("/admin/publication/faqs/{$faq->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect((bool) $faq->fresh()->is_active)->toBeFalse();
});

it('mengelola sampah FAQ dan mengekspornya', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.viewAny', 'faq.delete']);

    $faq = Faq::create([
        'type' => FaqType::GENERAL->value, 'category' => 'Umum',
        'question' => 'Sementara?', 'answer' => 'Jawab.',
        'sort_order' => 0, 'is_active' => true,
    ]);

    actingFaqOperator($setup['operator'])
        ->delete("/admin/publication/faqs/{$faq->id}")
        ->assertRedirect('/admin/publication/faqs');

    actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingFaqOperator($setup['operator'])
        ->post("/admin/publication/faqs/{$faq->id}/restore")
        ->assertSessionHas('success');

    expect(Faq::find($faq->id))->not->toBeNull();

    $response = actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('mengekspor PDF laporan standar berkop kampus', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.viewAny']);

    Faq::create([
        'type' => FaqType::GENERAL->value, 'category' => 'Umum',
        'question' => 'Tanya?', 'answer' => 'Jawab.',
        'sort_order' => 0, 'is_active' => true,
    ]);

    $response = actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs/export/pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen($response->getContent()) > 1000)->toBeTrue();
});

function makeFaqImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['type', 'category', 'question', 'answer', 'sort_order', 'is_active'], ...$rows]);

    $path = tempnam(sys_get_temp_dir(), 'import-faqs').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'faqs.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template dan mengimpor FAQ valid', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.create']);

    $response = actingFaqOperator($setup['operator'])
        ->get('/admin/publication/faqs/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template-import-faqs.xlsx');

    actingFaqOperator($setup['operator'])
        ->post('/admin/publication/faqs/import', ['file' => makeFaqImportFile([
            ['general', 'Umum', 'Apa itu NexaCampus?', 'SIAKAD kampus.', '0', '1'],
            ['admission', 'Pendaftaran', 'Kapan PMB dibuka?', 'Lihat kalender PMB.', '1', '0'],
        ])])
        ->assertRedirect('/admin/publication/faqs')
        ->assertSessionHas('success');

    expect(Faq::where('question', 'Apa itu NexaCampus?')->firstOrFail()->type->value)->toBe('general')
        ->and((bool) Faq::where('question', 'Kapan PMB dibuka?')->firstOrFail()->is_active)->toBeFalse();
});

it('menolak seluruh file FAQ bila ada baris tidak valid', function () {
    $setup = faqCrudSetup();
    giveFaqPermissionsToOperator(['faq.create']);

    $response = actingFaqOperator($setup['operator'])
        ->post('/admin/publication/faqs/import', ['file' => makeFaqImportFile([
            ['general', 'Umum', 'Valid?', 'Ya.', '', ''],
            ['salah', 'Umum', 'Tidak valid?', 'Bukan.', '', ''],
        ])])
        ->assertRedirect('/admin/publication/faqs');

    expect(Faq::where('question', 'Valid?')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});
