# Pemilik data master (app_code + company_id), permission seragam, dan Edit di Master Data (v3.0)

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

## Permission seragam

- **Format:** `{read|create|update|delete}_{tabel tanpa master_}`, mis. `update_item_code`, `create_item_group`.
  Sumbernya `Support\MasterModules`.
- **Alias lama:** `*_item` dan `*_itemgroup` tetap diterima selama transisi.
- **`php artisan master:permissions [--dry-run]`:** membuat yang belum ada, lalu menggabungkan alias ke nama baru.
  Role pemegang alias diberi nama baru, lalu alias dihapus. Pakai `--keep-alias` bila alias perlu dipertahankan.

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

## Urutan rollout

1. **Hub (Master Data):**
   - Deploy v3. Hub menerima nama permission lama dan baru.
   - `master:permissions`.
   - `master:scope-upgrade`. Ini hanya menambah kolom dan melonggarkan index, data tidak berubah.
   - Saklar `MASTER_UNIQUE_PER_OWNER` tetap kosong.
2. **Tiap aplikasi penyalin:**
   - Ubah constraint ke `^3.0` dan jalankan `composer update`.
   - `master:scope-upgrade` di DB lokal.
   - `master:permissions`.
   - Untuk Edit di Master Data: `master:launch-key`, lalu daftarkan kunci publiknya di hub.
3. **Hub:** setelah semua penyalin tabel X selesai, tambahkan X ke `MASTER_UNIQUE_PER_OWNER`.

## Batasan yang diketahui

- **Import Excel** masih memberi `app_code` dari `SsoConfig.main.APP_CODE` tanpa company, sehingga hasilnya company 0
  setelah upgrade. Sesi delegasi di hub tidak boleh import: master-data mengunci route import ke permission lokal.
- **Theme `_meridian` / `_tabler`** belum menampilkan field "Pemilik Data" di form. Aturan simpan tetap berlaku.
- **`master_employee`, `master_status`, `master_increment`, `master_project_detail`** belum ber-scope (aturan khusus).
