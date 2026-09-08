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
- **Universal Blade Component (`<x-master::broadcast-listener />`)**: Komponen frontend siap pakai untuk menangkap event WebSocket dan melakukan sinkronisasi otomatis di latar belakang tanpa reload penuh.
- **Built-in Endpoints**:
  - `POST /master-sync/sync` (Sinkronisasi universal semua master table)
  - `POST /master-sync/sync-items` (Alias kompatibel untuk item code)
  - `GET|POST /master-sync/catch-up` (Catch-up sync event yang terlewat)
  - `POST /master-sync/channel-auth` (Otorisasi channel privat browser)

---

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
2. Saat koneksi terhubung (`connected`), otomatis memanggil *catch-up* ke Senada untuk menyinkronkan event master data yang terlewat selama aplikasi/browser offline.
3. Berlangganan ke channel `masterdata.items` dan channel privat pengguna (`private-user.{email}`).
4. Menampilkan toast notifikasi modern saat master data berubah atau saat catch-up selesai.
5. Melakukan sinkronisasi database lokal ke `db_master` via endpoint bawaan paket `/master-sync/sync`.
6. Merefresh DataTable aktif secara otomatis jika ada di halaman.
7. Me-reload halaman jika pengguna sedang membuka formulir/detail dari item yang bersangkutan.

---

### 2. Perintah Artisan Catch-Up (CLI / Scheduler)

Aplikasi klien dapat menjalankan sinkronisasi catch-up melalui Artisan:

```bash
# Sinkronkan semua broadcast yang terlewat sejak checkpoint terakhir
php artisan master:catch-up

# Cek ID checkpoint sinkronisasi saat ini
php artisan master:catch-up --info

# Paksa catch-up dari ID tertentu dengan batas maksimal event
php artisan master:catch-up --since=80 --limit=50
```

*Contoh Output:*
```
Fetching missed master data events from Senada (Current Checkpoint: #90)...
✓ Successfully synced 1 missed master data event(s). Checkpoint updated to #91.
+----------+-----------------+---------+--------+
| Event ID | Table           | Action  | Status |
+----------+-----------------+---------+--------+
| 91       | master_category | updated | OK     |
+----------+-----------------+---------+--------+
```

---

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

