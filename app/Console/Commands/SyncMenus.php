<?php

namespace App\Console\Commands;

use App\Models\Settings\Menu;
use App\Support\ResourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncMenus extends Command
{
    protected $signature = 'menus:sync';

    protected $description = 'Sync menus from config/resources.php';

    public function handle(): int
    {
        foreach (ResourceRegistry::all() as $resource) {
            if (empty($resource['menu'])) {
                continue;
            }

            $menuConfig = $resource['menu'];
            $groupTitle = trim($menuConfig['group'] ?? '');
            $parentId = null;

            // 1. Buat / update parent group kalau ada group
            if ($groupTitle !== '') {
                $groupRouteName = null;
                $groupUrl = null;

                $group = Menu::updateOrCreate(
                    [
                        'title' => $groupTitle,
                        'parent_id' => null,
                        'type' => 'group',
                    ],
                    [
                        'route_name' => $groupRouteName,
                        'url' => $groupUrl,
                        'icon' => $menuConfig['group_icon'] ?? $this->defaultGroupIcon($groupTitle),
                        'permission_name' => null,
                        'sort_order' => $menuConfig['group_order'] ?? 0,
                        'is_active' => true,
                    ]
                );

                $parentId = $group->id;
            }

            // 2. Buat / update child/top-level link
            $routeName = "{$resource['component']}.index";
            $url = '/'.trim(($resource['area'] ?? '').'/'.$resource['plural'], '/');

            Menu::updateOrCreate(
                [
                    'route_name' => $routeName,
                ],
                [
                    'parent_id' => $parentId,
                    'type' => 'link',
                    'title' => $menuConfig['title'],
                    'url' => $url,
                    'icon' => $menuConfig['icon'] ?? null,
                    'permission_name' => ResourceRegistry::menuPermission($resource),
                    'sort_order' => $menuConfig['order'] ?? 0,
                    'is_active' => true,
                ]
            );

            $this->line("Synced menu: {$menuConfig['title']}");
        }

        $this->info('Menu sync completed.');

        return self::SUCCESS;
    }

    protected function defaultGroupIcon(string $groupTitle): ?string
    {
        return match (Str::lower($groupTitle)) {
            'access management' => 'fa fa-user-shield',
            'system', 'system management' => 'fa fa-cogs',
            'academic' => 'fa fa-graduation-cap',
            default => 'fa fa-folder',
        };
    }
}
