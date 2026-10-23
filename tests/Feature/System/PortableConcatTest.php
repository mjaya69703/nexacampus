<?php

use App\Models\User;
use App\Support\Database\PortableConcat;

it('membangun ekspresi konkatenasi sesuai driver sqlite', function () {
    expect(PortableConcat::fullName())
        ->toBe("first_name || ' ' || last_name");
});

it('menggabungkan ekspresi apa pun secara berurutan', function () {
    expect(PortableConcat::of(['a', "'-'", 'b', "'!'"]))
        ->toBe("a || '-' || b || '!'");
});

it('mengembalikan string kosong untuk daftar kosong', function () {
    expect(PortableConcat::of([]))->toBe("''");
});

it('mencari nama lengkap melintasi spasi pada query nyata', function () {
    User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Santoso']);
    User::factory()->create(['first_name' => 'Siti', 'last_name' => 'Aminah']);

    $found = User::query()
        ->whereRaw(PortableConcat::fullName().' like ?', ['%Budi Santoso%'])
        ->pluck('first_name')
        ->all();

    expect($found)->toBe(['Budi']);
});
