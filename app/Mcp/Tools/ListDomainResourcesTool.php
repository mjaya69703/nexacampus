<?php

namespace App\Mcp\Tools;

use App\Support\ResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List all NexaCampus CRUD resources grouped by domain area (academic, financial, admission, ...). Each entry shows the resource key, admin URL, Livewire component prefix, available actions, and required permissions. Use this as the page map when rewriting the UI to React.')]
#[IsReadOnly]
#[IsIdempotent]
class ListDomainResourcesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $area = $request->get('area');

        $resources = collect(ResourceRegistry::all())
            ->when(is_string($area) && $area !== '', fn ($collection) => $collection->where('area', $area))
            ->map(fn (array $resource): array => [
                'resource' => $resource['resource'],
                'area' => $resource['area'] ?? null,
                'url' => url('admin/'.($resource['area'] ?? '').'/'.$resource['plural']),
                'component' => $resource['component'] ?? null,
                'actions' => array_values($resource['actions'] ?? []),
                'permissions' => ResourceRegistry::permissionsFor($resource),
                'menu' => $resource['menu']['title'] ?? null,
            ])
            ->groupBy('area')
            ->map(fn ($group) => $group->values()->all())
            ->all();

        return Response::text(json_encode([
            'areas' => array_keys($resources),
            'total' => collect($resources)->map(fn ($group) => count($group))->sum(),
            'resources' => $resources,
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
            'area' => $schema->string()->description('Filter by domain area, e.g. academic, financial, admission, organization, alumni, student-service, campus, publication, access, system. Omit to list all areas.'),
        ];
    }
}
