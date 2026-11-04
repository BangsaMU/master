# Pemilik data master (app_code + company_id), permission seragam, dan Edit di Master Data (v3.0, skrip setup v3.1)

## Konsep

| app_code | company_id | Arti |
|---|---|---|
| `GLOBAL_APP_CODE` (APP03) | 0 | Data bersama, dikelola tim Master Data, dipakai semua aplikasi lewat filter |
| APPxx | 0 | Milik aplikasi APPxx, berlaku untuk semua company di aplikasi itu |
| APPxx | N | Milik aplikasi APPxx, khusus company N (`master_company.id`) |

- **Scope ditentukan kolom.** Tabel yang punya `app_code` (dan `company_id`) otomatis ber-scope. Tabel tanpa kolom
  itu tetap global.
- **Kode unik berpasangan dengan pemiliknya.** Kode JKT boleh ada di APP03 dan APP32, setelah tabelnya diaktifkan
  di saklar transisi (lihat di bawah).
- **Company 0 tidak boleh bersamaan dengan company N.** Dalam satu aplikasi, kode yang sama tidak boleh ada di
  company 0 dan company N sekaligus (`MasterScope::assertNoCompanyConflict`).

### Saklar transisi unik per pemilik (`MASTER_UNIQUE_PER_OWNER`)

Unik per pemilik (kode sama boleh di aplikasi/company lain) baru aktif untuk tabel yang tercantum di
`MASTER_UNIQUE_PER_OWNER` (dipisah koma, `*` = semua tabel ber-scope). Defaultnya kosong:

| Tabel | Selama saklar kosong |
|---|---|
| `master_department` | unik per `app_code`, sama seperti v2.4 |
| tabel ber-scope lain | kode tetap unik global |

Alasan: aplikasi lama (v2.x) menyalin tabel master ke DB lokal yang index uniknya masih global (`uom_code`,
`item_code`, ...), dan sinkronnya memakai upsert per id. Di MySQL, upsert juga bentrok di index unik lain. Bila
`meindo_master` berisi dua baris berkode sama, baris kedua **menimpa diam-diam** baris pertama di salinan lokal, tanpa
error.

Aktifkan per tabel di aplikasi Master Data (hub) hanya setelah **semua** aplikasi yang menyalin tabel itu:
1. Memakai v3.
2. Menjalankan `php artisan master:scope-upgrade` di DB lokalnya.

Contoh: `MASTER_UNIQUE_PER_OWNER=master_department,master_uom`.

## Menaikkan tabel

```bash
php artisan master:scope-upgrade --dry-run                 # rencana: tabel ber-app_code diberi company_id
php artisan master:scope-upgrade                           # jalankan
php artisan master:scope-upgrade --table=master_location   # tabel global: + app_code (APP03) + company_id
```

- **Perubahan:** index unik lama diperluas dengan `app_code`/`company_id`, dan index ganda dirapikan. Tidak ada data
  yang diubah.
- **Ditolak:** `master_employee`, `master_status`, `master_increment`, `master_project_detail`, `master_company`, dan
  tabel bukan data ber-pemilik.
- **Urutan:** jalankan di DB hub (master) dulu, baru di DB lokal tiap aplikasi klien. Sinkronisasi hanya menyalin
  kolom yang ada di kedua sisi.

### Kolom lain yang ada di DB master (`master:schema-columns`, sejak v3.3)

```bash
php artisan master:schema-columns --dry-run                # klien: kolom DB master yang belum ada di tabel sinkron lokal
php artisan master:schema-columns [--table=master_uom]     # tambahkan (NULL-able, tipe dari DB master, tanpa default/index)
```

Kolom pemilik `app_code`/`company_id` tidak ditambahkan di sini (pakai `master:scope-upgrade`). Di hub tidak berlaku.
Tipe yang tidak dikenali dan kolom auto increment dilewati (ditulis di keluaran).

### Banner pengelola aplikasi (`master::partials.schema-notice`, sejak v3.3)

Pasang sekali di layout aplikasi (setelah login), mis. di atas konten:

```blade
@include('master::partials.schema-notice')
```

