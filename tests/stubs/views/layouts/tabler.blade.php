{{-- Layout minimal untuk test view paket (pengganti layout tabler milik app). --}}
<html>
<body>
@yield('header')
@yield('header-actions')
@yield('content')
@stack('js')
</body>
</html>
