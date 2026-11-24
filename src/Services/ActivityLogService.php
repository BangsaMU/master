<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\LibraryClay\Models\ActivityLog;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Str;

/**
 * Riwayat activity_logs untuk satu record master (dipakai view logs_view.list).
 *
 * Satu tabel master punya beberapa model: form & simpan memakai Models\{Studly} (Project), hapus/restore/import
 * memakai Models\Master{Studly} (MasterProject). Loggable mencatat model_type sesuai kelas yang dipakai, jadi kartu Log
 * membaca semua nama model tabel itu (forSlug); dulu hanya satu kelas, sehingga log hapus tidak tampil di kartu Log.
 */
class ActivityLogService
{
    public const PER_PAGE = 10;

    /**
     * Semua model_type activity_logs untuk satu modul master: model paket (dengan dan tanpa awalan Master) dan model
     * aplikasi (App\Models) yang memakai tabel yang sama.
     *
     * @return list<class-string>
     */
    public static function modelTypes(string $slug): array
    {
        $studly = Str::studly($slug);

        return [
            'Bangsamu\\Master\\Models\\'.$studly,
            'Bangsamu\\Master\\Models\\Master'.$studly,
            'App\\Models\\'.$studly,
            'App\\Models\\Master'.$studly,
        ];
    }

    /** riwayat satu baris modul master (slug route: project, item-code, project-detail, ...) untuk kartu Log form */
    public function forSlug(string $slug, int|string $id, int $perPage = self::PER_PAGE): CursorPaginator
    {
        return $this->forModel(self::modelTypes($slug), $id, $perPage);
    }

    /**
     * @param  class-string|list<class-string>  $modelTypes
     */
    public function forModel(string|array $modelTypes, int|string $id, int $perPage = self::PER_PAGE): CursorPaginator
    {
        return ActivityLog::query()
            ->select('id', 'model_type', 'model_id', 'user_id', 'action', 'description', 'properties', 'updated_at')
            ->whereIn('model_type', (array) $modelTypes)
            ->where('model_id', $id)
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->decorate($log));
    }

    /**
     * Isi kolom bantu tampilan; sama dengan hasil query log employee:
     * "Bagas updated Category (via import: alasan)" -> nama_user "Bagas", sort_description "Category (via import: alasan)".
     * Nama model Master{Studly} ditampilkan sebagai nama modul: "Gita deleted MasterProject" -> "Project".
     */
    public function decorate(ActivityLog $log): ActivityLog
    {
        $description = (string) $log->description;
        $action = (string) $log->action;
        $position = $action !== '' ? strpos($description, $action) : false;
        $subject = $position === false ? $description : trim(substr($description, $position + strlen($action)));

        $log->setAttribute('nama_user', $position === false ? '' : trim(substr($description, 0, $position)));
        $log->setAttribute('sort_description', (string) preg_replace('/\bMaster(?=[A-Z][a-z])/', '', $subject));
        $log->setAttribute('updated_at_formated', $log->updated_at?->format('j F Y H:i:s'));
        $log->setAttribute('updated_at_formated_date', $log->updated_at?->format('j F Y'));
        $log->setAttribute('updated_at_formated_time', $log->updated_at?->format('H:i:s'));

        return $log;
    }
}
