<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\SchemaColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Buka satu project di daftar Project (permintaan user 2026-11-24): klik Project Code di daftar Project Detail membuka
 * tab baru `master/project?project_id=<id>` yang hanya berisi project itu. Bila project-nya sudah dihapus, tautannya ikut
 * membawa `show_trashed=1` sehingga saklar "Tampilkan data terhapus" langsung menyala dan admin bisa menekan Restore di
 * kolom Action (kasus "Project bermasalah" di card ringkasan Project Detail).
 *
 * `project_id` cocok persis per id, termasuk project yang sudah dihapus; id tidak sah / tidak ada = tanpa filter.
 */
final class ProjectListFilter
{
    public const PARAM = 'project_id';

    public const TABLE = 'master_project';

    /**
     * Project terpilih dari request, atau null bila tidak ada / tidak sah.
     *
     * @return array{id: int, label: string, deleted: bool}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $id = filter_var($request->input(self::PARAM), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! SchemaColumns::hasTable(self::TABLE)) {
            return null;
        }

        $columns = ['id', 'project_code'];
        foreach (['project_name', 'deleted_at'] as $optional) {
            if (SchemaColumns::hasColumn(self::TABLE, $optional)) {
                $columns[] = $optional;
            }
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first($columns);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'label' => CompanyListFilter::label($row->project_code ?? null, $row->project_name ?? null),
            'deleted' => filled($row->deleted_at ?? null),
        ];
    }

    /** URL daftar Project yang hanya berisi project ini; project terhapus = saklar data terhapus menyala */
    public static function url(int $projectId, bool $deleted): string
    {
        $query = [self::PARAM => $projectId];
        if ($deleted) {
            $query[MasterTrash::REQUEST_PARAM] = 1;
        }

        return route('master.project.index', $query);
    }

    /**
     * $data['datatable']['fixed_filter'] daftar Project (bar "Hanya project ..." + parameter ajax), atau null.
     *
     * @param  bool  $canSeeTrashed  user boleh melihat project terhapus (admin); tidak = petunjuk minta admin
     * @return array{text: string, label: string, badge: string|null, note: string|null, clear_text: string, clear_url: string, params: array<string, int>}|null
     */
    public static function config(Request $request, bool $canSeeTrashed): ?array
    {
        $project = self::fromRequest($request);
        if ($project === null) {
            return null;
        }

        return [
            'text' => 'Hanya project',
            'label' => $project['label'],
            'badge' => $project['deleted'] ? 'project sudah dihapus' : null,
            'note' => $project['deleted']
                ? ($canSeeTrashed
                    ? 'Nyalakan "Tampilkan data terhapus" bila barisnya belum tampil, lalu klik Restore di kolom Action.'
                    : 'Project terhapus hanya tampil untuk admin Master Data: minta admin memulihkannya.')
                : null,
            'clear_text' => 'Tampilkan semua project',
            'clear_url' => $request->fullUrlWithoutQuery([self::PARAM, MasterTrash::REQUEST_PARAM]),
            'params' => [self::PARAM => $project['id']],
        ];
    }
}
