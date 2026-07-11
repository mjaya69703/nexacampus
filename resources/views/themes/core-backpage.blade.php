<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    @PwaHead


    <title>{{ (isset($menus) ? $menus . ' - ' : '') . $pages . ' - ' . $academy->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- BEGIN PAGE LEVEL STYLES -->
    <link href="./libs/jsvectormap/dist/jsvectormap.css" rel="stylesheet" />
    <!-- END PAGE LEVEL STYLES -->
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <link href="{{ asset('assets') }}/dist/css/tabler.css" rel="stylesheet" />
    <!-- END GLOBAL MANDATORY STYLES -->
    <!-- BEGIN PLUGINS STYLES -->
    <link href="{{ asset('assets') }}/dist/css/tabler-flags.css" rel="stylesheet" />
    <link href="{{ asset('assets') }}/dist/css/tabler-socials.css" rel="stylesheet" />
    <link href="{{ asset('assets') }}/dist/css/tabler-payments.css" rel="stylesheet" />
    <link href="{{ asset('assets') }}/dist/css/tabler-vendors.css" rel="stylesheet" />
    <link href="{{ asset('assets') }}/dist/css/tabler-marketing.css" rel="stylesheet" />
    <link href="{{ asset('assets') }}/dist/css/tabler-themes.css" rel="stylesheet" />
    <!-- END PLUGINS STYLES -->
    <!-- BEGIN DEMO STYLES -->
    <link href="./preview/css/demo.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <!-- END DEMO STYLES -->
    @yield('custom-css')
    <style>
        :root {
            --app-sidebar-width: 15rem;
            --app-primary: #6842f4;
            --app-primary-bright: #8b6cff;
            --app-primary-deep: #3f249c;
            --app-shell-bg: linear-gradient(165deg, #8b6cff 0%, #5d3fd3 48%, #372083 100%);
            --app-topbar-bg: #ffffff;
            --app-topbar-border: rgba(93, 63, 211, 0.14);
            --app-shell-edge: #4d2fc0;
            --app-sidebar-bg: var(--app-shell-bg);
            --app-sidebar-border: rgba(255, 255, 255, 0.18);
            --app-page-bg: #f7f3ff;
            --app-surface-bg: #ffffff;
            --app-muted-bg: #f0eaff;
        }

        [data-bs-theme=dark],
        body[data-bs-theme=dark] {
            --app-primary: #7b61ff;
            --app-primary-bright: #a78bff;
            --app-primary-deep: #2b176b;
            --app-shell-bg: linear-gradient(165deg, #8b6cff 0%, #5d3fd3 46%, #241157 100%);
            --app-topbar-bg: #1a1029;
            --app-topbar-border: rgba(167, 139, 255, 0.16);
            --app-shell-edge: #3d228f;
            --app-sidebar-bg: var(--app-shell-bg);
            --app-sidebar-border: rgba(255, 255, 255, 0.16);
            --app-page-bg: #130b22;
            --app-surface-bg: #211632;
            --app-muted-bg: #2b1c43;
        }

        html,
        body {
            overflow-x: hidden;
            background: var(--app-page-bg);
        }

        .page {
            background: var(--app-page-bg);
        }

        @media (min-width: 992px) {
            .page > .navbar-vertical.navbar-expand-lg {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: var(--app-sidebar-width);
                overflow-y: auto;
                overflow-x: hidden;
                scrollbar-width: none;
                background: var(--app-sidebar-bg);
                transform: translateZ(0);
                z-index: 1030;
            }

            .page > .navbar-vertical.navbar-expand-lg::-webkit-scrollbar {
                width: 0;
                height: 0;
            }

            .page > .navbar-vertical.navbar-expand-lg::after {
                content: "";
                position: fixed;
                top: 0;
                right: -2px;
                bottom: 0;
                width: 3px;
                background: var(--app-shell-edge);
                pointer-events: none;
            }

            .page > header.navbar {
                background: var(--app-topbar-bg);
                border-bottom: 1px solid var(--app-topbar-border);
                color: inherit;
            }

            .page > header.navbar .nav-link,
            .page > header.navbar .navbar-nav .nav-link,
            .page > header.navbar .text-secondary {
                color: inherit !important;
            }

            .page > header.navbar .nav-link:hover,
            .page > header.navbar .nav-link:focus {
                color: var(--app-primary) !important;
            }

            .page > .navbar-vertical.navbar-expand-lg ~ .navbar,
            .page > .navbar-vertical.navbar-expand-lg ~ .page-wrapper {
                margin-left: var(--app-sidebar-width) !important;
            }

            .page > .navbar-vertical.navbar-expand-lg .navbar-brand img {
                max-width: 12.5rem;
                height: auto !important;
                object-fit: contain;
            }
        }

        .form-label,
        .form-control {
            margin-top: 0 !important;
        }
    </style>


    <!-- BEGIN CUSTOM FONT -->
    <style>
        @import url("https://rsms.me/inter/inter.css");
    </style>
    <!-- END CUSTOM FONT -->
</head>

<body class="layout-fluid">
    <!-- BEGIN GLOBAL THEME SCRIPT -->
    <script src="{{ asset('assets') }}/dist/js/tabler-theme.min.js"></script>
    <!-- END GLOBAL THEME SCRIPT -->
    <div class="page">
        <!--  BEGIN SIDEBAR  -->
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <!-- BEGIN NAVBAR TOGGLER -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                    aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <!-- END NAVBAR TOGGLER -->
                <!-- BEGIN NAVBAR LOGO -->
                <div class="navbar-brand navbar-brand-autodark">
                    <a href="{{ route('root.home-index') }}" aria-label="Tabler">
                        <img src="{{ $academy->logo_horizontal }}" style="height: 64px; width:200px;"
                            alt="Neco Siakad Logo">
                    </a>
                </div>
                <!-- END NAVBAR LOGO -->
                <div class="navbar-nav flex-row d-lg-none">
                    <div class="nav-item d-none d-lg-flex me-3">
                        <div class="btn-list">
                            <a href="https://github.com/tabler/tabler" class="btn btn-5" target="_blank"
                                rel="noreferrer">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/brand-github -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                    <path
                                        d="M9 19c-4.3 1.4 -4.3 -2.5 -6 -3m12 5v-3.5c0 -1 .1 -1.4 -.5 -2c2.8 -.3 5.5 -1.4 5.5 -6a4.6 4.6 0 0 0 -1.3 -3.2a4.2 4.2 0 0 0 -.1 -3.2s-1.1 -.3 -3.5 1.3a12.3 12.3 0 0 0 -6.2 0c-2.4 -1.6 -3.5 -1.3 -3.5 -1.3a4.2 4.2 0 0 0 -.1 3.2a4.6 4.6 0 0 0 -1.3 3.2c0 4.6 2.7 5.7 5.5 6c-.6 .6 -.6 1.2 -.5 2v3.5" />
                                </svg>
                                Source code
                            </a>
                            <a href="https://github.com/sponsors/codecalm" class="btn btn-6" target="_blank"
                                rel="noreferrer">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/heart -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon text-pink icon-2">
                                    <path
                                        d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />
                                </svg>
                                Sponsor
                            </a>
                        </div>
                    </div>
                    <div class="d-none d-lg-flex">
                        <div class="nav-item">
                            <a href="?theme=dark" class="nav-link px-0 hide-theme-dark" title="Enable dark mode"
                                data-bs-toggle="tooltip" data-bs-placement="bottom">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/moon -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                    <path
                                        d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454z" />
                                </svg>
                            </a>
                            <a href="?theme=light" class="nav-link px-0 hide-theme-light" title="Enable light mode"
                                data-bs-toggle="tooltip" data-bs-placement="bottom">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/sun -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                    <path d="M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
                                    <path
                                        d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" />
                                </svg>
                            </a>
                        </div>
                        <div class="nav-item dropdown d-none d-md-flex">
                            <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                                aria-label="Show notifications" data-bs-auto-close="outside" aria-expanded="false">
                                <!-- Download SVG icon from http://tabler.io/icons/icon/bell -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                    <path
                                        d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                    <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                                </svg>
                                <span class="badge bg-red"></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
                                <div class="card">
                                    <div class="card-header d-flex">
                                        <h3 class="card-title">Notifications</h3>
                                        <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
                                    </div>
                                    <div class="list-group list-group-flush list-group-hoverable">
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span
                                                        class="status-dot status-dot-animated bg-red d-block"></span>
                                                </div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 1</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">Change
                                                        deprecated html tags to text decoration classes (#29604)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                            height="24" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="icon text-muted icon-2">
                                                            <path
                                                                d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" />
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
                                                    <div class="d-block text-secondary text-truncate mt-n1">
                                                        justify-content:between ⇒ justify-content:space-between (#29734)
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions show">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                            height="24" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="icon text-yellow icon-2">
                                                            <path
                                                                d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" />
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
                                                    <div class="d-block text-secondary text-truncate mt-n1">Update
                                                        change-version.js (#29736)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                            height="24" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="icon text-muted icon-2">
                                                            <path
                                                                d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item">
                                            <div class="row align-items-center">
                                                <div class="col-auto"><span
                                                        class="status-dot status-dot-animated bg-green d-block"></span>
                                                </div>
                                                <div class="col text-truncate">
                                                    <a href="#" class="text-body d-block">Example 4</a>
                                                    <div class="d-block text-secondary text-truncate mt-n1">Regenerate
                                                        package-lock.json (#29730)</div>
                                                </div>
                                                <div class="col-auto">
                                                    <a href="#" class="list-group-item-actions">
                                                        <!-- Download SVG icon from http://tabler.io/icons/icon/star -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                            height="24" viewBox="0 0 24 24" fill="none"
                                                            stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            class="icon text-muted icon-2">
                                                            <path
                                                                d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" />
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
                        </div>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown"
                            aria-label="Open user menu">
                            <span class="avatar avatar-sm"
                                style="background-image: url({{ $user == null ? '' : $user->photo }})"> </span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $user == null ? '' : $user->name }}</div>
                                <div class="mt-1 small text-secondary">{{ ucfirst($activeRole) }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="{{ route($activeRole.'.profile-index') }}" class="dropdown-item">Profile</a>
                            <!-- <a href="#" class="dropdown-item">Feedback</a> -->
                            <div class="dropdown-divider"></div>
                            <a href="{{ route($activeRole.'.auth.handle-logout') }}" class="dropdown-item">Logout</a>
                        </div>
                    </div>
                </div>
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <!-- BEGIN NAVBAR MENU -->
                    @include('themes.partials.sidebar-index')
                    <!-- END NAVBAR MENU -->
                </div>
            </div>
        </aside>
        <!--  END SIDEBAR  -->
        <!-- BEGIN NAVBAR  -->
        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
            <div class="container-xl">
                <!-- BEGIN NAVBAR TOGGLER -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <!-- END NAVBAR TOGGLER -->
                <div class="navbar-nav flex-row order-md-last">
                    <div class="d-none d-md-flex">
                        @include('themes.partials.buttons-navbar')
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown"
                            aria-label="Open user menu">
                            <span class="avatar avatar-sm"
                                style="background-image: url({{ $user == null ? asset('storage/images/profile/default.jpg') : $user->photo }})">
                            </span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $user == null ? 'Guest Name' : $user->name }}</div>
                                <div class="mt-1 small text-secondary">
                                    {{ $user == null ? 'Guest Position' : ucfirst($activeRole) }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="#" class="dropdown-item">Status</a>
                            <a href="{{ route($activeRole.'.profile-index') }}" class="dropdown-item">Profile</a>
                            <a href="#" class="dropdown-item">Feedback</a>
                            <div class="dropdown-divider"></div>
                            <a href="./settings.html" class="dropdown-item">Settings</a>
                            <a href="{{ route($activeRole.'.auth.handle-logout') }}" class="dropdown-item">Logout</a>
                        </div>
                    </div>
                </div>
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <!-- BEGIN NAVBAR MENU -->
                    <ul class="navbar-nav">


                        <select class="form-select form-select-sm form-select-navbar" aria-label="Select theme">
                            <option value="" selected>Pilih tahun akademik</option>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </ul>
                    <!-- END NAVBAR MENU -->
                </div>
            </div>
        </header>
        <!-- END NAVBAR  -->
        <div class="page-wrapper">
            <!-- BEGIN PAGE HEADER -->
            <div class="page-header d-print-none" aria-label="Page header">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <!-- Page pre-title -->
                            <div class="page-pretitle">{{ $menus }}</div>
                            <h2 class="page-title">{{ $pages }}</h2>
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
            <!-- BEGIN PAGE BODY -->
            <div class="page-body">
                <div class="container-xl">
                    <!-- Debug Error Messages -->
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>ERROR:</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>VALIDATION ERRORS:</strong>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @yield('content')
                </div>
            </div>
            <!-- END PAGE BODY -->
            <!--  BEGIN FOOTER  -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item"><a href="https://docs.tabler.io" target="_blank"
                                        class="link-secondary" rel="noopener">Documentation</a></li>
                                <li class="list-inline-item">
                                    <a href="{{ config('app.source_url') }}" target="_blank" class="link-secondary"
                                        rel="noopener">Source code</a>
                                </li>
                                <li class="list-inline-item">
                                    <a href="https://github.com/sponsors/codecalm" target="_blank"
                                        class="link-secondary" rel="noopener">
                                        <!-- Download SVG icon from http://tabler.io/icons/icon/heart -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="icon text-pink icon-inline icon-4">
                                            <path
                                                d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />
                                        </svg>
                                        Sponsor
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    Copyright &copy; {{ \Carbon\Carbon::now()->translatedFormat('F Y') }}
                                    <a href="." class="link-secondary">{{ $academy->name }}</a>.
                                </li>
                                <li class="list-inline-item">
                                    <a href="./changelog.html" class="link-secondary" rel="noopener">Tabler v1.2.0
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
            <!--  END FOOTER  -->
        </div>
    </div>

    @include('themes.partials.settings')
    <!-- BEGIN PAGE LIBRARIES -->
    @RegisterServiceWorkerScript
    <script src="{{ asset('assets') }}/libs/apexcharts/dist/apexcharts.min.js" defer></script>
    <script src="{{ asset('assets') }}/libs/jsvectormap/dist/jsvectormap.min.js" defer></script>
    <script src="{{ asset('assets') }}/libs/jsvectormap/dist/maps/world.js" defer></script>
    <script src="{{ asset('assets') }}/libs/jsvectormap/dist/maps/world-merc.js" defer></script>
    <!-- END PAGE LIBRARIES -->
    <!-- BEGIN GLOBAL MANDATORY SCRIPTS -->
    <script src="{{ asset('assets') }}/dist/js/tabler.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
    <!-- END GLOBAL MANDATORY SCRIPTS -->
    <!-- BEGIN DEMO SCRIPTS -->
    <script src="./preview/js/demo.min.js" defer></script>
    <!-- END DEMO SCRIPTS -->
    <!-- BEGIN PAGE SCRIPTS -->
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'Apakah kamu yakin?',
                text: "Data yang sudah dihapus tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + id).submit();
                }
            })
        }

        function confirmForceDelete(id) {
            Swal.fire({
                title: 'Apakah kamu yakin?',
                text: "Data akan dihapus secara permanen dan tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, hapus permanen!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('force-delete-form-' + id).submit();
                }
            })
        }
    </script>

    @stack('scripts')

    @yield('custom-js')
    <!-- END PAGE SCRIPTS -->
</body>

</html>
