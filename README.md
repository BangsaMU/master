# Bangsamu Master Package

Paket terpusat Master Data untuk ekosistem aplikasi berbasis Laravel.

## Fitur Utama

- **Cakupan 15 Tabel Master Data**:
  1. `master_item_code` (`item_code`)
  2. `master_category` (`category_code`)
  3. `master_company` (`company_code`)
  4. `master_department` (`department_code`)
  5. `master_employee` (`no_ktp` sebagai kode identitas utama unik)
  6. `master_item_group` (`item_group_code`)
  7. `master_job_position` (`position_code`)
  8. `master_location` (`loc_code`)
  9. `master_pca` (`pca_code`)
  10. `master_project` (`project_code`)
  11. `master_project_detail` (`project_code_client`)
  12. `master_status` (`kode`)
  13. `master_uom` (`uom_code`)
  14. `master_vendor` (`vendor_code`)
  15. `master_vendor_contact` (`vendor_contact_name`)
- **Real-Time WebSocket Broadcasting (Senada Reverb Hub)**: Setiap perubahan data master (`created`, `updated`, `deleted`) otomatis di-broadcast secara real-time ke channel `masterdata.items` dengan single unified event `MasterDataUpdated` (tanpa duplikasi notifikasi).
- **FCM-Like Catch-Up Sync (Tahan Offline/App Mati)**:
  - Jika aplikasi klien atau browser pengguna sempat *offline* atau server mati saat broadcast dikirimkan, klien tidak akan kehilangan data.
  - Checkpoint ID broadcast terakhir tersimpan aman di tabel `dashboard_settings` (`key='last_synced_broadcast_id'`).
  - Paket secara otomatis melakukan validasi dan membuat tabel `dashboard_settings` jika belum ada di database lokal aplikasi.
  - Begitu koneksi WebSocket tersambung kembali, frontend secara otomatis memanggil `/master-sync/catch-up` untuk mengambil seluruh update yang terlewat.
  - Tersedia juga CLI command: `php artisan master:catch-up`.
- **Memory-Safe Data Cloning (`MasterDataSyncService`)**: Sinkronisasi data bertahap (chunking 250 rows) dengan MySQL `upsert` untuk menjaga tabel lokal tetap menjadi clone identik dari database master (`db_master`).
- **Whitelist Sinkronisasi & Penekanan Notifikasi (Notification Suppression)**:
  - **Filter Tabel Lokal & Prefix `master_*`**: Hanya tabel berawalan `master_*` yang benar-benar ada di database lokal aplikasi yang memenuhi syarat untuk disinkronkan. Tabel di luar database lokal atau tabel cadangan (`*OLD`, `*dump*`) otomatis diabaikan.
  - **Penyimpanan di `dashboard_settings`**:
    - **key**: `sync_allowed_master_tables`
    - **group**: `senada_sync`
    - **type**: `json`
    - **label**: `Allowed Master Data Sync Tables`
    - **value**: JSON array daftar tabel `master_*` yang diizinkan untuk disinkronkan (misal: `["master_category","master_company","master_department","master_employee","master_item_code", ...]`).
    - **options**: JSON array seluruh tabel `master_*` yang terdeteksi di database lokal.
    - *Auto-Discovery*: Jika konfigurasi belum ada, sistem secara otomatis memindai database lokal dan menginisialisasi tabel-tabel `master_*` yang valid ke `dashboard_settings`.
    - *Cache cek tabel (sejak v2.4.6)*: `getAllowedSyncTables()` dipanggil `<x-master::broadcast-listener />` di setiap halaman. Hasil cek keberadaan tabel (`information_schema`) di-cache `MasterDataSyncService::SCHEMA_CACHE_TTL` (1 jam) per database; isi `value` yang diubah langsung dihitung ulang, `master:install-tables` yang membuat tabel baru mengosongkan cache. Kosongkan manual: `MasterDataSyncService::flushSchemaCache()` atau `php artisan cache:clear`.
  - **Silent Ignore (Tanpa Popup Toast)**: Jika event broadcast diterima dari Senada untuk tabel yang tidak ada di database lokal klien atau tidak diaktifkan di `dashboard_settings`, klien mengabaikan event tersebut secara hening tanpa memunculkan popup toast notifikasi dan tanpa request fetch yang sia-sia karena tidak berdampak pada aplikasi klien.
  - **Respon Backend Aman**: Endpoint sinkronisasi mengembalikan `200 OK` dengan flag `skipped: true` (bukan error 422).
- **Built-in Endpoints**:
  - `POST /master-sync/sync` (Sinkronisasi universal semua master table)
  - `POST /master-sync/sync-items` (Alias kompatibel untuk item code)
  - `GET|POST /master-sync/catch-up` (Catch-up sync event yang terlewat)
  - `GET /master-sync/allowed-tables` (Daftar whitelist tabel yang diizinkan untuk disinkronkan)
  - `POST /master-sync/channel-auth` (Otorisasi channel privat browser)
  - *Wajib login (sejak v2.4.11)*: semua route di atas memakai `web` + `auth`, termasuk salinannya `api/master-sync/*`
    yang dulu terbuka (tamu bisa memicu sync / soft delete baris master lokal lewat `action=deleted`). Pemanggil
    tanpa session dapat 401 JSON / redirect login.

---

## Token Launch Viewer Annotation (opt-in)

Link viewer annotation tanpa token statis `md5(email)` dan tanpa cek cookie lintas-origin: route
`GET /annotation/open` membuat token terenkripsi yang terikat ke session user, dicek server-side oleh
`api/getcurrentuser`, `api/getSignature`, dan `api/getParaf`. Default `ANNOTATION_TOKEN_MODE=legacy`
(perilaku lama). Pakai helper `annotation_launch_url($fileUrl, $callback)` di app. Detail dan langkah
aktivasi: [docs/annotation-launch-token.md](docs/annotation-launch-token.md).

## Pemilik Data Multi Aplikasi + Multi Company (sejak v3.0)

