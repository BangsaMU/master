<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Department;
use Bangsamu\Master\Services\Import\SimpleCodeImportDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Unik: department_code + app_code. app_code = aplikasi yang mengimport (config SsoConfig.main.APP_CODE),
 * jadi kode yang sama milik aplikasi lain adalah data terpisah, bukan duplikat.
 */
class DepartmentImportDefinition extends SimpleCodeImportDefinition
{
    /** Department dengan kode sama milik aplikasi lain: kode upper => list app_code. */
    protected Collection $otherApps;

    protected ?bool $hasAppCode = null;

    public function entity(): string
    {
        return 'department';
    }

    public function label(): string
    {
        return 'Department';
    }

    public function table(): string
    {
        return 'master_department';
    }

    public function permission(): string
    {
        return 'department';
    }

    public function keyLabel(): string
    {
        return 'Department Code + App Code';
    }

    protected function keyField(): string
    {
        return 'department_code';
    }

    protected function modelClass(): string
    {
        return Department::class;
    }

    /** Kode tidak lagi unik global, sinkron DB master by kode bisa mengenai department aplikasi lain. */
    public function masterSyncKey(): ?string
    {
        return null;
    }

    protected function columns(): array
    {
        return [
            'department_code' => ['label' => 'Department Code', 'aliases' => ['departmentcode', 'code'], 'max' => 15, 'required' => true],
            'department_name' => ['label' => 'Department Name', 'aliases' => ['departmentname', 'name'], 'max' => 100, 'required' => true],
        ];
    }

    public function prepare(array $rows): void
    {
        if (! $this->hasAppCodeColumn()) {
            $this->existing = collect();
            $this->otherApps = collect();

            return;
        }

        $appCode = $this->appCode();
        $all = $this->fetchByValues('master_department', 'department_code', array_column($rows, 'department_code'),
            ['id', 'department_code', 'department_name', 'app_code', 'deleted_at']);

        $this->existing = $all->filter(fn ($row) => (string) $row->app_code === $appCode)
            ->keyBy(fn ($row) => self::upper($row->department_code));

        $this->otherApps = $all->filter(fn ($row) => (string) $row->app_code !== $appCode && $row->deleted_at === null)
            ->groupBy(fn ($row) => self::upper($row->department_code))
            ->map(fn ($group) => $group->pluck('app_code')->unique()->values()->all());
    }

    public function check(array $data, ?array $existing): ?string
    {
        if (! $this->hasAppCodeColumn()) {
            return 'Kolom app_code belum ada di master_department. Jalankan `php artisan migrate` terlebih dahulu.';
        }

        return null;
    }

    public function notes(array $data): array
    {
        $apps = $this->otherApps->get($data['department_code']);

        return $apps
            ? ['Kode yang sama juga dipakai aplikasi '.implode(', ', $apps).' (data terpisah per aplikasi).']
            : [];
    }

    protected function hasAppCodeColumn(): bool
    {
        return $this->hasAppCode ??= Schema::hasColumn('master_department', 'app_code');
    }
}
