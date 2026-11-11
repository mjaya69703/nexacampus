<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List NexaCampus application routes (admin, student, lecturer, alumni, academic-leader, employee portals). Optionally filter by portal prefix and/or search string. Use this together with list-domain-resources to map every page that must be rewritten to React.')]
#[IsReadOnly]
#[IsIdempotent]
class ListAppRoutesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $portal = $request->get('portal');
        $search = $request->get('search');

        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): array => [
                'name' => $route->getName(),
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
            ])
            ->filter(fn (array $route): bool => is_string($route['name']) && $route['name'] !== '')
            ->when(
                is_string($portal) && $portal !== '',
                fn ($collection) => $collection->filter(fn (array $route): bool => str_starts_with($route['name'], $portal.'.') || $route['name'] === $portal)
            )
            ->when(
                is_string($search) && $search !== '',
                fn ($collection) => $collection->filter(fn (array $route): bool => str_contains($route['name'], $search) || str_contains($route['uri'], $search))
            )
            ->sortBy('name')
            ->values();

        $total = $routes->count();
        $limit = 300;

        return Response::text(json_encode([
            'total' => $total,
            'truncated' => $total > $limit,
            'routes' => $routes->take($limit)->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'portal' => $schema->string()->description('Filter by route name prefix: admin, student, lecturer, alumni, academic-leader, employee. Omit to list all portals.'),
            'search' => $schema->string()->description('Filter routes whose name or URI contains this string, e.g. study-plan, invoice, transcript.'),
        ];
    }
}