- **Pemilik data:** baris master dimiliki (`app_code`, `company_id`). APP03 + company 0 = data bersama; APPxx + 0 =
  semua company aplikasi itu; APPxx + N = khusus company N.
- **Kode unik berpasangan dengan pemiliknya** (aktif per tabel lewat saklar transisi `MASTER_UNIQUE_PER_OWNER`), dan
  permission seragam per tabel (`update_item_code`, nama lama
  `update_item` tetap diterima).
- **Edit di Master Data tanpa login ulang:** lewat launch token Ed25519 + delegasi izin dari aplikasi asal.
- **Memindahkan pemilik baris (v3.4):** hanya `is_admin`, ke aplikasi/company terdaftar, dan wajib konfirmasi
  (dialog di form + `confirm_owner_change=1` dicek server).
- **Perintah:** `master:scope-upgrade`, `master:permissions`, `master:launch-key`, `master:scope-doctor` (cek, v3.1),
  `master:schema-columns` (kolom DB master yang belum ada di tabel sinkron lokal, v3.3).
- **Banner pengelola (v3.3):** `@include('master::partials.schema-notice')` di layout menampilkan tabel lokal yang
  tertinggal beserta perintah `docker exec`/artisan perbaikannya untuk `MASTER_SCHEMA_NOTICE_PERMISSION`.
- **Skrip setup sekali jalan (v3.1):** dari folder project di host, `vendor/bin/master-scope-setup [--dry-run]`
  mendeteksi container dan menjalankan semua langkah di atas (aplikasi yang masih v2: jalankan dari salinan paket).

Detail: [docs/scope-multi-company.md](docs/scope-multi-company.md).

## Filter Dropdown Master dari Setting (opt-in)

`MASTER_SELECT2_SETTING_FILTER=true` (config `MasterCrudConfig.SELECT2_SETTING_FILTER`, default `false`) membuat
`api/getmaster_*byparams` (sumber dropdown select2) memakai filter yang sama dengan list master: baris tabel
`setting` dengan `category` = nama tabel (fallback `dashboard_settings.group`), `name`/`key` = kolom, `value` = daftar
nilai dipisah koma; antar setting digabung OR. Lookup nilai terpilih (`?id=`) tidak difilter, jadi data lama tetap tampil.
List master selalu memakai filter ini (tanpa config). Sejak v2.4.9 list Location ikut membaca kolom apa pun (mis.
`loc_code`); sebelumnya hanya setting `group_type`.

## Edit di Aplikasi Master Data (sejak v2.4.7, opt-in)

Bila `MASTER_DIRECT_EDIT=false` (data master hanya dibaca, hasil sync hub) dan `MASTER_EDIT_URL` diisi (base URL
aplikasi Master Data, mis. `http://192.168.20.187:9002`), daftar master menampilkan tombol **Tambah di Master Data**
dan **Edit di Master Data** per baris yang membuka tab baru ke `{MASTER_EDIT_URL}/master/{slug}/create` dan
`/master/{slug}/{id}/edit` (path route paket sama, id baris = id DB master). Helper:
`Bangsamu\Master\Services\MasterEditLink`.

Sejak v2.4.8 izinnya per modul: tombol Tambah butuh `create_{akhiran}`, tombol Edit butuh `update_{akhiran}`, dengan
nama permission aplikasi Master Data (`meindo_master`): item code `item`, item group `itemgroup`, lainnya sama dengan
slug (`category`, `uom`, `pca`, `company`, ...). `is_admin` selalu boleh. Override akhiran per slug lewat
`MasterCrudConfig.MASTER_EDIT_PERMISSIONS`; `MASTER_EDIT_PERMISSION` (opsional) = satu permission untuk semua modul.
Tombol Delete lokal hanya tampil bila `MASTER_DIRECT_EDIT=true`. Tombol hanya menyembunyikan aksi; route tulis tetap
perlu dikunci di app (lihat pola `MasterDataPermission`).

## Keamanan API Generik (sejak v2.4.5)

Sebelumnya `api/get{tabel}byparams` dan `api/get*byparams` ada di grup `api` tanpa auth: tamu bisa membaca tabel apa
pun, termasuk `password` + `api_token` `master_user` (`set[field][]=password`), dan `POST api/sync-master` menimpa
tabel master lokal dari isi request tanpa token.

- **Select2 wajib login**: route `api/get*byparams` memakai `MasterCrudConfig.SELECT2_MIDDLEWARE` (default `web` + `auth`;
  tamu dapat 401 JSON / redirect login). Dropdown yang dipanggil lintas aplikasi (tanpa session) perlu diatur sendiri,
  mis. `['api']` = publik lagi, batas tabel/kolom di bawah tetap berlaku.
- **Kolom rahasia** (`TableAccessPolicy`): `password`, `remember_token`, `api_token` + `SELECT2_HIDDEN_COLUMNS` tidak
  pernah keluar, dan dipakai di parameter apa pun (`set`, `search`, `where`, `find_in_set`, `not_null`, `order`,
  `group`, `join`) langsung 403. `set[field]=*` = semua kolom kecuali kolom rahasia.
- **Tabel**: `sessions`, `*token*`, `password_reset*`, `jobs`, `failed_jobs`, `cache*`, `migrations`, `telescope_*`, dll.
  selalu ditolak (+ `SELECT2_DENY_TABLES`). `SELECT2_TABLES` (pola `Str::is`, mis. `['master_*', 'terms']`) membatasi
  tabel select2 dan tabel `join[...]`; kosong = semua tabel lain boleh. Disarankan diisi di app.
- Pemisah `set[text][<pemisah>]` sekarang dikutip PDO (dulu ditempel mentah ke SQL).
- `api/get-{tabel}/{id}` dan `api/getMaster-{tabel}/{id}`: token `md5("{id}:{SsoConfig.main.KEY}")` dicek `hash_equals`,
  `KEY` kosong selalu ditolak, kolom rahasia dibuang, tabel daftar tolak tidak bisa dibaca.
