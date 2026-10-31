<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Membuka Master Data…</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; color: #343a40; }
        button { padding: .5rem 1rem; border: 0; border-radius: .25rem; background: #0d6efd; color: #fff; cursor: pointer; }
    </style>
</head>
<body>
    {{-- token dikirim lewat POST (tidak tercatat di access log / riwayat browser), berlaku singkat dan sekali pakai --}}
    <form id="master-launch" method="POST" action="{{ $action }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <p>Membuka aplikasi Master Data…</p>
        <noscript><button type="submit">Lanjutkan ke Master Data</button></noscript>
    </form>
    <script>document.getElementById('master-launch').submit();</script>
</body>
</html>
