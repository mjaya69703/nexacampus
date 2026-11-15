<?php

return [
    // Inertia React 3 reads the initial page from a JSON script element.
    'use_script_element_for_initial_page' => true,
    'page_paths' => [
        resource_path('js/pages'),
    ],
    'page_extensions' => ['js', 'jsx', 'ts', 'tsx'],
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],
];
