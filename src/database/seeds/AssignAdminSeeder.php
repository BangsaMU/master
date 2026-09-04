<?php

namespace Bangsamu\Master\Database\Seeds;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignAdminSeeder extends Seeder
{
    /**
     * untuk menjalakan dari terminal
     * php artisan db:seed --class="Bangsamu\Master\Database\Seeds\AssignAdminSeeder"
     */
    public function run(): void
    {
        // 1. Pastikan role admin tersedia terlebih dahulu
        $role = Role::firstOrCreate(['name' => 'admin']);

        // 2. Masukkan semua email yang ingin dijadikan admin ke dalam array ini
        $adminEmails = [
            'gita.samudra@meindo.com',
            'it_dept@meindo.com',
            // 'user_lain@meindo.com', <-- Anda bisa tambah email lain di sini nanti
        ];

        // 3. Lakukan perulangan untuk mengecek dan memasang role ke setiap user
        foreach ($adminEmails as $email) {
            $user = User::where('email', $email)->first();

            // if ($user) {
            //     // MENGGUNAKAN SYNC ROLES (Akan me-replace / menghapus role selain admin)
            //     $user->syncRoles([$role]); 
            //     $this->command->info("Berhasil! Role user {$email} telah di-replace menjadi admin.");
            // }

            if ($user) {
                // Cek apakah user sudah punya role admin atau belum
                if (!$user->hasRole('admin')) {
                    $user->assignRole($role);
                    $this->command->info("Berhasil! User {$email} sekarang menjadi admin.");
                } else {
                    $this->command->info("User {$email} sudah menjadi admin sebelumnya.");
                }
            } else {
                // Memberikan peringatan jika email tersebut ternyata belum registrasi di aplikasi
                $this->command->error("Gagal: User dengan email {$email} tidak ditemukan di database.");
            }
        }
    }
}
