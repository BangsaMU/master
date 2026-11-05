{{-- Layout minimal untuk test view paket (pengganti adminlte::page milik app). --}}
<html>
<body>
@yield('header')
@yield('header-actions')
@yield('content')
@stack('js')
</body>
</html>
