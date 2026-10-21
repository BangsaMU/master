<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aturan import (preview + commit) untuk satu master data.
 *
 * Alur: normalize() -> validate() -> key() -> prepare() -> existing()/check() -> create()/update().
 * Nilai di $data selalu string ter-normalisasi (string kosong = tidak diisi di file).
 */
abstract class MasterImportDefinition
{
    /** Slug routing, contoh: 'category', 'item-code'. */
    abstract public function entity(): string;

    /** Label untuk tampilan, contoh: 'Category'. */
    abstract public function label(): string;

    /** Tabel utama yang diimport (dipakai untuk broadcast ringkasan batch). */
    abstract public function table(): string;

    /** Nama route index untuk kembali setelah import. */
    public function indexRoute(): string
    {
        return 'master.'.$this->entity().'.index';
    }

    /** Route template download. */
    public function templateRoute(): string
    {
        return 'master.'.$this->entity().'.importtemplate';
    }

    /** Suffix permission, contoh: create_{suffix} / update_{suffix}. */
    abstract public function permission(): string;

    /** Label kunci unik untuk tampilan, contoh: 'Category Code'. */
    abstract public function keyLabel(): string;

    /**
     * Kolom ter-normalisasi => label, urutan tampilan di preview.
     *
     * @return array<string, string>
     */
    abstract public function fields(): array;

    /**
     * Kolom yang boleh di-update bila data sudah ada.
     *
     * @return list<string>
     */
    abstract public function updatableFields(): array;

    /**
     * Ubah satu baris mentah file menjadi data ter-normalisasi.
     *
     * @param  array<int|string, mixed>  $raw
     * @return array<string, string>
     */
    abstract public function normalize(array $raw): array;

    /** Validasi format/isi baris (tanpa DB). Kembalikan pesan error atau null. */
    abstract public function validate(array $data): ?string;

    /** Kunci unik baris (sudah upper/lower sesuai aturan tabel). */
    abstract public function key(array $data): string;

    /** Tampilan kunci unik untuk pesan, default sama dengan key(). */
    public function keyDisplay(array $data): string
    {
        return $this->key($data);
    }

    /**
     * Muat data existing/lookup sekali untuk seluruh baris (hindari N+1).
     *
     * @param  list<array<string, string>>  $rows
     */
    abstract public function prepare(array $rows): void;

    /**
     * Data existing untuk baris ini.
     *
     * @return array{id:int, trashed:bool, owner:?string, values:array<string, string>}|null
     */
    abstract public function existing(array $data): ?array;

    /** Validasi relasi/konflik terhadap DB. Kembalikan pesan error atau null. */
    public function check(array $data, ?array $existing): ?string
    {
        return null;
    }

    /**
     * Catatan informasi untuk preview (mis. data referensi akan dibuat otomatis).
     *
     * @return list<string>
     */
    public function notes(array $data): array
    {
        return [];
    }

    /** Kolom kepemilikan aplikasi; data milik aplikasi lain tidak boleh di-update. */
    public function ownershipColumn(): ?string
    {
        return null;
    }

    /** Kolom kunci untuk sinkron ke DB master (null = tidak disinkron oleh engine). */
    public function masterSyncKey(): ?string
    {
        return null;
    }

    /** Kolom kunci sinkron DB master untuk model yang disimpan (bisa model referensi). */
    public function masterSyncKeyFor(Model $model): ?string
    {
        return $model->getTable() === $this->table() ? $this->masterSyncKey() : null;
    }

    /** Import tanpa heading row (kolom posisi); baris pertama diberikan ke readHeader(). */
    public function usesHeadingRow(): bool
    {
        return true;
    }

    /** @param  array<int, mixed>  $header */
    public function readHeader(array $header): void {}

    /**
     * Data tambahan hasil baca file yang perlu disimpan bersama preview.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $meta */
    public function withMeta(array $meta): void {}

    /**
     * Simpan baris baru.
     *
     * @return list<array{model:Model, action:string, before:array, after:array}>
     */
    abstract public function create(array $data): array;

    /**
     * Update baris existing hanya pada kolom $changes.
     *
     * @param  array<string, array{old:string, new:string}>  $changes
     * @return list<array{model:Model, action:string, before:array, after:array}>
     */
    abstract public function update(int $id, array $changes, array $data): array;

    /**
     * Isi kolom file yang dianggap untuk perbandingan/update.
     * Kolom kosong di file tidak pernah mengosongkan data lama.
     */
    public function hasValue(array $data, string $field): bool
    {
        return isset($data[$field]) && $data[$field] !== '';
    }

    /** Nilai akhir kolom bila di-update (default: nilai file menggantikan nilai lama). */
    public function mergeValue(string $field, string $old, string $new): string
    {
        return $new;
    }

    // ---------------------------------------------------------------- helpers

    protected function str(array $raw, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $raw) && $raw[$key] !== null && trim((string) $raw[$key]) !== '') {
                return trim((string) $raw[$key]);
            }
        }

        return '';
    }

    protected static function upper(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    protected function appCode(): string
    {
        return (string) config('SsoConfig.main.APP_CODE', 'APP03');
    }

    /**
     * Ambil baris tabel (termasuk soft delete) berdasarkan daftar nilai kolom, per chunk.
     */
    protected function fetchByValues(string $table, string $column, array $values, array $select = ['*']): Collection
    {
        $values = array_values(array_unique(array_filter($values, fn ($v) => $v !== '' && $v !== null)));
        $result = collect();

        foreach (array_chunk($values, 1000) as $chunk) {
            $result = $result->merge(DB::table($table)->select($select)->whereIn($column, $chunk)->get());
        }

        return $result;
    }

    /**
     * Simpan model tanpa memicu event (Loggable/broadcast); log ditulis oleh engine import.
     *
     * @return array{model:Model, action:string, before:array, after:array}
     */
    protected function saveNew(Model $model): array
    {
        $model->saveQuietly();

        return ['model' => $model, 'action' => 'created', 'before' => [], 'after' => $model->attributesToArray()];
    }

    /**
     * @return array{model:Model, action:string, before:array, after:array}|null
     */
    protected function saveChanges(Model $model, array $attributes): ?array
    {
        $model->fill($attributes);
        if (! $model->isDirty()) {
            return null;
        }

        $dirty = $model->getDirty();
        $before = array_intersect_key($model->getOriginal(), $dirty);
        $model->saveQuietly();

        return ['model' => $model, 'action' => 'updated', 'before' => $before, 'after' => $model->getChanges()];
    }

    /** Sinkron ke DB master bila mode direct edit dan DB master berbeda. */
    public function syncToMaster(Model $model): void
    {
        $key = $this->masterSyncKeyFor($model);
        if ($key === null) {
            return;
        }

        try {
            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                LibraryClayController::updateMaster([
                    'sync_tabel' => $model->getTable(),
                    'sync_id' => $model->getAttribute($key),
                    'key_unik' => $key,
                    'sync_row' => $model->toArray(),
                    'sync_list_callback' => config('AppConfig.CALLBACK_URL'),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