- `POST api/sync-master/{tabel}/{id}`: rows dari request hanya dipakai bila header `X-Master-Sync-Token` = `MASTER_SYNC_TOKEN`
  (config `MasterConfig.SYNC_TOKEN`). Tanpa token valid, webhook hanya menarik ulang baris `{id}` dari `db_master`
  (seperti `api/master-{tabel}/{id}`); tabel wajib di `MASTER_TABEL`, id wajib angka. App tanpa koneksi `db_master`
  wajib memasang token di app dan di pengirim webhook.

## Export Tabel & CRUD Generik (sejak v2.4.10)

Sebelumnya `master/get{table}/export` (`master.table.export`) membaca tabel apa pun dari `db_master` semua kolom untuk
user login mana pun (termasuk `password` + `api_token` `master_user`), dan `crud/{table}` membaca/menulis tabel apa pun
(insert `model_has_roles` = jadi admin); constructor `CrudTabelController` bahkan menjalankan query information_schema
dengan nama tabel dari URL sebelum middleware `auth` (injeksi SQL tanpa login).

- **Export**: hanya tabel `MasterCrudConfig.EXPORT_TABLES` (nama tabel => daftar permission baca, salah satu cukup);
  tabel lain 404, user tanpa `is_admin` / permission itu 403. Default = tabel master yang punya halaman list dengan
  permission `read_{modul}`. App yang mengekspor tabel lain lewat route ini wajib menambahkannya, mis.
  `'mcu_matrix' => ['read_mcu']`; override di app mengganti seluruh daftar. Kolom rahasia `TableAccessPolicy` tidak ikut.
- **CRUD generik**: hanya tabel `MasterCrudConfig.CRUD_TABLES` dan hanya `is_admin`; default kosong = semua route `crud/*`
  404.

## Import: Referensi yang Belum Ada (sejak v3.2.0)

Import Item Code dulu selalu membuat UoM/PCA/Category/Item Group yang kodenya belum ada di master. Kini diatur saklar
`dashboard_settings` key `master_import.auto_create_refs` (group `master_import`, tipe `checkbox`, class
`Services\Import\MasterImportSettings`):

- **0 / tidak ada (default) = tolak**: baris itu berstatus **Error** di preview dan dilewati saat proses; nilai yang belum
  ada ditandai merah di tabel dan kolom Keterangan.
- **1 = izinkan**: alur lama, referensi dibuat otomatis saat proses; nilai ditandai kuning (*... akan dibuat otomatis*).

Baris saklar dibuat otomatis (nilai 0) saat preview import pertama, atau dari app dengan `MasterImportSettings::ensure()`,
supaya bisa diubah dari halaman Dashboard Settings app. Bila app punya route `settings.dashboard.index`, preview memberi
admin link ke group `master_import`. Definisi import lain bisa ikut memakai saklar ini dengan mengisi `missingRefs()` dan
`autoCreatableRefs()`.

Sejak v3.2.1 route lama `POST master/{modul}/import` (nama `master.{modul}.import`, 10 modul yang punya definisi preview)
juga masuk ke preview, bukan lagi `Imports\Master\*Import` yang langsung menyimpan (item code lama membuat referensi
otomatis tanpa saklar dan tanpa activity log). Form yang masih memakai route itu kini diarahkan ke halaman preview.
Employee tetap import langsung (belum ada definisi preview; kode posisi/lokasi yang salah ditolak).

## Data Terhapus: Pesan Kode Terpakai dan Restore (sejak v3.5.0)

Data master dihapus secara soft delete, dan kodenya tetap terpakai (rule unik menghitung baris terhapus, sama dengan
index unik DB). Dulu form hanya menampilkan *The project code has already been taken* padahal daftar kosong.

- **Pesan kode terpakai** (`Rules\MasterUnique`, dipakai `MasterScope::uniqueRule()`, form Company dan Project Detail):
  turunan `Unique` dengan query yang sama, tetapi pesannya menyebut baris pemakai kode. Baris aktif: nama + pemilik
  (*Project Code 20000 sudah dipakai: Heavy Equipment, pemilik APP03 · Semua company.*). Baris terhapus: nama, waktu dan
  user penghapus (dari `activity_logs` aksi `deleted`), lalu arahan Restore untuk admin atau "minta admin" untuk user lain.
- **Daftar master** (`Services\MasterTrash`, 13 modul): admin (`is_admin`) melihat baris terhapus berlatar merah muda
  (`DT_RowClass` `master-row-trashed`) dengan tanda **Terhapus**, tombol **View** dan **Restore**; user lain tetap hanya baris
  aktif. Controller modul cukup `->tap(fn ($query) => $trash->filter($query, 'slug', 'alias'))` pengganti
  `->whereNull('alias.deleted_at')`, dan `$trash->actions()` / `$trash->rowClass()` di kolom Action.
- **Restore** (`Controllers\MasterRestoreController`): `POST master/{slug}/{id}/restore`, nama `master.{slug}.restore`
  (`MasterRestoreController::MODULES`). Hanya admin; di aplikasi klien hanya baris milik aplikasinya (aturan hapus
  `MasterAccess`). Ditolak bila kode unik sudah dipakai baris aktif lain. Vendor ikut memulihkan Location vendor-nya.
  Tercatat di activity log aksi `restored` (atas nama `Models\{Studly}`, tampil di kartu riwayat form).
  `MasterModules::abilityForRouteAction('restore')` = `delete`, untuk middleware izin aksi tulis di app.
- Layout `layouts.dashboard.index` menyediakan `#restore-form` dan `notificationBeforeRestore()` (SweetAlert, fallback
  `confirm()`). App yang memakai layout daftar sendiri perlu menyalin keduanya.
