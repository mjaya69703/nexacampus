@php
    use App\Support\SidebarMenu;
    use Illuminate\Support\Facades\Route;

    $menus = SidebarMenu::get();
@endphp

<ul class="navbar-nav pt-lg-3">
    @foreach ($menus as $menu)
        @if ($menu->type === 'link')
            <li class="nav-item {{ $menu->is_active_menu ? 'active' : '' }}">
                <a class="nav-link {{ $menu->is_active_menu ? 'active' : '' }}"
                    href="{{ $menu->route_name && Route::has($menu->route_name) ? route($menu->route_name) : $menu->url ?? '#' }}">
                    @if ($menu->icon)
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                            <i class="{{ $menu->icon }}"></i>
                        </span>
                    @endif
                    <span class="nav-link-title">{{ $menu->title }}</span>
                </a>
            </li>
        @elseif ($menu->type === 'group')
            <li class="nav-item dropdown {{ $menu->is_active_menu ? 'active' : '' }}">
                <a class="nav-link dropdown-toggle {{ $menu->is_active_menu ? 'show' : '' }}" href="#navbar-{{ $menu->id }}"
                    data-bs-toggle="dropdown" data-bs-auto-close="false" role="button"
                    aria-expanded="{{ $menu->is_active_menu ? 'true' : 'false' }}">
                    @if ($menu->icon)
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                            <i class="{{ $menu->icon }}"></i>
                        </span>
                    @endif
                    <span class="nav-link-title">{{ $menu->title }}</span>
                </a>

                <div class="dropdown-menu {{ $menu->is_active_menu ? 'show' : '' }}">
                    <div class="dropdown-menu-columns">
                        <div class="dropdown-menu-column">
                            @foreach ($menu->children as $child)
                                <a class="dropdown-item {{ $child->is_active_menu ? 'active' : '' }}"
                                    href="{{ $child->route_name && Route::has($child->route_name) ? route($child->route_name) : $child->url ?? '#' }}">
                                    {{ $child->title }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </li>
        @endif
    @endforeach
</ul>