User dengan permission `MASTER_SCHEMA_NOTICE_PERMISSION` (default `is_admin`; SPB memakai `is_root`) melihat banner bila
ada tabel yang perlu `master:scope-upgrade` (sama dengan `master:scope-doctor`) atau kolom DB master yang belum ada di
tabel sinkron lokal. Banner berisi perintah siap salin: `docker exec -u <pemilik file artisan> -w <folder app>
<container> php artisan ...` (container = `MASTER_DOCKER_CONTAINER` atau hostname container, hanya bila aplikasi
berjalan di Docker), versi tanpa Docker, dan skrip setup. Hasil cek disimpan di cache 1 jam (`MasterSchemaStatus`) dan
dikosongkan kedua perintah perbaikan, jadi banner hilang begitu perintahnya selesai. Gagal dicek (mis. DB master tidak
terjangkau) = banner tidak tampil, halaman tidak ikut gagal.

### Tombol Sync halaman master (sejak v3.3)

Tombol Sync (`api/master-{tabel}`, juga webhook `api/sync-master` bertoken) kini hanya menyalin kolom yang ada di
tabel lokal, seperti sinkron otomatis. Sebelumnya baris gagal "Unknown column" bila DB master punya kolom yang belum
ada di lokal (mis. `company_id` sebelum `master:scope-upgrade` lokal), walau hasil keseluruhan tertulis sukses.

## Permission seragam

- **Format:** `{read|create|update|delete}_{tabel tanpa master_}`, mis. `update_item_code`, `create_item_group`.
  Sumbernya `Support\MasterModules`.
- **Alias lama:** `*_item` dan `*_itemgroup` tetap diterima selama transisi.
- **`php artisan master:permissions [--dry-run]`:** menggabungkan alias ke nama baru, lalu membuat yang belum ada.
  - Nama baru belum ada: alias cukup diganti nama (id dan semua role tetap).
  - Keduanya ada: baris ber-id lebih kecil dipertahankan dan diberi nama baru, role pemegang baris lain dipindahkan.
    Id lama biasanya yang dirujuk seeder/snapshot aplikasi (mis. `PermissionsTableSeeder` SPB dengan id tetap).
  - `--keep-alias`: nama baru dibuat dan diberi role yang sama, alias tetap ada. Untuk masa transisi selama kode lain
    di DB yang sama masih memakai nama lama; jalankan lagi tanpa opsi ini setelahnya.

## Aturan izin (`Services\MasterAccess`)

1. **Hub (`MASTER_HUB=true`):** `is_admin` dan permission master lokal (tim data pusat) boleh semua pemilik.
2. **Hub dengan session delegasi:**
   - Modul + aksi harus didelegasikan.
   - Baris harus milik aplikasi + company asal.
   - Delegasi `all_co` boleh semua company aplikasi itu.
3. **Aplikasi klien:**
   - Perlu permission lokal, dan baris harus milik aplikasi ini + company user.
   - `ALL_COMPANY_PERMISSION` (default `is_admin`) boleh semua company aplikasi ini, termasuk company 0.
   - `is_admin` klien tetap tidak bisa mengubah data aplikasi lain atau data bersama.
4. **Penegakan di controller paket:**
   - store/destroy memanggil `authorizeStore`/`authorize` (403).
   - Form edit baris di luar scope menjadi hanya-baca.
   - Form menampilkan "Pemilik Data" (tim master memilih pemilik data baru).
5. **Memindahkan pemilik baris lama (sejak v3.4):**
   - Hanya `is_admin`. Isian pemilik dari user lain diabaikan (pemilik tetap).
   - Tujuan wajib terdaftar aktif: aplikasi di `master_apps` (atau aplikasi ini / data bersama), company 0 atau ada di
     `master_company`. Berlaku juga untuk pemilik pilihan tim master pada data baru di hub.
   - Wajib dikonfirmasi: `confirm_owner_change=1` (`MasterScope::CONFIRM_OWNER_FIELD`), diisi dialog di form
     (`partials.master-owner`). Tanpa itu simpan ditolak dengan pesan validasi, termasuk bila form dikirim langsung.
   - Dialog memuat sebelum → sesudah dan dampaknya: data bersama APP03 yang dipindah hilang dari daftar/pilihan
     aplikasi lain yang menyaring data bersama, dan hak "Edit di Master Data" ikut pindah.
   - Pemilik lama yang tidak (lagi) terdaftar tetap muncul terpilih di form, supaya menyimpan form tidak memindahkan
     pemilik ke opsi pertama tanpa disadari. Mengubah isi baris seperti itu tetap bisa tanpa konfirmasi.
   - Perpindahan tercatat di log data (`Loggable`: `app_code`/`company_id` sebelum dan sesudah).

