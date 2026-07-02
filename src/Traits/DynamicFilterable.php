<?php

namespace Bangsamu\Master\Traits;

use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        if ($settings instanceof Collection && $settings->isEmpty()) {
            return;
        }

        if (!is_iterable($settings)) {
            return;
        }

        $query->where(function ($q) use ($table, $settings, $alias): void {
            foreach ($settings as $setting) {
                $column = $setting->name ?? $setting->key;
                $values = array_values(array_filter(array_map('trim', explode(',', (string) ($setting->value ?? '')))));
                $columnRef = $alias ? $alias . '.' . $column : $column;

                if (!empty($column) && !empty($values) && Schema::hasColumn($table, $column)) {
                    $q->orWhereIn($columnRef, $values);
                }
            }
        });
    }
}
