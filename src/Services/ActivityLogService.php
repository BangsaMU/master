<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\LibraryClay\Models\ActivityLog;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Riwayat activity_logs untuk satu record master (dipakai view logs_view.list).
 */
class ActivityLogService
{
    public const PER_PAGE = 10;

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
     */
    public function decorate(ActivityLog $log): ActivityLog
    {
        $description = (string) $log->description;
        $action = (string) $log->action;
        $position = $action !== '' ? strpos($description, $action) : false;

        $log->setAttribute('nama_user', $position === false ? '' : trim(substr($description, 0, $position)));
        $log->setAttribute('sort_description', $position === false ? $description : trim(substr($description, $position + strlen($action))));
        $log->setAttribute('updated_at_formated', $log->updated_at?->format('j F Y H:i:s'));
        $log->setAttribute('updated_at_formated_date', $log->updated_at?->format('j F Y'));
        $log->setAttribute('updated_at_formated_time', $log->updated_at?->format('H:i:s'));

        return $log;
    }
}
