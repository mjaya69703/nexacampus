@php
    use App\Models\Academic\AcademicYear;
    use App\Support\SidebarMenu;
    use Illuminate\Support\Facades\Route;

    $topbarMenus = SidebarMenu::get()
        ->flatMap(function ($menu) {
            if ($menu->type === 'link') {
                return [[
                    'group' => null,
                    'title' => $menu->title,
                    'route_name' => $menu->route_name,
                    'url' => $menu->url,
                    'icon' => $menu->icon ?: 'fas fa-arrow-right',
                ]];
            }

            return collect($menu->children ?? [])->map(fn ($child) => [
                'group' => $menu->title,
                'title' => $child->title,
                'route_name' => $child->route_name,
                'url' => $child->url,
                'icon' => $menu->icon ?: 'fas fa-arrow-right',
            ]);
        })
        ->filter(fn ($item) => ($item['route_name'] && Route::has($item['route_name'])) || filled($item['url']))
        ->map(function ($item) {
            $item['href'] = $item['route_name'] && Route::has($item['route_name'])
                ? route($item['route_name'])
                : $item['url'];

            return $item;
        })
        ->values();

    $activeAcademicYear = AcademicYear::query()
        ->where('is_active', true)
        ->orderByDesc('start_date')
        ->first();
@endphp

<li class="nav-item dropdown topbar-command-item">
    <button
        id="topbar-command-trigger"
        class="topbar-command-trigger"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-label="Cari dan buka menu"
    >
        <i class="fas fa-search" aria-hidden="true"></i>
        <span>Cari menu atau halaman...</span>
        <kbd>Ctrl K</kbd>
    </button>

    <div class="dropdown-menu topbar-command-menu p-0">
        <div class="topbar-command-input-wrap">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input
                id="topbar-command-input"
                type="search"
                class="form-control"
                placeholder="Ketik nama menu..."
                autocomplete="off"
                aria-label="Cari menu"
            >
            <button id="topbar-command-clear" type="button" class="btn btn-icon btn-ghost-secondary" title="Hapus pencarian" aria-label="Hapus pencarian">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="topbar-command-results" class="topbar-command-results">
            @foreach ($topbarMenus as $item)
                <a
                    href="{{ $item['href'] }}"
                    class="topbar-command-result"
                    data-command-item
                    data-command-search="{{ str(($item['group'] ? $item['group'].' ' : '').$item['title'])->lower() }}"
                >
                    <span class="topbar-command-icon"><i class="{{ $item['icon'] }}"></i></span>
                    <span class="topbar-command-copy">
                        <strong>{{ $item['title'] }}</strong>
                        <small>{{ $item['group'] ?: 'Navigasi utama' }}</small>
                    </span>
                    <i class="fas fa-arrow-right topbar-command-arrow" aria-hidden="true"></i>
                </a>
            @endforeach

            <div id="topbar-command-empty" class="topbar-command-empty d-none">
                <i class="fas fa-search-minus"></i>
                <span>Menu tidak ditemukan.</span>
            </div>
        </div>

        <div class="topbar-command-footer">
            <span><kbd>Enter</kbd> buka</span>
            <span><kbd>Esc</kbd> tutup</span>
        </div>
    </div>
</li>

@if ($activeAcademicYear)
    <li class="nav-item d-none d-xl-flex align-items-center ms-2">
        <div class="topbar-period" title="Tahun akademik aktif">
            <span class="topbar-period-icon"><i class="fas fa-calendar-alt"></i></span>
            <span>
                <small>Periode aktif</small>
                <strong>{{ $activeAcademicYear->name }}</strong>
            </span>
        </div>
    </li>
@endif

