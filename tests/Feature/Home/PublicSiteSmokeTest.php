<?php

use App\Models\Settings\System;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();
});

it('merender seluruh halaman publik institusi dan kemahasiswaan', function () {
    $pages = [
        '/',
        '/institusi/profil',
        '/institusi/visi-misi',
        '/institusi/struktur',
        '/institusi/fasilitas',
        '/institusi/akreditasi',
        '/institusi/kerjasama',
        '/kemahasiswaan/organisasi',
        '/kemahasiswaan/prestasi',
        '/kemahasiswaan/layanan',
        '/beasiswa',
        '/alumni',
        '/alumni/karir',
        '/kontak',
        '/admission/apply',
        '/admission/status',
        '/admission/requirements',
        '/admission/tuition',
        '/admission/faq',
    ];

    foreach ($pages as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia();
    }
});