## Edit di Master Data tanpa login ulang

Klien (mis. SPB):

```bash
php artisan master:launch-key        # cetak MASTER_LAUNCH_PRIVATE_KEY (.env) + perintah daftar di hub
```

`.env` klien: `MASTER_DIRECT_EDIT=false`, `MASTER_EDIT_URL=https://master-data...`, `MASTER_LAUNCH_PRIVATE_KEY=...`.

Hub (Master Data):

```bash
php artisan master:launch-key --register=APP32 --public=<kunci publik>
php artisan master:launch-key --register=APP32 --modules=master_item_code,master_uom   # opsional, default semua tabel ber-scope
```

`.env` hub: `MASTER_HUB=true`.

**Alur:**
1. Tombol Edit/Tambah membuka `master-launch/go/{slug}/{id?}` di klien.
2. Klien mengecek izin + pemilik baris, lalu menandatangani token (Ed25519). Token berisi email, company user,
   `all_co`, dan modul + aksi yang dimiliki user di klien, lalu dikirim lewat POST otomatis ke `{MASTER_EDIT_URL}/master-launch`.
3. Hub memverifikasi:
   - tanda tangan dengan kunci publik aplikasi;
   - `aud`, masa berlaku (`LAUNCH_TTL`, default 120 detik), dan jti sekali pakai;
   - user aktif dan terdaftar di aplikasi asal (`list_apps_id`);
   - allowlist modul.
   Bila modul halaman tujuan (mis. Department) tidak ikut didelegasikan, hub menolak 403 dengan alasannya (belum ada
   di allowlist aplikasi, user tidak punya izin di aplikasi asal, atau tabel global) tanpa me-login-kan user. Sebelum
   v3.2.3 user dialihkan tanpa pesan ke modul delegasi pertama. Allowlist bisa diubah admin hub lewat perintah di atas
   atau Setting → Dashboard Settings grup `master_launch` (JSON daftar tabel; nama tabel tidak dicek di form itu).
4. Hub me-login-kan user dan menyimpan session `master_delegasi`, lalu membuka halaman edit.
5. Daftar master di hub dibatasi ke data bersama + milik aplikasi/company asal. Banner
   `@include('master::partials.delegation-banner')` menampilkan scope dan tombol Kembali.

**Keamanan:**
- Kunci privat hanya di klien. Kunci publik aman disimpan di `dashboard_settings` DB master, walau DB itu dibaca semua
  aplikasi.
- `SSO_KEY` bersama tidak dipakai.
- Tabel global tidak pernah didelegasikan.
- Aplikasi hub sebaiknya membatasi sesi delegasi ke route master saja, karena user delegasi belum tentu punya akses
  hub.

## Filter daftar/dropdown di klien (setting)

Format lama tetap berlaku: `setting` dengan `category`=tabel, `name`=kolom, `value`=CSV, digabung OR antar baris.
Format baris scope:

| Sumber | `name` / `key` | `value` |
|---|---|---|
| `setting` | `scope.<label>` | JSON kolom → nilai (AND di dalam baris) |
| `dashboard_settings` | `<tabel>.scope.<label>` | sama |

Contoh:

```
scope.bersama : {"app_code": ["{GLOBAL_APP_CODE}"]}
scope.app     : {"app_code": ["{APP_CODE}"], "company_id": ["{USER_COMPANY}", 0]}
```

- **Placeholder:** `{APP_CODE}`, `{USER_COMPANY}`, `{GLOBAL_APP_CODE}`.
- **Company user dicari berurutan:**
  1. `USER_COMPANY_RESOLVER`.
  2. `resolve_user_company_id($user)`.
  3. `user_details` / `master_user_details` (`company_id`).
  4. `MASTER_COMPANY_ID`.

