<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders all public academic pages through Inertia', function () {
    $this->withoutMiddleware();

    $pages = [
        '/program-studi' => 'Home/Academic/Programs',
        '/akademik/kalender' => 'Home/Academic/Calendar',
        '/akademik/jadwal' => 'Home/Academic/Schedule',
        '/akademik/kurikulum' => 'Home/Academic/Curriculum',
        '/akademik/elearning' => 'Home/Academic/Elearning',
    ];

    foreach ($pages as $url => $component) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component($component)
                ->has('campus')
                ->has('links')
                ->has('user'));
    }
});