- Butuh `bangsamu/library-clay` ^2.3 (`Loggable::shouldLogActivity()`, `ActivityLog::actorName()`, opt-in log console
  `libraryclay.activity_log.console`), wajib di `composer.json` sejak v3.8.2. Dengan v2.2 ke bawah aksi `restored` tidak
  tercatat (dilewati diam-diam) dan log dari console tidak pernah ditulis, sehingga test Restore/pindah pemilik merah.

## Cari per Kolom di Daftar Master (sejak v3.6.0, Item Code)

Daftar `master/item-code` punya baris input **Cari …** di bawah judul kolom (Item Code, Description, Unit (UoM), PCA,
Category, Item Group, Attributes). Request dikirim 400 ms sesudah berhenti mengetik (Enter = langsung, tombol × = hapus).

- **Layout** `layouts.dashboard.index` (opt-in): controller mengisi `$data['datatable']['column_search'] = true` di
  `index()`; input dibuat hanya untuk kolom dengan `'filter' => true` di metadata `columns` dari `dataJson()`. Memakai
  `orderCellsTop` (klik judul = urutkan, input tidak) dan ikut menyembunyikan sel cari saat Responsive melipat kolom.
  Modul lain belum dinyalakan; backend-nya masih memakai helper app `datatabelFilterQuery()`.
- **Item Code** (`ItemCodeController::SEARCH_COLUMNS`): whitelist alias kolom => ekspresi SQL untuk cari per kolom, cari
  global dan urutan; nama kolom dari request hanya dicocokkan ke daftar ini. Tiap kata (maks 5, dipisah spasi) wajib
  ada (`LIKE '%kata%' ESCAPE '!'`, jadi `%` dan `_` ketikan user = huruf biasa); antar kolom AND, cari global = tiap kata
  ada di salah satu kolom, termasuk nama UoM/PCA/Category/Item Group (dulu dilewati). Tidak lagi bergantung pada helper
  app `datatabelFilterQuery()`.
- **Performa**: `LIKE '%…%'` tidak bisa memakai index B-tree (`EXPLAIN` = `type ALL`), jadi setiap pencarian memindai
  `master_item_code`; ±6 ribu baris = beberapa milidetik. Join referensi memakai PRIMARY (`eq_ref`), sehingga `GROUP BY
  mic.id` dibuang (dulu memaksa temporary table + filesort) dan jumlah hasil dihitung `COUNT(*)` (dulu semua baris hasil
  diambil ke PHP setiap ganti halaman). Urutan dicocokkan lewat nama kolom (dulu indeks `$view_tabel` yang berbeda
  panjang, urut kolom Attributes = error SQL), dengan `mic.id` sebagai pengurut kedua supaya halaman stabil.

## Daftar Master: Nilai Sel Di-escape di Server (sejak v3.6.1)

Layout `layouts.dashboard.index` (DataTables 1.10) memasang data kolom dari `dataJson()` sebagai HTML, tanpa `render`
text. Dulu nilai dikirim mentah, jadi nama berisi `<img src=x onerror=...>` (lewat form, import Excel, atau sinkron dari
aplikasi lain) dieksekusi di browser setiap pembuka daftar (stored XSS).

- Semua `dataJson()` controller master (Item Code, UoM, Category, PCA, Item Group, Project, Project Detail, Company,
  Vendor, Location, Department, Employee, Priority, CRUD tabel generik) mengisi sel lewat
  `Bangsamu\Master\Support\DataTableCell::text()` = `e()` seperti `{{ }}` Blade; angka dan `null` dibiarkan.
- HTML yang sengaja dikirim hanya buatan server: kolom `action` (tombol) dan pemisah `<br>` kolom Attributes Item Code
  (`DataTableCell::keyValueLines()`: tiap key/value di-escape; value bersarang ditulis sebagai JSON, dulu daftar 500).
- Pemakai JSON yang memasang nilai sebagai teks (bukan HTML) akan melihat entitas (`&amp;`); layout paket tidak.
- Controller/daftar baru: jangan memasang `$row->kolom` langsung ke data DataTables. `MasterDataTableEscapeTest` menolak
  pola `$nestedData[$c_filed] = @$row->$c_filed;` di `src/Controllers`.

## Form Item Code: Dropdown "kode | nama" dan Tab di Select2 (sejak v3.6.2)

- Dropdown UoM, PCA, Category, dan Item Group (`master/item_code/form`, juga tema `_tabler`) tampil `KODE | Nama`
  (`set[text][ | ]={ref}_code&set[text][]={ref}_name`, pemisah dikirim `%20%7C%20` = CONCAT yang dikutip PDO di
  `getTabelByParams`). Kata dicari di kode ATAU nama (`search[{ref}_code][]` + `search[{ref}_name][]`, `initializeSelect2()`
  menerima array kolom). `ItemCodeController::edit()` mengisi `{ref}_text` dengan format yang sama untuk opsi tersimpan.
  Nilai `old()` sesudah validasi gagal kini ikut terpilih (dulu `Option(…, false, false)`: PCA/Category kosong lagi).
- Dropdown **Pemilik Data** (`partials/master-owner`) memakai pemisah yang sama: `APP03 | MASTER DATA (bersama)`,
  `MEB | Nama company`. Label pemilik gabungan (`MasterScope::ownerLabel()`, "APP03 · Semua company") tidak berubah.
- `master::partials.select2-keyboard` (dimuat sekali per halaman, aman di-include berulang atau dari layout aplikasi):
  Select2 4.0 bawaan hanya memfokuskan kotak saat Tab (kotak cari baru muncul sesudah klik/Enter/Spasi) dan Tab di kotak
  cari yang terbuka hanya menutup lalu fokus kembali ke kotak yang sama. Kini Tab/Shift+Tab ke select2 langsung membuka
  kotak cari; Tab di kotak cari menutup lalu pindah ke field berikut/sebelumnya, dan bila sudah mengetik kata, pilihan
  yang disorot ikut dipilih (seperti Enter). Tanpa kata, nilai tidak berubah. Klik, Esc, dan Enter tetap bawaan.
  Dipasang di form Item Code; form lain cukup `@include('master::partials.select2-keyboard')` di `@push('js')`.

