<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Support\MasterModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seragamkan permission master di aplikasi ini (tabel Spatie lokal): alias lama (read_item -> read_item_code, ...)
 * diganti nama / digabung supaya role pemegang alias tetap punya izinnya, lalu {aksi}_{tabel tanpa master_} yang belum
 * ada dibuat. Aman diulang.
 */
class MasterPermissionsCommand extends Command
{
    protected $signature = 'master:permissions
        {--dry-run : Hanya tampilkan rencana, tanpa mengubah database}
        {--keep-alias : Pertahankan permission alias lama (nama baru dibuat dan diberi role yang sama), mis. selama kode lama masih memakainya}';

    protected $description = 'Buat permission master per tabel yang belum ada dan gabungkan nama lama (item, itemgroup) ke nama baru';

    /**
     * Rencana tanpa mengubah apa pun (dipakai master:scope-doctor): permission per tabel yang belum ada dan alias lama
     * yang masih ada.
     *
     * @return array{missing: list<string>, aliases: list<string>}
     */
    public function plan(): array
    {
        $permissions = config('permission.table_names.permissions') ?? 'permissions';
        if (! Schema::hasTable($permissions)) {
            return ['missing' => [], 'aliases' => []];
        }
        $existing = DB::table($permissions)->where('guard_name', config('auth.defaults.guard', 'web'))->pluck('name')->all();

        return [
            'missing' => array_values(array_diff(MasterModules::allPermissions(), $existing)),
            'aliases' => array_values(array_intersect(array_keys(MasterModules::legacyAliases()), $existing)),
        ];
    }

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
        $keepAlias = (bool) $this->option('keep-alias');
        $guard = config('auth.defaults.guard', 'web');
        $existing = DB::table($permissions)->where('guard_name', $guard)->pluck('id', 'name');

        // Alias dulu, sebelum nama baru dibuat: alias yang nama barunya belum ada cukup diganti nama (id dan semua role
        // tetap). Bila keduanya ada, baris ber-id lebih kecil (lebih lama, yang dirujuk seeder/snapshot) dipertahankan.
        $merged = [];
        foreach (MasterModules::legacyAliases() as $alias => $name) {
            if (! $existing->has($alias)) {
                continue;
            }
            $aliasId = $existing[$alias];
            $newId = $existing[$name] ?? null;

            if ($keepAlias) {
                $merged[] = "{$alias} -> {$name} (alias dipertahankan)";
                if ($dry) {
                    $existing[$name] ??= 0;
                } else {
                    $newId ??= DB::table($permissions)->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
                    $existing[$name] = $newId;
                    $this->copyLinks([$roleHas, $modelHas], $pivot, $aliasId, $newId);
                }

                continue;
            }

            if ($newId === null) {
                $merged[] = "{$alias} -> {$name} (ganti nama)";
                if (! $dry) {
                    DB::table($permissions)->where('id', $aliasId)->update(['name' => $name, 'updated_at' => now()]);
                }
            } else {
                [$keepId, $dropId] = $aliasId < $newId ? [$aliasId, $newId] : [$newId, $aliasId];
                $merged[] = "{$alias} -> {$name} (gabung, id {$keepId} dipertahankan)";
                if (! $dry) {
                    DB::transaction(function () use ($permissions, $roleHas, $modelHas, $pivot, $keepId, $dropId, $name) {
                        $this->copyLinks([$roleHas, $modelHas], $pivot, $dropId, $keepId);
                        DB::table($roleHas)->where($pivot, $dropId)->delete();
                        DB::table($modelHas)->where($pivot, $dropId)->delete();
                        DB::table($permissions)->where('id', $dropId)->delete();
                        DB::table($permissions)->where('id', $keepId)->update(['name' => $name, 'updated_at' => now()]);
                    });
                }
            }
            $existing->forget($alias);
            $existing[$name] = $newId === null ? $aliasId : min($aliasId, $newId);
        }

        $created = [];
        foreach (MasterModules::allPermissions() as $name) {
            if (! $existing->has($name)) {
                $created[] = $name;
                if (! $dry) {
                    $existing[$name] = DB::table($permissions)->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        if (! $dry && class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $prefix = $dry ? '[dry-run] ' : '';
        $this->info($prefix.'Permission baru: '.count($created).($created ? ' ('.implode(', ', $created).')' : ''));
        $this->info($prefix.'Alias digabung: '.count($merged).($merged ? ' ('.implode(', ', $merged).')' : ''));

        return self::SUCCESS;
    }

    /**
     * Salin role/user pemegang permission $fromId ke $toId (yang sudah punya dilewati).
     *
     * @param  list<string>  $pivotTables
     */
    protected function copyLinks(array $pivotTables, string $pivot, int $fromId, int $toId): void
    {
        foreach ($pivotTables as $table) {
            foreach (DB::table($table)->where($pivot, $fromId)->get() as $row) {
                $values = (array) $row;
                $values[$pivot] = $toId;
                DB::table($table)->insertOrIgnore($values);
            }
        }
    }
}
