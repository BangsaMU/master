<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Support\MasterModules;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Data master yang sudah dihapus (soft delete):
 *  - daftar master menyembunyikannya, kecuali untuk admin (is_admin): baris tampil bertanda "Terhapus" dengan tombol
 *    Restore (route master.{slug}.restore, MasterRestoreController);
 *  - kodenya tetap terpakai (rule unik menghitung baris terhapus), jadi pesan "sudah dipakai" menjelaskan baris mana
 *    yang memakainya, kapan dan oleh siapa dihapus, serta cara memulihkannya (MasterUnique).
 */
class MasterTrash
{
    /** kolom bantu di query daftar master */
    public const COLUMN = 'deleted_at';

    /** class baris DataTables (DT_RowClass) untuk baris terhapus */
    public const ROW_CLASS = 'master-row-trashed';

    /** @var array<string, bool> slug => boleh melihat baris terhapus (dipakai berulang dalam satu request daftar) */
    protected array $seeTrashed = [];

    public function __construct(protected MasterAccess $access, protected MasterScope $scope) {}

    /** admin melihat baris terhapus di daftar master */
    public function canSeeTrashed(string $slug): bool
    {
        return $this->seeTrashed[$slug] ??= $this->access->isAdmin() && Schema::hasColumn(MasterModules::table($slug), self::COLUMN);
    }

    /** pulihkan = admin, dan di aplikasi klien baris harus milik aplikasinya (aturan hapus MasterAccess) */
    public function canRestore(string $slug, mixed $row): bool
    {
        return $this->access->isAdmin()
            && Route::has('master.'.$slug.'.restore')
            && $this->access->allows($slug, 'delete', $row);
    }

    /**
     * Saring query daftar master: non-admin hanya baris aktif; admin semua baris, dan query yang sudah ber-select
     * (bukan query hitung total) ikut mengambil deleted_at untuk tanda baris.
     */
    public function filter(Builder $query, string $slug, string $alias): Builder
    {
        $column = $alias.'.'.self::COLUMN;
        if (! $this->canSeeTrashed($slug)) {
            return $query->whereNull($column);
        }
        if ($query->columns !== null) {
            $query->addSelect(DB::raw($column.' AS '.self::COLUMN));
        }

        return $query;
    }

    public function isTrashed(object $row): bool
    {
        return filled($row->{self::COLUMN} ?? null);
    }

    public function rowClass(object $row): string
    {
        return $this->isTrashed($row) ? self::ROW_CLASS : '';
    }

    /** tombol kolom Action: baris aktif tetap $buttons, baris terhapus = tanda Terhapus + View + Restore */
    public function actions(string $slug, object $row, string $buttons): string
    {
        if (! $this->isTrashed($row)) {
            return $buttons;
        }

        $id = $row->No ?? $row->id;
        $deletedAt = $this->formatDate($row->{self::COLUMN});
        $html = '<span class="badge bg-danger badge-danger text-white" title="Dihapus '.e($deletedAt).'">Terhapus</span> ';
        if (Route::has('master.'.$slug.'.show')) {
            $html .= '<a href="'.e(route('master.'.$slug.'.show', $id)).'" class="btn btn-primary btn-sm">View</a> ';
        }
        if ($this->canRestore($slug, $row)) {
            $html .= '<a href="'.e(route('master.'.$slug.'.restore', $id)).'" onclick="notificationBeforeRestore(event,this)"'
                .' class="btn btn-success btn-sm">Restore</a>';
        }

        return $html;
    }

    /**
     * Pesan validasi kode yang sudah dipakai baris lain: baris aktif (nama + pemilik) atau baris terhapus (nama,
     * waktu & user penghapus, cara memulihkan).
     */
    public function conflictMessage(string $table, string $column, string $value, object $row): string
    {
        $slug = MasterModules::slugForTable($table);
        $label = $this->label($slug, $column).' '.$value;
        $name = $this->rowName($table, $column, $row);
        $module = $slug !== null ? Str::headline($slug) : Str::headline(Str::after($table, 'master_'));

        if (! $this->isTrashed($row)) {
            $detail = array_filter([$name, $this->scope->isScoped($table) ? 'pemilik '.$this->scope->ownerLabel($this->scope->ownerOf($row)) : null]);

            return $label.' sudah dipakai'.($detail !== [] ? ': '.implode(', ', $detail) : '').'.';
        }

        $deleted = 'dihapus '.$this->formatDate($row->{self::COLUMN});
        $by = $this->deletedBy($table, $row);
        if ($by !== null) {
            $deleted .= ' oleh '.$by;
        }
        $message = $label.' sudah ada tetapi datanya sudah dihapus ('.implode(', ', array_filter([$name, $deleted])).').'
            .' Data terhapus tidak tampil di daftar, tetapi kodenya tetap terpakai.';

        return $message.($slug !== null && $this->canRestore($slug, $row)
            ? ' Pulihkan lewat tombol Restore di daftar '.$module.' (baris bertanda Terhapus), atau pakai kode lain.'
            : ' Minta admin memulihkannya (Restore) dari daftar '.$module.', atau pakai kode lain.');
    }

    /** nama user yang menghapus dari activity_logs (Loggable), null bila tidak tercatat */
    public function deletedBy(string $table, object $row): ?string
    {
        $slug = MasterModules::slugForTable($table);
        if ($slug === null || empty($row->id)) {
            return null;
        }

        try {
            if (! Schema::hasTable('activity_logs')) {
                return null;
            }
            $studly = Str::studly($slug);
            $log = DB::table('activity_logs')
                ->whereIn('model_type', [
                    'Bangsamu\\Master\\Models\\Master'.$studly,
                    'Bangsamu\\Master\\Models\\'.$studly,
                    'App\\Models\\'.$studly,
                    'App\\Models\\Master'.$studly,
                ])
                ->where('model_id', $row->id)
                ->where('action', 'deleted')
                ->orderByDesc('id')
                ->first(['user_id', 'description']);
        } catch (Throwable) {
            return null;
        }
        if ($log === null) {
            return null;
        }

        // "Bagas deleted MasterProject" (ActivityLog::actorName()), sama dengan ActivityLogService::decorate()
        $position = strpos((string) $log->description, ' deleted ');
        $name = $position === false ? '' : trim(substr((string) $log->description, 0, $position));

        return $name !== '' ? $name : null;
    }

    protected function label(?string $slug, string $column): string
    {
        return MasterUniqueKeyGuard::KEYS[$slug][1][$column] ?? Str::headline($column);
    }

    /** kolom nama pasangan kolom kode: project_code -> project_name, vendor_code -> vendor_description */
    protected function rowName(string $table, string $column, object $row): ?string
    {
        foreach ([str_replace('_code', '_name', $column), str_replace('_code', '_description', $column), 'name'] as $field) {
            if ($field !== $column && filled($row->{$field} ?? null)) {
                return (string) $row->{$field};
            }
        }

        return null;
    }

    protected function formatDate(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('d-m-Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