## Route Perawatan `/optimize` dan `/composer` Wajib Admin (sejak v3.6.3, backport v2.4.12, v1.2.15, v1.1.102)

Sebelumnya kedua route hanya memakai middleware `web`. Cek login di dalam `/composer` hanya menutup halaman bantuan,
jadi **tamu** bisa menjalankan `?update=1`, `?install=1`, `?require=vendor/paket`, `?remove=vendor/paket`,
`?autoload=1`, dan `/optimize` (`?clear=1`). `composer require` paket buatan orang lain = kode orang itu berjalan di server.

- `/optimize` dan `/composer`: `web` + `auth` + `can:{ability}`. Ability dari `MASTER_MAINTENANCE_ABILITY`
  (config `MasterConfig.maintenance.ability`, default `is_admin`; Gate atau permission Spatie), mis. `is_root` di SPB.
  Tamu diarahkan ke login, user tanpa ability 403. Aplikasi yang belum punya permission itu: semua user 403 (aman).
- `mud-master` dan `master/mud-view` (halaman uji paket): wajib login.
- Skrip deploy tidak perlu route ini: `php artisan optimize:clear` dari CLI + reload php-fpm (opcache).

```dotenv
# ability untuk /optimize dan /composer (default is_admin)
MASTER_MAINTENANCE_ABILITY=is_admin
```

## Filter Lanjutan di Daftar Master (sejak v3.7.0, Item Code)

Tombol **Filter Lanjutan** di atas daftar `master/item-code` membuka modal pembuat kondisi: grup utama (tingkat 1) berisi
kondisi dan subgrup, subgrup bisa berisi subgrup lagi sampai **tingkat 3**. Tiap grup memilih **AND** (semua cocok, bawaan)
atau **OR** (cukup salah satu). Contoh: Description mengandung `Excavator` AND (Description mengandung `[Remark]` OR
Description mengandung `[Brand]`) AND Category sama dengan `EQUIPMENT`.

- **Operator**: mengandung, tidak mengandung, sama dengan, tidak sama dengan, diawali, diakhiri, kosong, tidak kosong.
  Nilai dicari utuh (frasa, termasuk spasi dan koma), tanpa beda huruf besar/kecil (`LIKE ... ESCAPE '!'`, juga untuk
  "sama dengan"; `%` dan `_` = huruf biasa). "Tidak ..." dan "kosong" ikut memasukkan item tanpa referensi (NULL).
  Kondisi tanpa nilai dilewati; filter yang semuanya kosong = tanpa filter.
- **Request**: parameter `advanced_filter` (JSON `{"logic":"and","rules":[{"field","op","value"} | {grup}]}`) ikut setiap
  request DataTables (`ajax.data` layout memanggil `window.masterAdvancedFilter.payload()`), digabung AND dengan cari per
  kolom dan cari global. Grup utama selalu dibungkus kurung, jadi OR tidak lepas dari filter data terhapus / scope.
- **Server** (`Bangsamu\Master\Support\AdvancedFilter`): field hanya dari whitelist controller (Item Code:
  `SEARCH_COLUMNS`), nilai lewat binding. Ditolak **422** `{"message": ...}`: lebih dari 3 tingkat, lebih dari 20 kondisi,
  nilai lebih dari 100 karakter, field/operator/logika tak dikenal, bukan JSON. Layout melepas filter dan memuat ulang
  daftar dengan pesan merah (dibatasi 20 kondisi karena filter dikirim lewat query string GET).
- **Saran nilai**: kolom UoM, PCA, Category, Item Group memakai `<datalist>` dari `GET master/item-code?advanced_filter_options=1`
  (AJAX, dimuat saat modal pertama dibuka): nama referensi yang dipakai item di daftar itu (filter data terhapus +
  setting aplikasi sama dengan daftar), maks 500 per kolom.
- **Tersimpan per tab browser** (`sessionStorage`, kunci `master-advanced-filter:{path}`): filter tetap terpasang sesudah
  membuka View/Update lalu kembali ke daftar; tab baru mulai tanpa filter. Badge jumlah kondisi + ringkasan kondisi
  tampil di atas tabel, tombol **Hapus Filter** melepasnya.
- **Stabilo hasil** (sejak v3.8.1): sesudah setiap draw DataTables, teks sel yang cocok dengan kondisi mengandung / sama
  dengan / diawali / diakhiri dibungkus `<mark class="maf-hl">` kuning transparan, nilai kondisi itu di ringkasan ikut
  distabilo. Kolom dicocokkan lewat `data` kolom DataTables = nama field filter; pencocokan tanpa beda huruf besar/kecil,
  rentang yang bertumpuk/bersambung digabung. Hanya simpul teks yang dipecah (tanpa `innerHTML`); kondisi "tidak ..." dan
  kosong tidak ditandai. Warna: `mark.maf-hl` di partial modal.
- **Layout** `layouts.dashboard.index` (opt-in): controller mengisi
  `$data['datatable']['advanced_filter'] = AdvancedFilter::viewConfig($labels, $suggest, $optionsUrl)` di `index()` lalu
  memanggil `AdvancedFilter::fromRequest($request->input('advanced_filter'), $whitelist)` dan `->apply($query)` di
  `dataJson()`. Partial `master::partials.advanced-filter.toolbar` dan `.modal` memakai kelas Bootstrap 4 + 5 (modal lewat
  `bootstrap.Modal` BS5, `$.fn.modal` BS4, atau cadangan tanpa Bootstrap). Belum untuk tema `_meridian`.
- **Belum**: Export Item Code masih mengekspor semua data (tidak ikut cari kolom maupun filter lanjutan).

## Template Company: Logo Header PDF per Aplikasi, Edit Hanya di Master Data (sejak v3.8.0)

Kartu **Template JSON** di form company (`master/company/{id}` dan `/edit`, tiga tema):