## Perubahan perilaku (breaking) v3.0

Versi major supaya aplikasi dengan constraint `^2.x` tidak ikut naik tanpa sengaja saat `composer update`. Naikkan
constraint ke `^3.0` per aplikasi setelah siap.

- **Simpan/hapus master di klien dengan `MASTER_DIRECT_EDIT=true`** kini wajib permission modul (dulu tanpa cek di
  paket), dan hanya untuk baris milik aplikasi + company user.
- **Tombol "Edit di Master Data"** hanya tampil pada baris milik aplikasi + company user.
- **Data baru dari hub tanpa pilihan pemilik** = APP03 + company 0. Dulu item code diberi `MASTER_COMPANY_ID`.
- **Route baru `POST master-launch`** (publik, token di body) ada di semua aplikasi. Selain di hub, route ini 404.
  Aplikasi dengan test daftar route perlu menggolongkannya.

## Perubahan v3.1

- **`bin/master-scope-setup`** dan **`master:scope-doctor`** (lihat di bawah); `master:launch-key --format=env` untuk skrip.
- **`master:permissions`** mengganti nama alias di tempat (id tetap) dan, bila nama baru sudah ada, mempertahankan id
  yang lebih kecil. Dulu nama baru selalu dibuat dengan id baru lalu alias dihapus.
- **Import Excel** memakai permission seragam (`create_item_group`, alias `create_itemgroup` tetap diterima).
- **`MasterEditLink::permission()`** mengembalikan nama seragam (`update_item_code`, `create_item_group`).
  `allowed()` tetap menerima nama lama.
- **`EXPORT_TABLES` default** `master_item_group` menerima `read_item_group` dan `read_itemgroup`.
- **Route `api/master-sync/*`** diberi nama per route (`api.master.sync.sync`, `.items`, `.catch-up`, `.allowed-tables`);
  dulu keempatnya bernama `api.master.sync.`.

## Perubahan v3.4

- **Memindahkan pemilik data dijaga** (lihat Aturan izin no. 5). Dulu `is_admin` bisa memindahkan pemilik, misalnya
  APP03 → APP23, tanpa konfirmasi dan tanpa cek apakah aplikasi/company tujuan terdaftar.
- **Klien yang mengirim `owner_app_code`/`owner_company_id` sendiri** (bukan lewat form paket) wajib menambahkan
  `confirm_owner_change=1` saat memindahkan pemilik, dan memakai aplikasi/company yang terdaftar.
- **Dialog konfirmasi form master** dipindah ke komponen bersama `master::components.confirm-dialog`
  (`window.masterConfirmDialog`), dipakai penjaga kunci unik dan pemindahan pemilik. Kelas CSS `ukg-*` diganti `mcd-*`.

## Urutan rollout

1. **Hub (Master Data):** deploy v3, lalu jalankan skrip setup (atau `master:permissions` + `master:scope-upgrade`).
   Hub menerima nama permission lama dan baru. Saklar `MASTER_UNIQUE_PER_OWNER` tetap kosong.
2. **Tiap aplikasi penyalin:** jalankan skrip setup (di bawah). Skrip menaikkan paket, menaikkan tabel lokal,
   menyeragamkan permission, memasang kunci launch, dan mendaftarkannya di hub.
3. **Hub:** setelah semua penyalin tabel X selesai, tambahkan X ke `MASTER_UNIQUE_PER_OWNER`.

## Skrip setup sekali jalan (`bin/master-scope-setup`, sejak v3.1)

Dijalankan dari folder project **di host** (bukan console container), seperti `deploy-prod.sh`. Skrip mendeteksi sendiri
container php-fpm yang me-mount project (bisa ditimpa `PHP_CONTAINER` atau `.deploy.env`), versi paket, peran aplikasi
(hub bila `MASTER_HUB=true`, selain itu klien), app_code, dan apa saja yang belum beres (lewat `master:scope-doctor`).
Tiap langkah yang mengubah composer, DB, atau `.env` ditanya dulu. Langkah yang sudah sesuai dilewati, jadi aman diulang.

