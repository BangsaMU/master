# Token Launch untuk Viewer Annotation

Fitur opt-in untuk membuka file di service annotation (meindo-annotation) **tanpa token statis** dan
**tanpa cek cookie lintas-origin**. Default paket tetap perilaku lama; app yang tidak mengubah apa pun
tidak terpengaruh. Service meindo-annotation tidak perlu diubah.

## Masalah di cara lama

| Cara lama | Akibat |
|---|---|
| Link viewer memakai `token = md5(email)` | Token statis dan bisa ditebak dari email |
| `getSignature` / `getParaf` menerima `md5(email)` | Tanda tangan / paraf siapa pun bisa diambil |
| `validate_session = true` → JS viewer memanggil `{app}/getcurrentuser` dengan cookie | Butuh CORS credentials + SameSite, sering gagal ("Please login HERE") |

## Cara kerja mode `launch`

1. App membuat link ke `GET {app}/annotation/open?file_url=..&callback=..` (route `annotation.open`,
   middleware `web` + `auth`). Klik link ini adalah navigasi top-level, jadi cookie session app terkirim
   normal. User yang belum login diarahkan ke halaman login lalu kembali ke link ini (`url.intended`).
2. Route membuat token terenkripsi (`Crypt::encryptString`, APP_KEY app) berisi user id, email, file_url,
   **session id saat ini**, guard, dan `exp`, lalu redirect 302 ke
   `{AnnotationConfig.main.URL}/viewer?app_id=..&file_url=<base64>&token=<token>&callback=<base64>`
   (parameter sama dengan link lama). Token diawali `alt1.` dan URL-safe.
3. Service annotation meneruskan token seperti biasa ke `api/getcurrentuser`, `api/getSignature`,
   `api/getParaf`. App mendekripsi token, cek kedaluwarsa, cek `file_name` (bila dikirim) sesuai file di
   token, cek user masih ada, dan **cek session id di token masih aktif dan login sebagai user yang sama**
   lewat session handler Laravel (redis / file / database). Gagal → `401`.
4. Untuk token launch, `getcurrentuser` mengembalikan `validate_session: false`, sehingga JS viewer tidak
   lagi memanggil `/getcurrentuser` dengan cookie. Pengecekan hak routing (`permission`) tetap sama.

Nama route, parameter, dan bentuk JSON respons yang lama tidak berubah. Header CORS di
`api/getcurrentuser` tetap ada.

## Konfigurasi (`.env`)

| Env | Default | Keterangan |
|---|---|---|
| `ANNOTATION_TOKEN_MODE` | `legacy` | `legacy` = helper membuat link lama (md5). `launch` = helper membuat link ke `annotation.open` |
| `ANNOTATION_ACCEPT_LEGACY_TOKEN` | `true` | Hanya berlaku di mode `launch`: masih terima `api_token` / `md5(email)` selama transisi. Set `false` bila semua link sudah lewat helper |
| `ANNOTATION_TOKEN_TTL` | `120` | Umur token launch (menit). Setelah lewat, viewer harus dibuka ulang dari app |
| `ANNOTATION_TOKEN_CHECK_SESSION` | `true` | Cek session id ke session store. Matikan hanya bila app memakai `SESSION_DRIVER=cookie` (session tidak bisa dibaca server-side) |
| `ANNOTATION_ALLOWED_HOSTS` | kosong | Host tambahan (pisah koma) yang boleh dipakai di `file_url` / `callback`. Host app sendiri (`APP_URL` dan host request) selalu boleh |

Nilai ini ada di `config('AnnotationConfig.launch.*')` (di-merge dari paket; key `main` milik app tidak
tertimpa). App boleh menimpanya dengan menambah key `launch` di `config/AnnotationConfig.php`.

Catatan:
- Token launch selalu diterima di mode apa pun (hanya bisa dibuat lewat `annotation.open`).
- `file_url` dan `callback` di `annotation.open` harus ke host yang diizinkan (lainnya `422`), supaya
  token tidak bisa dikirim ke domain lain lewat link buatan orang.
- Logout di app (session di-invalidate) atau session kedaluwarsa langsung mematikan token.
- Perubahan kecil yang berlaku untuk semua mode: request ke `getcurrentuser` / `getSignature` / `getParaf`
  **tanpa token** sekarang `401`. Sebelumnya token kosong bisa cocok ke user yang `api_token`-nya `null`.

## Helper

```php
annotation_launch_url(?string $fileUrl, ?string $callback = null): ?string
```