- **Dropdown App Code Key** berlabel `kode | nama` dari `master_apps` (mis. `APP32 | SPB V2`). Nama dibaca dari DB aplikasi
  dulu, lalu koneksi `db_master` (di aplikasi penyalin tabel `master_apps` lokal biasanya kosong); kode yang tidak
  terdaftar tampil kodenya saja. **Add Key** memberi konfirmasi bila kode tidak ada di `master_apps`.
- **Logo Header PDF per App Code Key**: Motto (`logo_2`) dan ISO, upload PNG/JPG maks. 2 MB + preview, tersimpan saat
  **Save Template**. File masuk disk `app.storage_disk_master` + baris `master_gallery` (pola sama dengan Company Logo), yang
  disimpan ke template hanya **URL http(s)**-nya: `{"APP32": {"form_no": {...}, "logo_pdf": {"logo_2": "https://...",
  "iso": "https://..."}}}`. URL dipakai (bukan id gallery) karena `master_gallery` tidak ikut sync ke aplikasi penyalin.
  Logo utama tetap **Company Logo** (`company_logo_id`). Saat simpan, isi `logo_pdf` selain `logo_2`/`iso` berisi URL
  http(s) dibuang (path lokal seperti `storage/../.env` atau `file://` tidak bisa dipakai membaca file server).
- **Edit hanya di aplikasi Master Data** (`MasterCrudConfig.MASTER_HUB=true`, `Support\CompanyTemplate::editableHere()`):
  aplikasi lain menampilkan kartu hanya baca (tanpa Save Template, Add Key/Field, upload) dengan link **Edit di Master
  Data** bila user berhak (`MasterEditLink`), dan `POST master/company/{id}/update-template-json` dibalas **403**. Dulu
  aplikasi dengan `MASTER_DIRECT_EDIT=true` boleh menyimpan key-nya sendiri lalu mendorong ke DB master. Halaman
  `/edit` company juga hanya baca bila `MASTER_DIRECT_EDIT=false` (dulu tombol Submit tampil lalu ditolak middleware app).
- **Membaca logo di aplikasi** (mis. header Req-Slip SPB): `CompanyTemplate::logoUrls($company->template_json, $appCode)`
  mengembalikan `['logo_2' => url, 'iso' => url]` milik aplikasi itu, hanya URL http(s); kosong = pakai default aplikasi.

## Upload Logo Company: File Dulu, Database Belakangan (sejak v3.8.3)

Berlaku untuk **Company Logo** (`POST master/company`) dan **logo header PDF** Template JSON
(`POST master/company/{id}/update-template-json`):

- **Hanya gambar PNG/JPG maks. 2 MB** (`CompanyController::LOGO_RULES` = `image|mimes:png,jpg,jpeg|max:2048`, jenis
  dibaca dari isi file). Ekstensi file tersimpan diambil dari isi file, bukan dari nama kiriman browser. Dulu Company Logo
  tidak divalidasi sama sekali: `shell.php` tersimpan sebagai `company/php/{id}-shell.php` di disk publik dan bisa
  dijalankan web server bila folder publiknya menunjuk ke disk itu. Input file diberi `accept=".png,.jpg,.jpeg"`.
- **Urutan**: validasi → simpan file ke disk `app.storage_disk_master` → satu transaksi DB (baris `master_gallery` lengkap
  dengan `filename`, `url`, `hash_file` sha256, lalu `company_logo_id` / `template_json`) → sinkron ke DB master setelah
  commit. Nama file `{nama-asli-slug}-{acak 12}.{png|jpg}`, tidak lagi memakai id gallery (dulu baris gallery dibuat dulu
  demi id itu).
- **Disk gagal** (folder tidak bisa dibuat/ditulis, `putFileAs` false): pesan di field `company_logo` /
  `logo_pdf_file.{logo_2|iso}`, bukan error 500, dan **tidak ada yang ditulis ke database** (data company lain juga belum
  disimpan). Dulu tertinggal baris `master_gallery` dengan `filename`/`url` kosong. Bila transaksi DB atau file berikutnya
  gagal, file yang telanjur tersimpan dihapus lagi.
- **Logo lama** dilepas setelah logo baru pasti tersimpan: barisnya di-soft delete, filenya dibiarkan supaya baris itu
  masih bisa dipulihkan. Dulu file + baris lama dihapus sebelum upload baru dicoba, sehingga upload gagal membuat company
  kehilangan logo.
- Aplikasi `MASTER_DIRECT_EDIT` dengan DB master terpisah tidak lagi membuat baris gallery kosong tambahan di DB master;
  baris gallery dikirim lewat `updateMaster` setelah commit seperti baris company.
- Belum diubah: `CompanyController::uploadFile()` (tanpa route) masih memakai pola lama.

## Konfigurasi Database (`config/database.php`)

Pastikan koneksi `db_master` telah didaftarkan di aplikasi client:

```php
'db_master' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL_MASTER'),
    'host' => env('DB_HOST_MASTER', '192.168.20.187'),
    'port' => env('DB_PORT_MASTER', '3306'),
    'database' => env('DB_DATABASE_MASTER', 'master'),
    'username' => env('DB_USERNAME_MASTER', 'user'),
    'password' => env('DB_PASSWORD_MASTER', 'pass'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
],
```

---

## Konfigurasi Lingkungan (`.env`)

