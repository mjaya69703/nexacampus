<?php

use App\Support\Database\PortableOrder;

it('membangun ekspresi CASE yang portabel untuk urutan status', function () {
    expect(PortableOrder::priority('status', ['active', 'released', 'waived']))
        ->toBe("CASE status WHEN 'active' THEN 1 WHEN 'released' THEN 2 WHEN 'waived' THEN 3 ELSE 4 END");
});

it('meloloskan tanda kutip tunggal pada nilai', function () {
    expect(PortableOrder::priority('status', ["it's", 'done']))
        ->toBe("CASE status WHEN 'it''s' THEN 1 WHEN 'done' THEN 2 ELSE 3 END");
});

it('mengembalikan konstanta netral untuk daftar kosong', function () {
    expect(PortableOrder::priority('status', []))->toBe('1');
});
