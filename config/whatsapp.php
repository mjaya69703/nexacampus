<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node Binary
    |--------------------------------------------------------------------------
    |
    | Lokasi binary Node.js yang dipakai sidecar WhatsApp. Nilai ini dibaca
    | lewat config (bukan env() langsung) agar tetap tersedia ketika
    | konfigurasi di-cache pada environment produksi.
    |
    */

    'node_binary' => env('NODE_BINARY'),

    'web_node_binary' => env('WHATSAPP_WEB_NODE_BINARY'),

];