```env
# ==============================================================================
# SENADA REVERB WEBSOCKET & MASTER SYNC (SISI CLIENT)
# ==============================================================================
# Endpoint Senada Broadcast Hub
SENADA_URL=http://192.168.20.187:9029
SENADA_API_KEY=snd_masterdata_key_secret
SENADA_BROADCAST_ACTIVE=true

# Kredensial Reverb WebSocket (Wajib untuk otorisasi channel private)
REVERB_APP_ID=senada_app
REVERB_APP_KEY=senada_hub_key
REVERB_APP_SECRET=senada_hub_secret
REVERB_HOST="192.168.20.187"
REVERB_PORT=9029
REVERB_SCHEME=http

# ==============================================================================
# JADWAL OTOMATIS & DISTRIBUTED LOCK SINKRONISASI MASTER
# ==============================================================================
# Mengaktifkan/menonaktifkan auto-schedule catch-up di Laravel Scheduler (true/false)
MASTER_SYNC_SCHEDULE_ENABLED=true
# Frekuensi jadwal: hourly (default 1 jam), everyThirtyMinutes, everyTwoHours, daily, atau cron string "0 * * * *"
MASTER_SYNC_SCHEDULE_INTERVAL=hourly
# Maksimal jumlah event broadcast yang disinkronkan per siklus
MASTER_SYNC_SCHEDULE_LIMIT=50
# Durasi masa aktif Central Master Sync Lock (detik, default: 300 = 5 menit, maks 3600 = 1 jam)
MASTER_SYNC_LOCK_TTL=300
# Cooldown pencegah banjir sync saat user login / buka tab baru di browser (detik, default: 300 = 5 menit)
MASTER_SYNC_BROWSER_COOLDOWN=300
```

---

## Cara Penggunaan di Aplikasi Client

### 1. Memasang Listener Real-time di View
Cukup tambahkan komponen Blade ini di layout utama aplikasi (seperti di `layouts/app.blade.php` atau `components/layouts/app.blade.php`):

```blade
<x-master::broadcast-listener />
```

Komponen ini akan secara otomatis:
1. Menginisiasi koneksi ke Senada WebSocket Reverb.
2. Saat koneksi terhubung (`connected`), otomatis memanggil *catch-up* ke Senada dengan proteksi **Local Cooldown & Multi-Tab Debounce** (`localStorage`), sehingga saat 50 user login bersamaan tidak terjadi banjir request ke database master.
3. Berlangganan ke channel `masterdata.items` dan channel privat pengguna (`private-user.{email}`).
4. Menampilkan toast notifikasi modern saat master data berubah atau saat catch-up selesai.
5. Melakukan sinkronisasi database lokal ke `db_master` via endpoint bawaan paket `/master-sync/sync`.
6. Merefresh DataTable aktif secara otomatis jika ada di halaman.
7. Me-reload halaman jika pengguna sedang membuka formulir/detail dari item yang bersangkutan.

---

### 2. Penjadwalan Otomatis & Central Distributed Lock

#### A. Penjadwalan Otomatis (Auto-Scheduler)
Paket secara otomatis mendaftarkan task `php artisan master:catch-up` ke Laravel Scheduler tanpa perlu mengedit file `app/Console/Kernel.php` secara manual.
Pastikan cron server aplikasi Anda sudah menjalankan scheduler standar Laravel:
```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```
Frekuensi eksekusi dapat diatur bebas via `.env`:
- `MASTER_SYNC_SCHEDULE_INTERVAL=hourly` (Default: setiap 1 jam)
- `MASTER_SYNC_SCHEDULE_INTERVAL=everyThirtyMinutes` (Setiap 30 menit)
- `MASTER_SYNC_SCHEDULE_INTERVAL=everyTwoHours` (Setiap 2 jam)
- `MASTER_SYNC_SCHEDULE_INTERVAL="0 */4 * * *"` (Custom cron: setiap 4 jam)

#### B. Central Distributed Lock (Perlindungan DB Master Lintas Aplikasi)
Untuk mencegah lonjakan koneksi dan perlambatan pada Database Master ketika banyak aplikasi (Clay, MCU, Warehouse, dll.) menjalankan sync secara bersamaan:
1. **Single-Sync Guarantee**: Sebelum melakukan sinkronisasi dari `db_master`, aplikasi meminta *Central Lock* ke Senada Hub. Hanya **1 aplikasi** yang diizinkan melakukan sinkronisasi dalam satu waktu.
2. **Auto-Postpone yang Ramah**: Jika aplikasi lain sedang melakukan sinkronisasi, proses sync ditunda secara elegan tanpa membebani koneksi DB Master.
3. **Anti-Stuck & Auto-Expire**: Lock memiliki TTL otomatis (default 5 menit / diset via `MASTER_SYNC_LOCK_TTL`). Jika aplikasi mati mendadak saat proses sync, lock otomatis kadaluarsa sehingga proses sync berikutnya tidak akan macet selamanya.
4. **Force Override**: Tersedia opsi `--force-lock` untuk memecah lock yang dicurigai macet.

---

### 3. Perintah Artisan Catch-Up & Lock Management

#### A. Catch-Up Sync
```bash
# Sinkronkan semua broadcast yang terlewat (dilindungi Central Lock)
php artisan master:catch-up

# Cek ID checkpoint sinkronisasi saat ini
php artisan master:catch-up --info

# Paksa catch-up dan override lock jika lock terindikasi macet
php artisan master:catch-up --force-lock

# Jalankan dengan parameter checkpoint & limit tertentu
php artisan master:catch-up --since=80 --limit=50
```

#### B. Inspeksi & Rilis Central Lock (`master:sync-lock`)
```bash
# Cek apakah saat ini ada aplikasi yang sedang mengunci DB Master
php artisan master:sync-lock --status

# Paksa rilis lock yang macet di Senada Hub
php artisan master:sync-lock --release

# Manual lock untuk kebutuhan maintenance khusus
php artisan master:sync-lock --acquire --ttl=600
```


### 3. Memanggil Broadcast Secara Manual dari Kode
Setiap Model di bawah namespace `Bangsamu\Master\Models\*` sudah terpasang trait `BroadcastsMasterChanges` secara otomatis. Namun jika ingin memanggil secara manual:

```php
use Bangsamu\Master\Services\MasterBroadcastService;

// Broadcast perubahan entitas master apapun
$broadcastService = app(MasterBroadcastService::class);
$broadcastService->broadcastTableChange('master_category', $categoryModel, 'updated');
```

---