- Mode `legacy`: mengembalikan link viewer lama persis
  (`{URL}/viewer?app_id=..&file_url=<base64>&token=md5(email)&callback=<base64 url('api/setAnnotationId')>`),
  `null` bila belum login.
- Mode `launch`: mengembalikan `route('annotation.open', ['file_url' => .., 'callback' => ..])`.
- `$fileUrl` relatif otomatis dijadikan absolut (`url()`), sama seperti `getFixedUrl()`.
- `$callback` default `url('api/setAnnotationId')`.

Jadi kode app cukup diganti sekali memakai helper, lalu mode dipindah lewat `.env` tanpa deploy ulang kode.

## Langkah aktivasi per app (contoh GDA)

1. Update paket `bangsamu/master` ke versi yang memuat fitur ini (`composer update bangsamu/master`).
2. Ganti pembuatan link viewer memakai helper. Di GDA:
   `app/Models/RequisitionAttachment.php`, accessor `urlAnnotation()` (sekitar baris 190–215) saat ini:

   ```php
   $viewerUrl = config('AnnotationConfig.main.URL') . '/viewer';
   $appCode   = config('SsoConfig.main.APP_CODE');
   $this->url = !empty($this->doc_rev) ? $this->url . '?version=' . $this->doc_rev : $this->url;
   $fileUrl   = base64_encode(getFixedUrl($this->url));
   $token     = md5($userEmail);
   return "{$viewerUrl}?app_id={$appCode}&file_url={$fileUrl}&token={$token}&callback=" . base64_encode(url('api/setAnnotationId'));
   ```

   menjadi:

   ```php
   $fileUrl = !empty($this->doc_rev) ? $this->url . '?version=' . $this->doc_rev : $this->url;

   return annotation_launch_url(getFixedUrl($fileUrl), url('api/setAnnotationId'));
   ```

   Dengan `ANNOTATION_TOKEN_MODE=legacy` hasilnya sama persis dengan link lama, jadi perubahan kode ini
   aman di-deploy lebih dulu.

   **Jangan** ganti `responseUrlAnnotation()` (baris ±221): itu panggilan server-to-server GDA →
   `{annotation}/api/getresponseurl` (dari `RequisitionController`), tidak bisa mengikuti redirect login,
   dan `getResponseUrl` di meindo-annotation tidak memakai token tersebut.

   Catatan GDA: `User::booted()` mengisi `api_token = md5(email)`, jadi `api_token` di GDA juga bisa ditebak.
   Saat `ANNOTATION_ACCEPT_LEGACY_TOKEN=false`, `api_token` dan `md5(email)` sama-sama ditolak di endpoint
   annotation.
3. Pastikan `SESSION_DRIVER` server-side (GDA: `redis`, sudah sesuai) dan host file PDF sama dengan
   `APP_URL` / host app. Bila file disajikan dari host lain, isi `ANNOTATION_ALLOWED_HOSTS`.
4. Aktifkan di `.env` lalu `php artisan config:clear` (atau `config:cache`):

   ```dotenv
   ANNOTATION_TOKEN_MODE=launch
   ANNOTATION_ACCEPT_LEGACY_TOKEN=true
   ANNOTATION_TOKEN_TTL=120
   ```

5. Uji: buka dokumen dari GDA → viewer terbuka tanpa popup "Please login HERE"; logout di GDA lalu refresh
   viewer → `401`; buka link `annotation/open` saat belum login → diarahkan ke login lalu kembali ke viewer.
6. Setelah semua link (termasuk email / notifikasi lama) sudah lewat helper dan link md5 lama tidak dipakai
   lagi, set `ANNOTATION_ACCEPT_LEGACY_TOKEN=false`.
7. Opsional setelah transisi: `AnnotationConfig.main.VALIDATE_SESSION` boleh tetap `true`; nilai ini hanya
   dipakai untuk token lama. Untuk token launch respons selalu `validate_session: false`.

Rollback: set `ANNOTATION_TOKEN_MODE=legacy` (helper kembali membuat link md5, semua token lama diterima).

## Test paket

```bash
docker exec -u 1000:1000 -e COMPOSER_HOME=/tmp/composer -w /var/www/html/clay/packages/bangsamu/master samu-php8.3fpm_Alpine-v5_wkhtmltopdfRedis_QPDF composer update
```

```bash
docker exec -u 1000:1000 -w /var/www/html/clay/packages/bangsamu/master samu-php8.3fpm_Alpine-v5_wkhtmltopdfRedis_QPDF vendor/bin/phpunit
```

Test memakai Orchestra Testbench dengan SQLite in-memory dan session driver `file` (bukan database app).
