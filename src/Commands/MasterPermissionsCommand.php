<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Support\MasterModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seragamkan permission master di aplikasi ini (tabel Spatie lokal): buat {aksi}_{tabel tanpa master_} yang belum ada,
 * lalu gabungkan alias lama (read_item -> read_item_code, ...) supaya role pemegang alias tetap punya izinnya.
 */
class MasterPermissionsCommand extends Command
{
    protected $signature = 'master:permissions
        {--dry-run : Hanya tampilkan rencana, tanpa mengubah database}
        {--keep-alias : Jangan hapus permission alias lama setelah digabung}';

    protected $description = 'Buat permission master per tabel yang belum ada dan gabungkan nama lama (item, itemgroup) ke nama baru';

    public function handle(): int
    {
        $tables = config('permission.table_names', []);
        $permissions = $tables['permissions'] ?? 'permissions';
        $roleHas = $tables['role_has_permissions'] ?? 'role_has_permissions';
        $modelHas = $tables['model_has_permissions'] ?? 'model_has_permissions';
        $pivot = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        if (! Schema::hasTable($permissions)) {
            $this->error("Tabel {$permissions} tidak ada (spatie/laravel-permission belum terpasang).");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $guard = config('auth.defaults.guard', 'web');
        $existing = DB::table($permissions)->where('guard_name', $guard)->pluck('id', 'name');

        $created = [];
        foreach (MasterModules::allPermissions() as $name) {
            if (! $existing->has($name)) {
                $created[] = $name;
                if (! $dry) {
                    $existing[$name] = DB::table($permissions)->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        $merged = [];
        foreach (MasterModules::legacyAliases() as $alias => $name) {
            if (! $existing->has($alias)) {
                continue;
            }
            $merged[] = "{$alias} -> {$name}";
            if ($dry) {
                continue;
            }
            $aliasId = $existing[$alias];
            $newId = $existing[$name];
            DB::transaction(function () use ($roleHas, $modelHas, $pivot, $aliasId, $newId, $permissions) {
                foreach (DB::table($roleHas)->where($pivot, $aliasId)->get() as $row) {
                    $values = (array) $row;
                    $values[$pivot] = $newId;
                    DB::table($roleHas)->insertOrIgnore($values);
                }
                foreach (DB::table($modelHas)->where($pivot, $aliasId)->get() as $row) {
                    $values = (array) $row;
                    $values[$pivot] = $newId;
                    DB::table($modelHas)->insertOrIgnore($values);
                }
                if (! $this->option('keep-alias')) {
                    DB::table($roleHas)->where($pivot, $aliasId)->delete();
                    DB::table($modelHas)->where($pivot, $aliasId)->delete();
                    DB::table($permissions)->where('id', $aliasId)->delete();
                }
            });
        }

        if (! $dry && class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $prefix = $dry ? '[dry-run] ' : '';
        $this->info($prefix.'Permission baru: '.count($created).($created ? ' ('.implode(', ', $created).')' : ''));
        $this->info($prefix.'Alias digabung: '.count($merged).($merged ? ' ('.implode(', ', $merged).')' : ''));

        return self::SUCCESS;
    }
}
