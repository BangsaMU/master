<?php

namespace Bangsamu\Master\Traits;

use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Bangsamu\Master\Services\MasterScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Filter daftar/dropdown master dari setting aplikasi.
 *
 * Sumber: tabel `setting` (category = nama tabel), fallback `dashboard_settings` (group = nama tabel). Antar baris OR.
 *  - baris kolom (format lama): name/key = kolom, value = daftar dipisah koma -> kolom IN (...);
 *    di dashboard_settings key boleh `{tabel}.{kolom}` supaya tidak bentrok dengan key UNIQUE;
 *  - baris scope: name `scope` / `scope.<label>` (dashboard_settings key `{tabel}.scope.<label>`), value JSON
 *    {"kolom": [nilai, ...], ...} -> AND di dalam baris, mis. {"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}.
 * Placeholder nilai: {APP_CODE}, {USER_COMPANY}, {GLOBAL_APP_CODE} (MasterScope::placeholders()).
 *
 * Di aplikasi Master Data dengan session delegasi, tabel ber-scope dibatasi ke data bersama + milik aplikasi/company
 * asal delegasi.
 */
trait DynamicFilterable
{
    protected function getSettingsForTable(string $category): Collection
    {
        $settings = Setting::where('category', $category)->get();

        if ($settings->isEmpty()) {
            $settings = DashboardSettings::where('group', $category)->get();
        }

        return $settings;
    }

    protected function applyDynamicFilter($query, string $table, $settings, ?string $alias = null): void
    {
        $this->applyDelegationScope($query, $table, $alias);

        if ($settings instanceof Collection && $settings->isEmpty()) {
            return;
        }

        if (! is_iterable($settings)) {
            return;
        }

        $groups = [];
        foreach ($settings as $setting) {
            $group = $this->settingFilterGroup($table, $setting);
            if ($group !== []) {
                $groups[] = $group;
            }
        }

        if ($groups === []) {
            return;
        }

        $placeholders = app(MasterScope::class)->placeholders();

        $query->where(function ($q) use ($groups, $alias, $placeholders): void {
            foreach ($groups as $group) {
                $q->orWhere(function ($inner) use ($group, $alias, $placeholders): void {
                    foreach ($group as $column => $values) {
                        $values = array_values(array_unique(array_map(fn ($value) => strtr((string) $value, $placeholders), $values)));
                        $inner->whereIn($alias ? $alias.'.'.$column : $column, $values);
                    }
                });
            }
        });
    }

    /**
     * Satu baris setting -> [kolom => nilai] (AND); kosong bila tidak berlaku (kolom tidak ada, nilai kosong).
     *
     * @return array<string, list<string>>
     */
    protected function settingFilterGroup(string $table, object $setting): array
    {
        $name = (string) ($setting->name ?? $setting->key ?? '');
        if (str_starts_with($name, $table.'.')) {
            $name = substr($name, strlen($table) + 1);
        }

        if ($name === 'scope' || str_starts_with($name, 'scope.')) {
            $decoded = json_decode((string) ($setting->value ?? ''), true);
            if (! is_array($decoded) || $decoded === []) {
                return [];
            }
            $group = [];
            foreach ($decoded as $column => $values) {
                $values = array_values(array_filter(array_map(fn ($value) => trim((string) $value), (array) $values), fn ($value) => $value !== ''));
                if (! is_string($column) || $values === [] || ! Schema::hasColumn($table, $column)) {
                    return []; // baris scope dengan kolom tidak dikenal diabaikan utuh, bukan dilonggarkan
                }
                $group[$column] = $values;
            }

            return $group;
        }

        $values = array_values(array_filter(array_map('trim', explode(',', (string) ($setting->value ?? '')))));
        if ($name === '' || $values === [] || ! Schema::hasColumn($table, $name)) {
            return [];
        }

        return [$name => $values];
    }

    /** hub + session delegasi: tabel ber-scope hanya data bersama + milik aplikasi/company asal */
    protected function applyDelegationScope($query, string $table, ?string $alias = null): void
    {
        $scope = app(MasterScope::class);
        $delegation = $scope->delegation();
        if ($delegation === null || ! $scope->hasAppCode($table)) {
            return;
        }

        $column = fn (string $name) => $alias ? $alias.'.'.$name : $name;
        $companies = array_values(array_unique([(int) $delegation['company_id'], 0]));

        $query->where(function ($q) use ($scope, $table, $delegation, $column, $companies): void {
            $q->where($column('app_code'), $scope->globalAppCode())
                ->orWhere(function ($own) use ($scope, $table, $delegation, $column, $companies): void {
                    $own->where($column('app_code'), $delegation['app_code']);
                    if ($scope->hasCompany($table)) {
                        $own->whereIn($column('company_id'), $companies);
                    }
                });
        });
    }
}
