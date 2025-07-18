<!-- resources/views/layouts/includes/navbar-top.blade.php -->
<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"
            aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
            <a href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}" class="text-decoration-none">
                @php
                    $logo = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.logo');
                @endphp

                @if($logo)
                    <img src="{{ asset($logo) }}" width="110" height="32" alt="{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel'))  }}" class="navbar-brand-image p-1">
                @else
                    <span class="fs-4 fw-bold">{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel'))  }}</span>
                @endif
            </a>
        </h1>
        <div class="navbar-nav flex-row order-md-last">
            <div class="nav-item dropdown d-none d-md-flex me-3">
                <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                    aria-label="Show notifications">
                    <i class="ti ti-bell"></i>
                    <span class="badge bg-red"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <h3 class="m-0">Notifications</h3>
                                <a href="#" class="ms-auto text-decoration-none">Mark all as read</a>
                            </div>
                            <div class="list-group list-group-flush list-group-hoverable">
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto"><span
                                                class="status-dot status-dot-animated bg-red d-block"></span></div>
                                        <div class="col text-truncate">
                                            <a href="#" class="text-body d-block">Example notification 1</a>
                                            <div class="d-block text-muted text-truncate mt-n1">
                                                This is an example notification message.
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <a href="#" class="list-group-item-actions">
                                                <i class="ti ti-star"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto"><span class="status-dot d-block"></span></div>
                                        <div class="col text-truncate">
                                            <a href="#" class="text-body d-block">Example notification 2</a>
                                            <div class="d-block text-muted text-truncate mt-n1">
                                                Another example notification item.
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <a href="#" class="list-group-item-actions">
                                                <i class="ti ti-star"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                    aria-label="Open user menu">
                    <span class="avatar avatar-sm"
                        style="background-image: url(https://www.gravatar.com/avatar/{{ md5(auth()->user()->email ?? 'default@example.com') }}?d=mp)"></span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->name ?? 'Guest User' }}</div>
                        <div class="mt-1 small text-muted">{{ auth()->user()->email ?? 'guest@example.com' }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">Profile</a>
                    <a href="#" class="dropdown-item disabled">Settings</a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
