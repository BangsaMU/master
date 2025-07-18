<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('application.name', config('app.name', 'Laravel'))  }}</title>

    @php
        $theme = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.theme', 'default');
        $primaryColor = Bangsamu\LibraryClay\Controllers\LibraryClayController::getSettings('appearance.primary_color', '#206bc4');
    @endphp

    <link rel="stylesheet" href="{{ asset('tabler.min.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/1.35.0/iconfont/tabler-icons.min.css">

    <!-- Custom styles -->
    <style>
    </style>

    @yield('styles')
    @stack('styles')
</head>

<body class="d-flex flex-column">
    <div class="page page-center">
        @yield('content')
    </div>

    <script src="{{ asset('tabler.min.js') }}"></script>
</body>

</html>
