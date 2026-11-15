<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public landing page through Inertia', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Welcome')
            ->has('campus')
            ->has('stats')
            ->has('links'));

    expect($response->getContent())
        ->not->toContain('tabler.css')
        ->toContain('data-page');
});
