# Bangsamu Master Package

Paket terpusat Master Data untuk ekosistem aplikasi Meindo berbasis Laravel.

## Fitur Utama

- **Master Data CRUD & Dynamic Models**: Project, Company, Employee, Item Code, UoM, Category, Location, dll.
- **Real-Time WebSocket Broadcasting (Senada Reverb Hub)**: Setiap perubahan data master (`created`, `updated`, `deleted`) otomatis di-broadcast secara real-time ke channel `masterdata.items`.
- **Automated Memory-Safe Data Cloning (`MasterItemSyncService`)**: Sinkronisasi data bertahap (chunking 250 rows) dengan MySQL `upsert` untuk menjaga tabel lokal tetap menjadi clone identik dari database master (`db_master`).
- **Universal Blade Component (`<x-master::broadcast-listener />`)**: Komponen frontend siap pakai untuk menangkap event WebSocket dan melakukan sinkronisasi otomatis di latar belakang tanpa reload penuh.
- **Built-in Endpoints**: Route bawaan `/master-sync/sync-items` dan `/master-sync/channel-auth`.

---

## Konfigurasi Database (`config/database.php`)

Pastikan koneksi `db_master` telah didaftarkan di aplikasi client:

```php
'db_master' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL_MASTER'),
    'host' => env('DB_HOST_MASTER', '192.168.20.187'),
    'port' => env('DB_PORT_MASTER', '3306'),
    'database' => env('DB_DATABASE_MASTER', 'meindo_master_live'),
    'username' => env('DB_USERNAME_MASTER', 'admin'),
    'password' => env('DB_PASSWORD_MASTER', 'Meindo12345'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
],
```

---

## Konfigurasi Lingkungan (`.env`)

```env
# URL & Kredensial Senada Reverb Hub (Gunakan IP Host 192.168.20.187 untuk Docker)
SENADA_URL=http://192.168.20.187:9029
SENADA_API_KEY=snd_masterdata_pilot_key_secret_2026
SENADA_BROADCAST_ACTIVE=true

# Reverb WebSocket Client Settings
REVERB_HOST="192.168.20.187"
REVERB_PORT=9029
REVERB_SCHEME=http
REVERB_APP_KEY=senada_hub_key
```

---

## Cara Penggunaan di Aplikasi Client

### 1. Memasang Listener Real-time di View
Cukup tambahkan komponen Blade ini di layout utama aplikasi (seperti di `layouts/app.blade.php`):

```blade
<x-master::broadcast-listener />
```

Komponen ini akan secara otomatis:
1. Berlangganan ke channel `masterdata.items` dan channel privat pengguna (`private-user.{email}`).
2. Menampilkan toast notifikasi saat master data berubah.
3. Melakukan sinkronisasi database lokal ke `db_master` via endpoint bawaan paket `/master-sync/sync-items`.
4. Merefresh DataTable aktif secara otomatis jika ada di halaman.
5. Me-reload halaman jika pengguna sedang membuka formulir/detail dari item yang bersangkutan.

### 2. Memanggil Broadcast Secara Manual dari Kode
```php
use Bangsamu\Master\Services\MasterBroadcastService;

// Broadcast perubahan item
MasterBroadcastService::broadcastItem($itemModel, 'updated');

// Broadcast perubahan entitas master lainnya
app(MasterBroadcastService::class)->broadcastChange('master_company', $companyModel, 'updated');
```

### 3. Memanggil Sinkronisasi Manual dari Kode
```php
use Bangsamu\Master\Services\MasterItemSyncService;

$syncService = app(MasterItemSyncService::class);

// Sinkronkan payload dari event broadcast
$result = $syncService->syncFromBroadcast($broadcastPayload);

// Atau sinkronkan hingga ID tertentu
$result = $syncService->syncToMaxId(10857);
```