```bash
# aplikasi yang sudah memakai v3.1+
vendor/bin/master-scope-setup --dry-run        # lihat rencana, tidak mengubah apa pun
vendor/bin/master-scope-setup                  # jalankan, konfirmasi per langkah

# aplikasi yang masih v2 (skrip belum ada di vendor): jalankan dari salinan paket
curl -fsSL -o /tmp/master-scope-setup https://raw.githubusercontent.com/BangsaMU/master/v3.1.0/bin/master-scope-setup
bash /tmp/master-scope-setup --app=$HOME/www/meb_spb --dry-run
bash /tmp/master-scope-setup --app=$HOME/www/meb_spb
```

| Opsi | Arti |
|---|---|
| `--dry-run` | Tampilkan rencana; deteksi dan pemeriksaan tetap jalan |
| `--yes` | Tanpa konfirmasi (untuk dijalankan dari skrip lain) |
| `--app=DIR` | Folder project (default folder saat ini) |
| `--hub=DIR` | (klien) Folder aplikasi Master Data di mesin yang sama; kunci publik langsung didaftarkan di sana |
| `--modules=LIST` | (klien) Tabel yang boleh didelegasikan saat didaftarkan, mis. `master_item_code,master_uom` |
| `--edit-url=URL` | (klien) Isi `MASTER_EDIT_URL` di `.env` bila masih kosong |
| `--with-testing` | Naikkan juga DB testing (`.env.testing`) |
| `--keep-alias` | Pertahankan nama permission lama selama checkout/kode lain di DB yang sama masih memakainya |
| `--skip-composer` | Jangan menaikkan paket (server produksi: versi ikut `composer.lock` lewat deploy) |

Urutan yang dijalankan:

1. **Deteksi:** container, path project di container, versi PHP.
2. **Paket:** bila `bangsamu/master` < 3.1, `composer require bangsamu/master:^3.1 --update-with-dependencies`.
3. **`master:scope-upgrade`:** tampilkan rencana (dry-run), konfirmasi, jalankan. Dengan `--with-testing` juga di DB testing.
4. **`master:permissions`:** tampilkan rencana, konfirmasi, jalankan (`--keep-alias` bila diminta).
5. **Kunci launch (klien):**
   - Bila `MASTER_LAUNCH_PRIVATE_KEY` kosong: buat pasangan kunci, tulis kunci privat ke `.env`. Nilai `.env` yang
     sudah terisi tidak pernah ditimpa, dan kunci privat tidak pernah ditampilkan.
   - Dengan `--hub`: daftarkan kunci publik di hub. Tanpa `--hub`: cetak perintah untuk dijalankan di server hub.
6. **`.env` klien:** cek `MASTER_EDIT_URL` (isi dari `--edit-url`) dan peringatkan bila `MASTER_DIRECT_EDIT=true`.
7. **`optimize:clear`**, lalu izin `storage` dan `bootstrap/cache` ke www-data (seperti deploy).
8. **`master:scope-doctor`:** ringkasan akhir dan sisa yang dikerjakan manual (role, baris filter setting).

### Contoh: SPB (APP32) di PC dev, Master Data di mesin yang sama

```bash
cd ~/www/meb_spb
bash /tmp/master-scope-setup --with-testing \
    --hub=$HOME/www/master-data \
    --modules=master_item_code,master_item_group,master_uom,master_pca,master_category \
    --edit-url=http://master-data.localhost
```

Contoh keluaran (dipotong):

