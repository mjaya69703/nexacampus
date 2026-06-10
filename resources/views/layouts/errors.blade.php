<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>{{ (isset($menus) ? $menus . ' - ' : '') . $pages . ' - ' . $campus->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @PwaHead
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <link href="{{ asset('assets') }}/dist/css/tabler.css?1774011441" rel="stylesheet" />
    <!-- END GLOBAL MANDATORY STYLES -->
    <!-- BEGIN PLUGINS STYLES -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="{{ asset('assets/libs/tom-select/dist/css/tom-select.bootstrap5.min.css') }}" rel="stylesheet" />
    <!-- END PLUGINS STYLES -->
    <!-- BEGIN CUSTOM FONT -->
    <style>
        @import url("https://rsms.me/inter/inter.css");
    </style>
    <!-- END CUSTOM FONT -->
    {{-- BEGIN ADDITIONAL STYLES --}}
    @stack('styles')
    @livewireStyles
    <style>
        .form-label,
        .form-control {
            margin-top: 0 !important;
        }
        }
    </style>
    {{-- END ADDITIONAL STYLES --}}
</head>

<body class="layout-fluid">
    <a href="#content" class="visually-hidden skip-link">Skip to main content</a>
    <!-- BEGIN GLOBAL THEME SCRIPT -->
    <script src="{{ asset('assets') }}/dist/js/tabler-theme.min.js?1774011441"></script>
    <!-- END GLOBAL THEME SCRIPT -->
    <div class="page">
        <!--  BEGIN SIDEBAR  -->
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <!-- BEGIN NAVBAR TOGGLER -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle sidebar navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <!-- END NAVBAR TOGGLER -->
                <!-- BEGIN NAVBAR LOGO -->
                <div class="navbar-brand navbar-brand-autodark">
                    <a href="." aria-label="Tabler">
                        <img src="{{ $campus->logo_horizontal }}" alt="NexaCampus Logo" style="max-height: 64px; max-width: 200px;">
                    </a>
                </div>
                <!-- END NAVBAR LOGO -->
                <div class="navbar-nav flex-row d-lg-none">
                    <div class="nav-item d-none d-lg-flex me-3">
                        <div class="btn-list">
                            <a href="https://github.com/sponsors/codecalm" class="btn btn-6" target="_blank" rel="noreferrer">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/heart -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon text-pink icon-2">
                                    <path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />
                                </svg>
                                Sponsor
                            </a>
                        </div>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
                            <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->photo }})"> </span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ auth()->user()->name }}</div>
                                <div class="mt-1 small text-secondary">{{ ucfirst($activeRole ?? 'User') }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a class="dropdown-item" href="{{ route('home.profile-index') }}">
                                <i class="fas fa-user"></i>
                                Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('auth.switch-role') }}">
                                <i class="fas fa-exchange-alt"></i>
                                Switch Role</a>
                            <a class="dropdown-item" href="{{ route('auth.logout') }}">
                                <i class="fas fa-sign-out-alt"></i>
                                Sign out</a>
                        </div>
                    </div>

                </div>
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <!-- BEGIN NAVBAR MENU -->
                    <ul class="navbar-nav pt-lg-3">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-dashboards" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-haspopup="true" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                        <path d="M5 12l-2 0l9 -9l9 9l-2 0" />
                                        <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" />
                                        <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                                    </svg></span>
                                <span class="nav-link-title"> Dashboards </span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="./index.html"> Default </a>
                                <a class="dropdown-item" href="./dashboard-crypto.html"> Crypto </a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-base" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-haspopup="true" aria-expanded="false">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/package -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                        <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                                        <path d="M12 12l8 -4.5" />
                                        <path d="M12 12l0 9" />
                                        <path d="M12 12l-8 -4.5" />
                                        <path d="M16 5.25l-8 4.5" />
                                    </svg></span>
                                <span class="nav-link-title"> Interface </span>
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-menu-columns">
                                    <div class="dropdown-menu-column">
                                        <a class="dropdown-item" href="./all-elements.html"> All Elements </a>
                                        <a class="dropdown-item" href="./accordion.html">
                                            Accordion
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./alerts.html"> Alerts </a>
                                        <div class="dropend">
                                            <a class="dropdown-item dropdown-toggle" href="#sidebar-authentication" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-haspopup="true" aria-expanded="false">
                                                Authentication
                                            </a>
                                            <div class="dropdown-menu">
                                                <a href="./sign-in.html" class="dropdown-item"> Sign in </a>
                                                <a href="./sign-in-link.html" class="dropdown-item"> Sign in link </a>
                                                <a href="./sign-in-illustration.html" class="dropdown-item"> Sign in with illustration </a>
                                                <a href="./sign-in-cover.html" class="dropdown-item"> Sign in with cover </a>
                                                <a href="./sign-up.html" class="dropdown-item"> Sign up </a>
                                                <a href="./forgot-password.html" class="dropdown-item"> Forgot password </a>
                                                <a href="./terms-of-service.html" class="dropdown-item"> Terms of service </a>
                                                <a href="./auth-lock.html" class="dropdown-item"> Lock screen </a>
                                                <a href="./2-step-verification.html" class="dropdown-item"> 2 step verification </a>
                                                <a href="./2-step-verification-code.html" class="dropdown-item"> 2 step verification code </a>
                                            </div>
                                        </div>
                                        <a class="dropdown-item" href="./avatars.html">
                                            Avatars
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./badges.html"> Badges </a>
                                        <a class="dropdown-item" href="./blank.html"> Blank page </a>
                                        <a class="dropdown-item" href="./buttons.html"> Buttons </a>
                                        <div class="dropend">
                                            <a class="dropdown-item dropdown-toggle" href="#sidebar-cards" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-haspopup="true" aria-expanded="false">
                                                Cards
                                            </a>
                                            <div class="dropdown-menu">
                                                <a href="./cards.html" class="dropdown-item"> Sample cards </a>
                                                <a href="./card-actions.html" class="dropdown-item">
                                                    Card actions
                                                    <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                                </a>
                                                <a href="./card-gradients.html" class="dropdown-item">
                                                    Card gradients
                                                    <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                                </a>
                                                <a href="./cards-masonry.html" class="dropdown-item"> Cards Masonry </a>
                                            </div>
                                        </div>
                                        <a class="dropdown-item" href="./carousel.html"> Carousel </a>
                                        <a class="dropdown-item" href="./colors.html"> Colors </a>
                                        <a class="dropdown-item" href="./datagrid.html"> Data grid </a>
                                        <a class="dropdown-item" href="./dropdowns.html"> Dropdowns </a>
                                        <div class="dropend">
                                            <a class="dropdown-item dropdown-toggle" href="#sidebar-error" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-haspopup="true" aria-expanded="false">
                                                Error pages
                                            </a>
                                            <div class="dropdown-menu">
                                                <a href="./error-404.html" class="dropdown-item"> 404 page </a>
                                                <a href="./error-500.html" class="dropdown-item"> 500 page </a>
                                                <a href="./error-maintenance.html" class="dropdown-item"> Maintenance page </a>
                                            </div>
                                        </div>
                                        <a class="dropdown-item" href="./lists.html"> Lists </a>
                                        <a class="dropdown-item" href="./modals.html"> Modals </a>
                                        <a class="dropdown-item" href="./prose.html"> Prose </a>
                                    </div>
                                    <div class="dropdown-menu-column">
                                        <a class="dropdown-item" href="./navigation.html"> Navigation </a>
                                        <a class="dropdown-item" href="./offcanvas.html"> Offcanvas </a>
                                        <a class="dropdown-item" href="./pagination.html"> Pagination </a>
                                        <a class="dropdown-item" href="./patterns.html">
                                            Patterns
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./placeholder.html"> Placeholder </a>
                                        <a class="dropdown-item" href="./progress.html">
                                            Progress
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./segmented-control.html">
                                            Segmented control
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./scroll-spy.html">
                                            Scroll spy
                                            <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                        </a>
                                        <a class="dropdown-item" href="./social-icons.html"> Social icons </a>
                                        <a class="dropdown-item" href="./stars-rating.html"> Stars rating </a>
                                        <a class="dropdown-item" href="./steps.html"> Steps </a>
                                        <a class="dropdown-item" href="./tables.html"> Tables </a>
                                        <a class="dropdown-item" href="./tabs.html"> Tabs </a>
                                        <a class="dropdown-item" href="./tags.html"> Tags </a>
                                        <a class="dropdown-item" href="./toasts.html"> Toasts </a>
                                        <a class="dropdown-item" href="./typography.html"> Typography </a>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <!-- END NAVBAR MENU -->
                </div>
            </div>
        </aside>
        <!--  END SIDEBAR  -->
        <!-- BEGIN NAVBAR  -->
        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
            <div class="container-xl">
                <!-- BEGIN NAVBAR TOGGLER -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle primary navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <!-- END NAVBAR TOGGLER -->
                <div class="navbar-nav flex-row order-md-last">
                    <div class="d-none d-md-flex me-3">
                        <!-- BEGIN THEME TOGGLE -->
                        <div class="nav-item">
                            <a href="?theme=dark" class="nav-link px-0 hide-theme-dark" title="Enable dark mode" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/moon -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                    <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454l0 .008" />
                                </svg>
                            </a>
                            <a href="?theme=light" class="nav-link px-0 hide-theme-light" title="Enable light mode" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/sun -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                    <path d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                                    <path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" />
                                </svg>
                            </a>
                        </div>
                        <!-- END THEME TOGGLE -->
                        <!-- BEGIN NOTIFICATIONS -->
                        <div class="nav-item dropdown d-none d-md-flex">
                            <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications" data-bs-auto-close="outside" aria-expanded="false">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/bell -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                    <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                    <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                                </svg>
                                <span class="badge bg-red"></span>
                            </a>
                            <!-- BEGIN NAVBAR NOTIFICATIONS -->
                            <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
                                <div class="card">
                                    <div class="card-header d-flex">
                                        <h3 class="card-title">Notifications</h3>
                                        <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
                                    </div>
                                    <div class="list-group list-group-flush list-group-hoverable">
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span class="status-dot status-dot-animated bg-red d-block"></span></div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 1</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">Change deprecated html tags to text decoration classes (#29604)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon text-muted icon-2">
                                                            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span class="status-dot d-block"></span></div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 2</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">justify-content:between ⇒ justify-content:space-between (#29734)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions show">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon text-yellow icon-2">
                                                            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span class="status-dot d-block"></span></div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 3</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">Update change-version.js (#29736)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon text-muted icon-2">
                                                            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span class="status-dot status-dot-animated bg-green d-block"></span></div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 4</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">Regenerate package-lock.json (#29730)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon text-muted icon-2">
                                                            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col">
                                                <a href="#" class="btn btn-2 w-100"> Archive all </a>
                                            </div>
                                            <div class="col">
                                                <a href="#" class="btn btn-2 w-100"> Mark all as read </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- END NAVBAR NOTIFICATIONS -->
                        </div>
                        <!-- END NOTIFICATIONS -->
                    </div>
                    <!-- BEGIN USER MENU -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
                            <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->photo }})"> </span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ auth()->user()->name }}</div>
                                <div class="mt-1 small text-secondary">{{ ucfirst($activeRole ?? 'User') }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a class="dropdown-item" href="{{ route('home.profile-index') }}">
                                <i class="fas fa-user"></i>
                                Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('auth.switch-role') }}">
                                <i class="fas fa-exchange-alt"></i>
                                Switch Role</a>
                            <a class="dropdown-item" href="{{ route('auth.logout') }}">
                                <i class="fas fa-sign-out-alt"></i>
                                Sign out</a>
                        </div>
                    </div>
                    <!-- END USER MENU -->
                </div>
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <!-- BEGIN NAVBAR MENU -->
                    <nav aria-label="Primary">
                        <!-- BEGIN NAVBAR MENU -->
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#navbar-dashboards" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-haspopup="true" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                            <path d="M5 12l-2 0l9 -9l9 9l-2 0" />
                                            <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" />
                                            <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                                        </svg></span>
                                    <span class="nav-link-title"> Dashboards </span>
                                </a>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="./index.html"> Default </a>
                                    <a class="dropdown-item" href="./dashboard-crypto.html"> Crypto </a>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#navbar-base" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-haspopup="true" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block"><!-- Download SVG icon from http://tabler.io/icons/icon/package -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1">
                                            <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                                            <path d="M12 12l8 -4.5" />
                                            <path d="M12 12l0 9" />
                                            <path d="M12 12l-8 -4.5" />
                                            <path d="M16 5.25l-8 4.5" />
                                        </svg></span>
                                    <span class="nav-link-title"> Interface </span>
                                </a>
                                <div class="dropdown-menu">
                                    <div class="dropdown-menu-columns">
                                        <div class="dropdown-menu-column">
                                            <a class="dropdown-item" href="./all-elements.html"> All Elements </a>
                                            <a class="dropdown-item" href="./accordion.html">
                                                Accordion
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./alerts.html"> Alerts </a>
                                            <div class="dropend">
                                                <a class="dropdown-item dropdown-toggle" href="#sidebar-authentication" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-haspopup="true" aria-expanded="false">
                                                    Authentication
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="./sign-in.html" class="dropdown-item"> Sign in </a>
                                                    <a href="./sign-in-link.html" class="dropdown-item"> Sign in link </a>
                                                    <a href="./sign-in-illustration.html" class="dropdown-item"> Sign in with illustration </a>
                                                    <a href="./sign-in-cover.html" class="dropdown-item"> Sign in with cover </a>
                                                    <a href="./sign-up.html" class="dropdown-item"> Sign up </a>
                                                    <a href="./forgot-password.html" class="dropdown-item"> Forgot password </a>
                                                    <a href="./terms-of-service.html" class="dropdown-item"> Terms of service </a>
                                                    <a href="./auth-lock.html" class="dropdown-item"> Lock screen </a>
                                                    <a href="./2-step-verification.html" class="dropdown-item"> 2 step verification </a>
                                                    <a href="./2-step-verification-code.html" class="dropdown-item"> 2 step verification code </a>
                                                </div>
                                            </div>
                                            <a class="dropdown-item" href="./avatars.html">
                                                Avatars
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./badges.html"> Badges </a>
                                            <a class="dropdown-item" href="./blank.html"> Blank page </a>
                                            <a class="dropdown-item" href="./buttons.html"> Buttons </a>
                                            <div class="dropend">
                                                <a class="dropdown-item dropdown-toggle" href="#sidebar-cards" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-haspopup="true" aria-expanded="false">
                                                    Cards
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="./cards.html" class="dropdown-item"> Sample cards </a>
                                                    <a href="./card-actions.html" class="dropdown-item">
                                                        Card actions
                                                        <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                                    </a>
                                                    <a href="./card-gradients.html" class="dropdown-item">
                                                        Card gradients
                                                        <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                                    </a>
                                                    <a href="./cards-masonry.html" class="dropdown-item"> Cards Masonry </a>
                                                </div>
                                            </div>
                                            <a class="dropdown-item" href="./carousel.html"> Carousel </a>
                                            <a class="dropdown-item" href="./colors.html"> Colors </a>
                                            <a class="dropdown-item" href="./datagrid.html"> Data grid </a>
                                            <a class="dropdown-item" href="./dropdowns.html"> Dropdowns </a>
                                            <div class="dropend">
                                                <a class="dropdown-item dropdown-toggle" href="#sidebar-error" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-haspopup="true" aria-expanded="false">
                                                    Error pages
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="./error-404.html" class="dropdown-item"> 404 page </a>
                                                    <a href="./error-500.html" class="dropdown-item"> 500 page </a>
                                                    <a href="./error-maintenance.html" class="dropdown-item"> Maintenance page </a>
                                                </div>
                                            </div>
                                            <a class="dropdown-item" href="./lists.html"> Lists </a>
                                            <a class="dropdown-item" href="./modals.html"> Modals </a>
                                            <a class="dropdown-item" href="./prose.html"> Prose </a>
                                        </div>
                                        <div class="dropdown-menu-column">
                                            <a class="dropdown-item" href="./navigation.html"> Navigation </a>
                                            <a class="dropdown-item" href="./offcanvas.html"> Offcanvas </a>
                                            <a class="dropdown-item" href="./pagination.html"> Pagination </a>
                                            <a class="dropdown-item" href="./patterns.html">
                                                Patterns
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./placeholder.html"> Placeholder </a>
                                            <a class="dropdown-item" href="./progress.html">
                                                Progress
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./segmented-control.html">
                                                Segmented control
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./scroll-spy.html">
                                                Scroll spy
                                                <span class="badge badge-sm bg-green-lt text-uppercase ms-auto">New</span>
                                            </a>
                                            <a class="dropdown-item" href="./social-icons.html"> Social icons </a>
                                            <a class="dropdown-item" href="./stars-rating.html"> Stars rating </a>
                                            <a class="dropdown-item" href="./steps.html"> Steps </a>
                                            <a class="dropdown-item" href="./tables.html"> Tables </a>
                                            <a class="dropdown-item" href="./tabs.html"> Tabs </a>
                                            <a class="dropdown-item" href="./tags.html"> Tags </a>
                                            <a class="dropdown-item" href="./toasts.html"> Toasts </a>
                                            <a class="dropdown-item" href="./typography.html"> Typography </a>
                                        </div>
                                    </div>
                                </div>
                            </li>

                        </ul>
                        <!-- END NAVBAR MENU -->
                    </nav>
                    <!-- END NAVBAR MENU -->
                </div>
            </div>
        </header>
        <!-- END NAVBAR  -->
        <div class="page-wrapper">
            <!-- BEGIN PAGE HEADER -->
            <!-- BEGIN PAGE HEADER -->
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <!-- Page pre-title -->
                            <div class="page-pretitle">{{ $menus }}</div>
                            <h1 class="page-title">{{ $pages }}</h1>
                        </div>
                        <!-- Page title actions -->
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END PAGE HEADER -->
            <!-- END PAGE HEADER -->
            <!-- BEGIN PAGE BODY -->
            <main id="content" class="page-body">
                <div class="container-xl">
                    {{ $slot }}
                </div>
            </main>
            <!-- END PAGE BODY -->
            <!-- BEGIN FOOTER -->
            <!--  BEGIN FOOTER  -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <nav aria-label="Footer">
                                <ul class="list-inline list-inline-dots mb-0">
                                    <li class="list-inline-item">
                                        <a href="https://github.com/tabler/tabler" target="_blank" class="link-secondary" rel="noopener">Build with Tabler</a>
                                    </li>
                                    <li class="list-inline-item">
                                        <a href="{{ config('app.source_url') }}" target="_blank" class="link-secondary" rel="noopener">Source Code NexaCampus</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    Copyright &copy; {{ date('Y F') }} -
                                    <a href="." class="link-secondary">{{ $system->app_name ?? 'Your App Name' }}</a>.
                                </li>
                                <li class="list-inline-item">
                                    <a href="./changelog.html" class="link-secondary" rel="noopener"> {{ $system->app_version ?? 'v1.0.0' }} </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
            <!--  END FOOTER  -->
            <!-- END FOOTER -->
        </div>
    </div>
    <!-- BEGIN GLOBAL SCRIPTS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script src="{{ asset('assets') }}/dist/js/tabler.min.js?1774011441" defer></script>
    @livewireScripts
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('assets/libs/tom-select/dist/js/tom-select.complete.min.js') }}"></script>
    <!-- END GLOBAL SCRIPTS -->
    <!-- BEGIN PAGE LIBRARIES -->
    @stack('scripts')
    @RegisterServiceWorkerScript
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Global listener for delete confirmation
        document.addEventListener('swal-delete', async function(event) {

            const result = await Swal.fire({
                title: 'Hapus user?',
                text: 'Data tidak bisa dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya hapus',
                cancelButtonText: 'Batal'
            });

            if (result.isConfirmed) {
                Livewire.dispatch('deleteItem', {
                    id: event.detail.id
                });
            }

        });
    </script>
    <!-- END PAGE LIBRARIES -->
</body>

</html>