@once
    <style>
            .topbar-command-item {
                display: flex;
                align-items: center;
            }

            .topbar-command-trigger {
                width: min(25rem, 34vw);
                min-height: 2.35rem;
                display: flex;
                align-items: center;
                gap: .65rem;
                padding: .45rem .55rem .45rem .8rem;
                color: var(--tblr-secondary-color);
                background: var(--app-muted-bg);
                border: 1px solid var(--app-topbar-border);
                border-radius: 6px;
                text-align: left;
            }

            .topbar-command-trigger:hover,
            .topbar-command-trigger[aria-expanded="true"] {
                color: var(--app-primary);
                border-color: color-mix(in srgb, var(--app-primary) 45%, transparent);
                background: var(--app-surface-bg);
            }

            .topbar-command-trigger span {
                flex: 1;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }

            .topbar-command-trigger kbd,
            .topbar-command-footer kbd {
                color: var(--tblr-secondary-color);
                background: var(--app-surface-bg);
                border: 1px solid var(--app-topbar-border);
                box-shadow: none;
                font-size: .68rem;
            }

            .topbar-command-menu {
                width: min(31rem, calc(100vw - 2rem));
                margin-top: .55rem !important;
                overflow: hidden;
                border-color: var(--app-topbar-border);
                border-radius: 6px;
                box-shadow: 0 16px 42px rgba(31, 20, 66, .18);
            }

            .topbar-command-input-wrap {
                display: flex;
                align-items: center;
                gap: .55rem;
                padding: .65rem;
                border-bottom: 1px solid var(--app-topbar-border);
            }

            .topbar-command-input-wrap > i {
                color: var(--app-primary);
                margin-left: .25rem;
            }

            .topbar-command-input-wrap .form-control {
                border: 0;
                box-shadow: none;
                background: transparent;
            }

            .topbar-command-results {
                max-height: min(25rem, 55vh);
                overflow-y: auto;
                padding: .4rem;
            }

            .topbar-command-result {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding: .62rem .7rem;
                color: inherit;
                border-radius: 5px;
                text-decoration: none;
            }

            .topbar-command-result:hover,
            .topbar-command-result.is-selected {
                color: var(--app-primary);
                background: var(--app-muted-bg);
            }

            .topbar-command-icon {
                width: 2rem;
                height: 2rem;
                flex: 0 0 2rem;
                display: grid;
                place-items: center;
                color: var(--app-primary);
                background: color-mix(in srgb, var(--app-primary) 12%, transparent);
                border-radius: 5px;
            }

            .topbar-command-copy {
                min-width: 0;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .topbar-command-copy strong,
            .topbar-command-copy small {
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }

            .topbar-command-copy small {
                color: var(--tblr-secondary-color);
            }

            .topbar-command-arrow {
                opacity: 0;
                font-size: .75rem;
            }

            .topbar-command-result:hover .topbar-command-arrow,
            .topbar-command-result.is-selected .topbar-command-arrow {
                opacity: 1;
            }

            .topbar-command-empty {
                min-height: 8rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .6rem;
                color: var(--tblr-secondary-color);
            }

            .topbar-command-footer {
                display: flex;
                gap: 1rem;
                padding: .5rem .75rem;
                color: var(--tblr-secondary-color);
                background: var(--app-muted-bg);
                border-top: 1px solid var(--app-topbar-border);
                font-size: .72rem;
            }

            .topbar-period {
                display: flex;
                align-items: center;
                gap: .55rem;
                padding: .35rem .65rem;
                border-left: 1px solid var(--app-topbar-border);
            }

            .topbar-period-icon {
                color: var(--app-primary);
            }

            .topbar-period span:last-child {
                display: flex;
                flex-direction: column;
                line-height: 1.15;
            }

            .topbar-period small {
                color: var(--tblr-secondary-color);
                font-size: .64rem;
            }

            .topbar-period strong {
                max-width: 12rem;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
                font-size: .75rem;
            }
    </style>

    <script>
            document.addEventListener('DOMContentLoaded', () => {
                const trigger = document.getElementById('topbar-command-trigger');
                const input = document.getElementById('topbar-command-input');
                const clear = document.getElementById('topbar-command-clear');
                const empty = document.getElementById('topbar-command-empty');
                const items = Array.from(document.querySelectorAll('[data-command-item]'));
                let visibleItems = items;
                let selectedIndex = 0;

                if (!trigger || !input) {
                    return;
                }

                const selectItem = (index) => {
                    visibleItems.forEach(item => item.classList.remove('is-selected'));

                    if (!visibleItems.length) {
                        selectedIndex = -1;
                        return;
                    }

                    selectedIndex = Math.max(0, Math.min(index, visibleItems.length - 1));
                    visibleItems[selectedIndex].classList.add('is-selected');
                    visibleItems[selectedIndex].scrollIntoView({ block: 'nearest' });
                };

                const filterItems = () => {
                    const query = input.value.trim().toLowerCase();
                    visibleItems = items.filter(item => {
                        const matches = !query || item.dataset.commandSearch.includes(query);
                        item.classList.toggle('d-none', !matches);
                        return matches;
                    });
                    empty.classList.toggle('d-none', visibleItems.length > 0);
                    selectItem(0);
                };

                trigger.addEventListener('shown.bs.dropdown', () => {
                    input.focus();
                    input.select();
                    filterItems();
                });

                input.addEventListener('input', filterItems);
                clear?.addEventListener('click', () => {
                    input.value = '';
                    filterItems();
                    input.focus();
                });

                input.addEventListener('keydown', event => {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        selectItem(selectedIndex + 1);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        selectItem(selectedIndex - 1);
                    } else if (event.key === 'Enter' && selectedIndex >= 0) {
                        event.preventDefault();
                        visibleItems[selectedIndex]?.click();
                    }
                });

                document.addEventListener('keydown', event => {
                    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                        event.preventDefault();
                        bootstrap.Dropdown.getOrCreateInstance(trigger).show();
                    }
                });
            });
    </script>
@endonce