```
==> Paket bangsamu/master
   terpasang : v2.4.10
   sekarang  : 3.1.0
   peran     : klien APP32, DB spb_v2_local @ mariadb_alpine
==> Pemilik data app_code + company_id (master:scope-upgrade)
   [dry-run] master_category: tambah company_id (default 0 = semua company); unik ... -> (category_code,app_code,company_id)
   ...
==> Permission per tabel (master:permissions)
   [dry-run] Permission baru: 12 (read_brand, ..., delete_status)
   [dry-run] Alias digabung: 6 (create_item -> create_item_code (gabung, id 15 dipertahankan), read_itemgroup -> read_item_group (ganti nama), ...)
==> Kunci launch Edit di Master Data
   MASTER_LAUNCH_PRIVATE_KEY ditulis ke .env.
   Kunci publik APP32 didaftarkan.
   Modul delegasi APP32: master_item_code, master_item_group, master_uom, master_pca, master_category
==> Hasil (php artisan master:scope-doctor)
   WARN  Filter setting perlu dicek:
  - master_item_code app_code (setting id 11) = APP09,APP32,APP03, master_item_code company_id (setting id 12) = 1 (format lama)
    belum membatasi company. Saran: ganti dengan baris scope.bersama = {"app_code":["APP09","APP03"]} dan
    scope.app = {"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]} (category master_item_code)
```

### Contoh: produksi (server klien dan hub berbeda)

```bash
# server SPB, setelah deploy-prod.sh membawa composer.lock v3.1
it@spb:~/www/meb_spb$ vendor/bin/master-scope-setup --skip-composer
#   ... di akhir langkah kunci launch tercetak:
#   php artisan master:launch-key --register=APP32 --public=<kunci publik>

# server Master Data: jalankan perintah tercetak tadi lewat container PHP
it@master:~/www/master-data$ docker exec -u $(id -u):$(id -g) -w /var/www/html/master-data <container-php> \
    php artisan master:launch-key --register=APP32 --public=<kunci publik> \
    --modules=master_item_code,master_item_group,master_uom,master_pca,master_category
```

### Perintah manual (tanpa skrip)

Semua lewat container PHP aplikasinya (`docker exec -u <uid>:<gid> -w /var/www/html/<project> <container> ...`).

```bash
composer require "bangsamu/master:^3.1" --update-with-dependencies   # klien yang masih v2
php artisan master:scope-doctor                    # cek kondisi (hanya membaca); --format=env untuk skrip
php artisan master:scope-upgrade --dry-run && php artisan master:scope-upgrade
php artisan master:permissions --dry-run && php artisan master:permissions
php artisan master:launch-key                      # klien: cetak MASTER_LAUNCH_PRIVATE_KEY + perintah daftar di hub
php artisan master:launch-key --show               # klien: kunci publik dari kunci privat yang terpasang
php artisan master:launch-key --register=APP32 --public=<kunci> [--modules=...]   # hub
php artisan master:launch-key --register=APP32     # hub: cek kunci + modul terdaftar
php artisan optimize:clear
```

### Sisa manual di aplikasi klien

1. **Role:** beri `{create|update|delete}_{tabel}` ke role yang boleh mengubah master lewat Master Data, mis. ADMIN SPB:
   `create_item_code`, `update_item_code`, `create_item_group`, `update_item_group`, `create_uom`, `update_uom`,
   `create_pca`, `update_pca`, `create_category`, `update_category`. Aplikasi yang mengelola permission lewat seeder
   memasukkannya ke seeder.
2. **Filter setting** (`setting`, category = nama tabel): ganti baris lama `app_code`/`company_id` dengan baris scope
   sesuai saran doctor. Antar baris digabung OR, jadi baris lama yang tertinggal membuka lagi data yang dibatasi.
   Contoh SPB:

   | category | name | value |
   |---|---|---|
   | `master_item_code` | `scope.bersama` | `{"app_code":["APP03","APP09"]}` |
   | `master_item_code` | `scope.spb` | `{"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}` |
   | `master_department` | `scope.spb` | `{"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}` |

3. **Test daftar route:** golongkan `POST master-launch` (publik, token di body; di klien 404), `GET master-launch/go/{slug}/{id?}`
   (izin dicek controller), dan `GET master-launch/back` (cukup login).

## Batasan yang diketahui

- **Import Excel** masih memberi `app_code` dari `SsoConfig.main.APP_CODE` tanpa company, sehingga hasilnya company 0
  setelah upgrade. Sesi delegasi di hub tidak boleh import: master-data mengunci route import ke permission lokal.
- **Theme `_meridian` / `_tabler`** belum menampilkan field "Pemilik Data" di form. Aturan simpan tetap berlaku.
- **`master_employee`, `master_status`, `master_increment`, `master_project_detail`** belum ber-scope (aturan khusus).
