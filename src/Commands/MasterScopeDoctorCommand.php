<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Bangsamu\Master\Services\MasterLaunchToken;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Support\MasterModules;
use Bangsamu\Master\Traits\DynamicFilterable;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periksa kesiapan aplikasi untuk pemilik data app_code + company_id (v3): tabel yang belum dinaikkan, permission
 * yang belum seragam, kunci launch Edit di Master Data, dan baris filter setting yang bertabrakan dengan baris scope.
 * Hanya membaca. `--format=env` dipakai skrip bin/master-scope-setup.
 */
class MasterScopeDoctorCommand extends Command
{
    use DynamicFilterable;

    protected $signature = 'master:scope-doctor
        {--format=table : table (dibaca orang) atau env (key=value untuk skrip)}';

    protected $description = 'Periksa kesiapan pemilik data master, permission, kunci launch, dan filter setting (hanya membaca)';

    public function handle(MasterScope $scope, MasterSchemaUpgrader $upgrader, MasterLaunchToken $tokens): int
    {
        $isHub = $scope->isHub();
        $upgrade = $this->laravel->make(MasterScopeUpgradeCommand::class);
        $pending = [];
        foreach ($upgrade->defaultTables() as $table) {
            if ($changes = $upgrade->plan($upgrader, $table)) {
                $pending[$table] = $changes;
            }
        }
        $global = array_values(array_filter(
            array_map(fn ($slug) => MasterModules::table($slug), array_keys(MasterModules::MODULES)),
            fn ($table) => ! in_array($table, MasterScopeUpgradeCommand::DENY, true) && Schema::hasTable($table) && ! Schema::hasColumn($table, 'app_code')
        ));
        $permissions = $this->laravel->make(MasterPermissionsCommand::class)->plan();
        $filters = $this->filterWarnings($scope);

        $report = [
            'package' => $this->packageVersion(),
            'role' => $isHub ? 'hub' : 'client',
            'app_code' => $scope->appCode(),
            'database' => DB::connection()->getDatabaseName(),
            'db_host' => (string) config('database.connections.'.DB::getDefaultConnection().'.host', ''),
            'scope_pending' => implode(',', array_keys($pending)),
            'scope_global' => implode(',', $global),
            'permissions_missing' => count($permissions['missing']),
            'permissions_aliases' => count($permissions['aliases']),
            'launch_key' => $isHub ? 'n/a' : ($tokens->canIssue() ? 'ok' : 'missing'),
            'edit_url' => (string) config('MasterCrudConfig.MASTER_EDIT_URL'),
            'direct_edit' => config('MasterCrudConfig.MASTER_DIRECT_EDIT') ? 1 : 0,
            'registered' => $isHub ? implode(',', $this->registeredApps()) : '',
            'unique_per_owner' => implode(',', (array) config('MasterCrudConfig.UNIQUE_PER_OWNER', [])),
            'filter_warnings' => count($filters),
        ];

        if ($this->option('format') === 'env') {
            foreach ($report as $key => $value) {
                $this->line($key.'='.str_replace(["\r", "\n"], ' ', (string) $value));
            }

            return self::SUCCESS;
        }

        $this->renderTable($report, $pending, $permissions, $filters, $tokens);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $pending
     * @param  array{missing: list<string>, aliases: list<string>}  $permissions
     * @param  list<string>  $filters
     */
    protected function renderTable(array $report, array $pending, array $permissions, array $filters, MasterLaunchToken $tokens): void
    {
        $detail = fn (string $label, string $value) => $this->components->twoColumnDetail($label, $value);

        $this->components->info('Pemilik data master (bangsamu/master '.$report['package'].')');
        $detail('Peran', $report['role'] === 'hub' ? 'hub (aplikasi Master Data)' : 'klien '.$report['app_code']);
        $detail('Tabel perlu dinaikkan', $pending === [] ? '<fg=green>semua sudah sesuai</>' : '<fg=yellow>'.implode(', ', array_keys($pending)).'</> (master:scope-upgrade)');
        foreach ($pending as $table => $changes) {
            $detail('  '.$table, implode('; ', $changes));
        }
        $detail('Tabel global (tanpa app_code)', $report['scope_global'] === '' ? '-' : $report['scope_global']);
        $detail('Permission per tabel', ($permissions['missing'] === [] && $permissions['aliases'] === [])
            ? '<fg=green>seragam</>'
            : '<fg=yellow>'.count($permissions['missing']).' belum ada, '.count($permissions['aliases']).' alias lama</> (master:permissions)');

        if ($report['role'] === 'hub') {
            $registered = array_filter(explode(',', $report['registered']));
            $detail('Aplikasi terdaftar (launch)', $registered === [] ? '<fg=yellow>belum ada</>' : '');
            foreach ($registered as $appCode) {
                $allowed = $tokens->allowedTables($appCode);
                $detail('  '.$appCode, 'modul: '.($allowed === null ? 'semua tabel ber-scope' : implode(', ', $allowed)));
            }
        } else {
            $detail('Kunci launch', $report['launch_key'] === 'ok' ? '<fg=green>terpasang</>' : '<fg=yellow>belum</> (master:launch-key)');
            $detail('MASTER_EDIT_URL', $report['edit_url'] !== '' ? $report['edit_url'] : '<fg=yellow>kosong, tombol Edit di Master Data tidak tampil</>');
            $detail('MASTER_DIRECT_EDIT', $report['direct_edit'] ? '<fg=yellow>true, master diubah langsung di aplikasi ini</>' : 'false');
            $detail('Company user', $this->companyResolverLabel());
        }
        $detail('Unik per pemilik', $report['unique_per_owner'] === '' ? 'kosong (kode unik global)' : $report['unique_per_owner']);

        if ($filters !== []) {
            $this->newLine();
            $this->components->warn('Filter setting perlu dicek:');
            foreach ($filters as $warning) {
                $this->line('  - '.$warning); // tanpa pembungkus baris supaya JSON saran bisa disalin utuh
            }
        }
    }

    /**
     * Baris filter setting tabel ber-scope yang diabaikan atau bertabrakan, beserta saran baris scope.
     *
     * @return list<string>
     */
    protected function filterWarnings(MasterScope $scope): array
    {
        if (! Schema::hasTable('setting') && ! Schema::hasTable('dashboard_settings')) {
            return [];
        }

        $warnings = [];
        foreach (array_keys(MasterModules::MODULES) as $slug) {
            $table = MasterModules::table($slug);
            if (! Schema::hasTable($table) || ! $scope->hasAppCode($table)) {
                continue;
            }

            $scopeRows = 0;
            $ownerRows = [];
            foreach ($this->settingsFor($table) as $setting) {
                $name = (string) ($setting->name ?? $setting->key ?? '');
                if (str_starts_with($name, $table.'.')) {
                    $name = substr($name, strlen($table) + 1);
                }
                $label = $table.' '.$name.$this->settingRef($setting);

                if ($name === 'scope' || str_starts_with($name, 'scope.')) {
                    if ($this->settingFilterGroup($table, $setting) === []) {
                        $warnings[] = "{$label} diabaikan: JSON tidak valid atau memuat kolom yang belum ada di tabel lokal (jalankan master:scope-upgrade)";
                    } else {
                        $scopeRows++;
                    }
                } elseif (in_array($name, ['app_code', 'company_id'], true)) {
                    $ownerRows[] = ['label' => $label, 'name' => $name, 'value' => (string) ($setting->value ?? '')];
                }
            }

            if ($ownerRows === []) {
                continue;
            }
            if ($scopeRows > 0) {
                foreach ($ownerRows as $row) {
                    $warnings[] = "{$row['label']} = {$row['value']} (format lama) digabung OR dengan baris scope, jadi data yang dibatasi scope ikut tampil lagi: hapus baris ini";
                }

                continue;
            }
            if ($scope->isHub() || ! $scope->hasCompany($table)) {
                continue;
            }

            $warnings[] = $this->scopeSuggestion($table, $ownerRows, $scope);
        }

        return $warnings;
    }

    /**
     * @param  list<array{label: string, name: string, value: string}>  $ownerRows
     */
    protected function scopeSuggestion(string $table, array $ownerRows, MasterScope $scope): string
    {
        $shared = [];
        foreach ($ownerRows as $row) {
            if ($row['name'] === 'app_code') {
                $shared = array_merge($shared, array_map('trim', explode(',', $row['value'])));
            }
        }
        $shared = array_values(array_unique(array_filter($shared, fn ($code) => $code !== '' && $code !== $scope->appCode() && $code !== '{APP_CODE}')));

        $rows = implode(', ', array_map(fn ($row) => $row['label'].' = '.$row['value'], $ownerRows));
        $suggest = 'scope.app = {"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}';
        if ($shared !== []) {
            $suggest = 'scope.bersama = '.json_encode(['app_code' => $shared]).' dan '.$suggest;
        }

        return "{$rows} (format lama) belum membatasi company. Saran: ganti dengan baris {$suggest} (category {$table})";
    }

    /** sama dengan getSettingsForTable(), tapi aman bila salah satu tabel setting tidak ada */
    protected function settingsFor(string $table): Collection
    {
        $settings = Schema::hasTable('setting') ? Setting::where('category', $table)->get() : collect();
        if ($settings->isEmpty() && Schema::hasTable('dashboard_settings')) {
            $settings = DashboardSettings::where('group', $table)->get();
        }

        return $settings;
    }

    protected function settingRef(object $setting): string
    {
        if (isset($setting->category)) {
            return ' (setting id '.$setting->id.')';
        }

        return isset($setting->key) ? ' (dashboard_settings '.$setting->key.')' : '';
    }

    /** @return list<string> */
    protected function registeredApps(): array
    {
        if (! Schema::hasTable('dashboard_settings')) {
            return [];
        }
        $prefix = MasterLaunchToken::SETTING_GROUP.'.public_key.';

        return DashboardSettings::query()->where('key', 'like', $prefix.'%')->whereNotNull('value')->where('value', '!=', '')
            ->orderBy('key')->pluck('key')->map(fn ($key) => substr((string) $key, strlen($prefix)))->values()->all();
    }

    protected function companyResolverLabel(): string
    {
        if ($resolver = config('MasterCrudConfig.USER_COMPANY_RESOLVER')) {
            return 'USER_COMPANY_RESOLVER '.$resolver;
        }
        if (function_exists('resolve_user_company_id')) {
            return 'resolve_user_company_id()';
        }

        return 'user_details / master_user_details, lalu MASTER_COMPANY_ID '.config('MasterCrudConfig.MASTER_COMPANY_ID');
    }

    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('bangsamu/master')) {
            return (string) InstalledVersions::getPrettyVersion('bangsamu/master');
        }

        return '?';
    }
}
