<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel')))</title>

    <!-- Theme initialization (prevents flash)  -->
    <script>
        (function () {
            var t = localStorage.getItem("stisla-theme");
            if (t === "dark" || t === "light") document.documentElement.dataset.theme = t;
        })();
    </script>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Meridian Design System Stylesheet -->
    <link rel="stylesheet" href="{{ asset('assets/css/meridian.css') }}" />
    <link href="{{ asset('assets/vendor/select2.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-5-theme.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('datatables/dataTables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fancybox.css') }}" />

    @stack('styles')
    @stack('css')
</head>

<body class="bg-background text-foreground antialiased">
    <div class="app-shell" data-stisla-app-shell data-stisla-app-shell-auto-collapse="true">

        {{-- 1. Sidebar Navigation --}}
        <aside class="sidebar sidebar--lg sidebar--app" data-stisla-sidebar>
            {{-- Brand Header --}}
            <header class="sidebar__header p-4 border-b border-border flex items-center justify-between">
                <a class="sidebar__brand flex items-center gap-2 font-bold text-lg text-primary" href="{{ url('/') }}">
                    @php
                        $userCompanyLogo = get_user_company_logo();
                    @endphp
                    @if($userCompanyLogo)
                        <img src="{{ $userCompanyLogo }}" alt="Company Logo" class="h-7 w-auto max-w-[140px] object-contain" style="background-color: transparent !important; background: transparent !important; border: none !important; box-shadow: none !important;" onError="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="1.25em" height="1.25em" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                            <path d="M12 1.5l3.4 7.1 7.1 3.4-7.1 3.4-3.4 7.1-3.4-7.1L1.5 12l7.1-3.4z" opacity=".45"/>
                            <path d="M12 1.5l3.4 7.1L12 12 8.6 8.6z"/>
                        </svg>
                    @else
                        <img src="{{ asset('logo.png') }}" alt="Logo" class="h-7 w-auto max-w-[140px] object-contain" style="background-color: transparent !important; background: transparent !important; border: none !important; box-shadow: none !important;" onError="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="1.25em" height="1.25em" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                            <path d="M12 1.5l3.4 7.1 7.1 3.4-7.1 3.4-3.4 7.1-3.4-7.1L1.5 12l7.1-3.4z" opacity=".45"/>
                            <path d="M12 1.5l3.4 7.1L12 12 8.6 8.6z"/>
                        </svg>
                    @endif
                    <span>{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel')) }}</span>
                </a>
            </header>

            {{-- Quick Search --}}
            <div class="sidebar__search p-3 border-b border-border">
                <div class="input-group input-group--search">
                    <span class="input-group__text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                            <g fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="11.5" cy="11.5" r="9.5"/>
                                <path stroke-linecap="round" d="M18.5 18.5L22 22"/>
                            </g>
                        </svg>
                    </span>
                    <input type="search" class="input" placeholder="Search menu..." aria-label="Search menu" onkeyup="filterSidebarMenu(this.value)" />
                </div>
            </div>

            {{-- Navigation Menu --}}
            <div class="sidebar__content p-2 overflow-y-auto">
                <nav class="sidebar__menu">
                    <div class="sidebar__group">
                        <span class="sidebar__group-title uppercase text-xs text-muted-foreground font-bold px-3 py-1">Main Menu</span>
                        <ul class="sidebar__list flex flex-col gap-1 list-none p-0 m-0" id="sidebar-menu-list">
                            <x-master::menu />
                        </ul>
                    </div>
                </nav>
            </div>

            {{-- Sidebar Footer --}}
            <footer class="sidebar__footer p-3 border-t border-border mt-auto">
                <ul class="sidebar__list flex flex-col gap-1 list-none p-0 m-0 mb-2">
                    <li class="sidebar__item">
                        <a class="sidebar__button flex items-center gap-2 p-2 rounded-md transition-colors hover:bg-surface-2" href="{{ url('/profile') }}">
                            <i class="fas fa-cog text-muted-foreground"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="sidebar__item">
                        <a class="sidebar__button flex items-center gap-2 p-2 rounded-md text-danger transition-colors hover:bg-surface-2" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Log out</span>
                        </a>
                        <form id="logout-form-sidebar" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                    </li>
                </ul>
                <div class="copyright text-xs text-muted-foreground pt-2 border-t border-border">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </div>
            </footer>
        </aside>

        {{-- 2. Main App Shell Content --}}
        <main class="app-shell__main">
            {{-- Topbar Navbar Header --}}
            <header class="navbar flex items-center justify-between px-4 border-b border-border bg-surface">
                <!-- Sidebar Toggle Button -->
                <button type="button" class="button button--ghost button--neutral button--icon-only button--flush-start" data-stisla-app-shell-toggle="auto" aria-label="Toggle sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M20 7H4m16 5H4m16 5H4"/></svg>
                </button>

                <!-- Global Search Bar -->
                <div class="input-group input-group--search hidden lg:flex w-72 ms-4">
                    <span class="input-group__text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11.5" cy="11.5" r="9.5"/><path stroke-linecap="round" d="M18.5 18.5L22 22"/></g></svg>
                    </span>
                    <input type="search" class="input" placeholder="Search master data..." aria-label="Search" />
                </div>

                <!-- User Profile & Actions -->
                <div class="ms-auto flex items-center gap-2">
                    <!-- Theme Toggle Button -->
                    <button type="button" class="button button--ghost button--neutral button--icon-only" data-theme-toggle aria-label="Toggle theme">
                        <i class="fas fa-moon"></i>
                    </button>

                    @if(Auth::user())
                        <div class="menu">
                            <button type="button" class="button button--ghost button--neutral flex items-center gap-2" data-stisla-menu-trigger="topbarUser" aria-haspopup="menu" aria-expanded="false">
                                <span class="hidden sm:inline font-medium">{{ Auth::user()->name }}</span>
                                <span class="avatar avatar--sm avatar--circle">
                                    <span class="avatar__fallback">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</span>
                                </span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <div class="menu__popup w-48 shadow-xl" id="topbarUser" data-stisla-menu role="menu" data-state="closed">
                                <div class="menu__group">
                                    <h3 class="menu__group-label">{{ Auth::user()->name }}</h3>
                                    <a href="{{ url('/profile') }}" class="menu__item" role="menuitem"><i class="fas fa-user me-2"></i> Profile</a>
                                </div>
                                <hr class="menu__separator" role="separator" />
                                <a href="{{ route('logout') }}" class="menu__item menu__item--danger" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" role="menuitem"><i class="fas fa-sign-out-alt me-2"></i> Log out</a>
                            </div>
                        </div>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                    @endif
                </div>
            </header>

            {{-- 3. Page Content Area --}}
            <div class="page content">
                <div class="content__container">
                    @hasSection('header')
                        <header class="page__header flex items-center justify-between gap-4 flex-wrap mb-4">
                            <div class="page__headline">
                                @yield('header')
                            </div>
                            @hasSection('header-actions')
                                <div class="page__action flex items-center gap-2 ms-auto">
                                    @yield('header-actions')
                                </div>
                            @endif
                        </header>
                    @endif

                    <div class="page__body">
                        @yield('content')
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('jquery.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3"></script>
    <script type="module" src="https://cdn.jsdelivr.net/npm/@stisla/vanilla@3/dist/stisla.js"></script>
    <script src="{{ asset('assets/js/app-shell.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}"></script>
    <script src="{{ asset('assets/js/charts.js') }}"></script>
    <script src="{{ asset('assets/js/order-form.js') }}"></script>
    <script src="{{ asset('assets/js/table-select.js') }}"></script>
    <script src="{{ asset('assets/vendor/select2.min.js') }}"></script>
    <script src="{{ asset('datatables/dataTables.js') }}"></script>
    <script src="{{ asset('assets/js/fancybox.umd.js') }}"></script>

    <script>
        function filterSidebarMenu(query) {
            query = query.toLowerCase();
            $('#sidebar-menu-list li').each(function() {
                var text = $(this).text().toLowerCase();
                if (text.indexOf(query) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    </script>
    @stack('scripts')
    @stack('js')
</body>

</html>
