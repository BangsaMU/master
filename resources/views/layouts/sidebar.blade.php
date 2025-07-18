<!-- resources/views/layouts/sidebar.blade.php -->
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
            <a href="{{ route('dashboard') }}">
                @php
                    $logo = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.logo');
                @endphp

                @if($logo)
                    <img src="{{ asset($logo) }}" width="110" height="32" alt="{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel'))  }}" class="navbar-brand-image p-1">
                @else
                    <h1 class="mb-0 card-text">{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel'))  }}</h1>
                @endif
            </a>
        </div>
        <!-- END NAVBAR LOGO -->

        <div class="collapse navbar-collapse" id="sidebar-menu">
            <!-- BEGIN NAVBAR MENU -->
            <ul class="navbar-nav pt-lg-3">
                <!-- Use your menu component here -->
                <x-menu />
            </ul>
            <!-- END NAVBAR MENU -->
        </div>
    </div>
</aside>
