<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Aturan sirkulasi perpustakaan
    |--------------------------------------------------------------------------
    |
    | Nilai dapat dioverride lewat .env tanpa mengubah kode.
    |
    */
    'loan_days' => (int) env('LIBRARY_LOAN_DAYS', 14),
    'reservation_hold_days' => (int) env('LIBRARY_RESERVATION_HOLD_DAYS', 3),
    'fine_per_day' => (int) env('LIBRARY_FINE_PER_DAY', 1000),
    'max_active_reservations' => (int) env('LIBRARY_MAX_ACTIVE_RESERVATIONS', 3),

    'max_active_loans' => [
        'student' => (int) env('LIBRARY_MAX_LOANS_STUDENT', 3),
        'lecturer' => (int) env('LIBRARY_MAX_LOANS_LECTURER', 5),
        'default' => (int) env('LIBRARY_MAX_LOANS_DEFAULT', 3),
    ],
];