### 4. Memanggil Sinkronisasi Manual dari Kode
```php
use Bangsamu\Master\Services\MasterDataSyncService;

$syncService = app(MasterDataSyncService::class);

// 1. Sinkronkan tabel tertentu berdasarkan ID
$result = $syncService->syncTable('master_employee', 12, 450, 'updated');

// 2. Jalankan Catch-Up event terlewat
$catchUpResult = $syncService->catchUpMissedBroadcasts();
```

---

### 5. Optimasi Bulk Import (10.000+ Data) & Single Summary Broadcast

Untuk mencegah **Broadcast Storm** (di mana pengunggahan 10.000 data memicu 10.000 panggilan HTTP ke Senada, menyebabkan HTTP 429 Rate Limit dan browser freeze):

1. **Suppression Selama Proses Import**:
   Semua per-row broadcast dinonaktifkan sementara via `MasterBroadcastService::withoutBroadcasting(...)` dan event hook `BeforeImport`.
2. **1 Kali Ringkasan Broadcast di Akhir Batch**:
   Setelah seluruh sheet dan chunk selesai dibaca, dikirimkan **1 kali ringkasan event** `MasterDataUpdated` ke Senada:
   ```json
   {
       "table": "master_item_code",
       "action": "created",
       "id": 26500,
       "max_id": 26500,
       "batch_count": 10000,
       "is_batch": true,
       "identifier": "Batch Import (10.000 data)",
       "item_code": "Batch Import (10.000 data)"
   }
   ```
3. **Cloning Otomatis Sisi Klien**:
   Aplikasi klien yang menerima event ini mendeteksi bahwa `max_id` master (misal: 26.500) melompat melampaui `max_id` lokal (misal: 16.500). Klien otomatis mengeksekusi `syncRange()` dengan batch 250 record secara langsung dari `db_master` via MySQL upsert.
4. **Trait `HandlesBatchImportBroadcast`**:
   Semua class Import (Item Code, Category, Department, Employee, Item Group, Location, PCA, Project, UoM, Vendor) mengimplementasikan trait `HandlesBatchImportBroadcast` serta `WithChunkReading` (chunk 1.000) dan koleksi lookup in-memory (memoized) untuk menghindari N+1 kueri ke database master.

---

## 6. API Sinkronisasi Status User (Active / Inactive) Lokal

API ini disediakan untuk menerima webhook/cURL dari aplikasi master (`master-data` via `App\Services\UserStatusSyncService`) guna mengubah status user (`is_active`) menjadi aktif (`1`) atau inaktif (`0`) pada **database default lokal** (tabel `master_user`), **bukan** pada koneksi `db_master`.

### Endpoints
| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` / `POST` | `/api/user/status` | **Endpoint Utama** Sinkronisasi status user |
| `GET` / `POST` | `/api/user/set-status` | Endpoint alias |
| `GET` / `POST` | `/api/setUser.php` | Kompatibilitas URL legacy API |
| `GET` / `POST` | `/setUser.php` | Kompatibilitas URL legacy root |
| `GET` | `/api/users` | Daftar user lokal (Cursor Paginated). Wajib login (middleware `web` + `auth`) |
| `GET` | `/api/users/{id}` | Detail user lokal (id atau email). Wajib login (middleware `web` + `auth`) |

### Parameter Request
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `email_id` | `string` | **Ya** | Alamat email user yang akan disinkronkan. Menerima juga alias: `email`, `person_id`, `person_email`. |
| `status` | `int`/`string` | **Ya** | `1` / `active` / `true` untuk **Aktif**, `0` / `inactive` / `false` untuk **Inaktif**. Menerima juga alias: `active`. |
| `token` | `string` | **Ya** | Token keamanan HMAC SHA-256 (atau fallback MD5) yang dihasilkan dari `api_token($email_id)` menggunakan `SsoConfig.main.KEY`, atau static token. Dapat juga dikirim via header `Authorization: Bearer <token>`. |

### Contoh Request (cURL)

#### A. GET Request (Sesuai cURL `UserStatusSyncService` di `master-data`):
```bash
curl -X GET "http://clay-domain/api/user/status?email_id=user@example.com&status=1&token=45be95886cd458871369dba552ee3fd9c82bb578ff9863131ab4d2bbe40b52e7"
```

#### B. POST Request dengan JSON Body:
```bash
curl -X POST "http://clay-domain/api/user/status" \
     -H "Content-Type: application/json" \
     -d '{
       "email_id": "user@example.com",
       "status": 0,
       "token": "45be95886cd458871369dba552ee3fd9c82bb578ff9863131ab4d2bbe40b52e7"
     }'
```

### Format Response

#### Sukses (HTTP 200 OK)
```json
{
  "status": true,
  "code": 200,
  "message": "User 'user@example.com' status successfully set to active.",
  "data": {
    "id": 12,
    "name": "Bagas Alwi",
    "email": "user@example.com",
    "previous_status": 0,
    "current_status": 1,
    "status_label": "active"
  }
}
```

#### Token Tidak Valid (HTTP 401 Unauthorized)
```json
{
  "status": false,
  "code": 401,
  "message": "Unauthorized: Invalid or missing token."
}
```

#### User Tidak Ditemukan di DB Lokal (HTTP 404 Not Found)
```json
{
  "status": false,
  "code": 404,
  "message": "User not found for 'unknown@example.com'."
}
```

#### Validasi Gagal (HTTP 422 Unprocessable Entity)
```json
{
  "status": false,
  "code": 422,
  "message": "User identifier (email_id) is required.",
  "errors": {
    "email_id": [
      "User identifier (email_id) is required."
    ]
  }
}
```

### Logging & Tracing Aktivitas
Semua aktivitas request hit, penolakan token, validasi gagal, user not found, dan update status berhasil dicatat secara spesifik ke file log harian khusus:

- **Lokasi File:** `storage/logs/user-sync-YYYY-MM-DD.log` (dan terduplikasi di `storage/logs/laravel-YYYY-MM-DD.log`).
- **Live Trace Command:**
  ```bash
  tail -f storage/logs/user-sync-$(date +%Y-%m-%d).log
  ```


