<?php

declare(strict_types=1);

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\MasterUniqueKeyGuard;
use Bangsamu\Master\Services\VendorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pulihkan (restore) data master yang terhapus (soft delete) dari daftar master: hanya admin, dan di aplikasi klien
 * hanya baris milik aplikasinya (MasterTrash::canRestore). Route POST master/{slug}/{id}/restore, nama
 * master.{slug}.restore, slug dari defaults route.
 */
class MasterRestoreController extends Controller
{
    /** modul yang bisa dipulihkan: model Master{Studly} ber-SoftDeletes, dipakai juga untuk mendaftarkan route */
    public const MODULES = [
        'category', 'company', 'department', 'employee', 'item-code', 'item-group', 'location', 'pca', 'priority',
        'project', 'project-detail', 'uom', 'vendor',
    ];

    public function restore(Request $request, string $id): RedirectResponse
    {
        $slug = (string) $request->route('slug');
        abort_unless(in_array($slug, self::MODULES, true), 404);

        $modelClass = 'Bangsamu\\Master\\Models\\Master'.Str::studly($slug);
        abort_unless(class_exists($modelClass) && in_array(SoftDeletes::class, class_uses_recursive($modelClass), true), 404);

        /** @var Model $model */
        $model = $modelClass::withTrashed()->findOrFail($id);
        $trash = app(MasterTrash::class);
        abort_unless($trash->canRestore($slug, $model), 403, 'Hanya admin yang dapat memulihkan data master '.$slug.' yang terhapus.');

        $index = redirect()->route('master.'.$slug.'.index');
        // pesan flash dicetak tanpa escape oleh layout daftar
        $label = Str::headline($slug).' '.e($this->label($slug, $model));
        if (! $model->trashed()) {
            return $index->with('success_message', $label.' tidak terhapus, tidak ada yang dipulihkan.');
        }

        $conflict = $this->activeDuplicate($slug, $model);
        if ($conflict !== null) {
            return $index->with('error_message', $label.' tidak dapat dipulihkan: kodenya sudah dipakai data aktif id '.$conflict->id.'.');
        }

        $deletedAt = $model->getAttribute($model->getDeletedAtColumn());
        // model db_master seperti destroy(); Loggable mencatat updated (deleted_at -> null), broadcast master ikut jalan
        $model->restore();
        if ($slug === 'vendor') {
            app(VendorService::class)->restoreVendorLocation($model); // lokasi vendor ikut terhapus saat vendor dihapus
        }
        $this->logRestored($slug, $model, $deletedAt);

        return $index->with('success_message', $label.' berhasil dipulihkan.');
    }

    /**
     * Catat "restored" atas nama model yang dibaca kartu riwayat form (Models\{Studly}, ActivityLogService::forModel),
     * bukan model Master{Studly}, supaya pemulihan tampil di Log {modul}.
     */
    protected function logRestored(string $slug, Model $model, mixed $deletedAt): void
    {
        if (! method_exists($model, 'shouldLogActivity') || ! $model->shouldLogActivity()) {
            return;
        }

        $historyClass = 'Bangsamu\\Master\\Models\\'.Str::studly($slug);
        $subject = $model;
        if (class_exists($historyClass)) {
            $subject = (new $historyClass)->forceFill([$model->getKeyName() => $model->getKey()]);
        }

        ActivityLog::log('restored', ActivityLog::actorName().' restored '.class_basename($subject), $subject, [
            'before' => [$model->getDeletedAtColumn() => (string) $deletedAt],
            'after' => [$model->getDeletedAtColumn() => null],
        ]);
    }

    /** kode yang sama sudah dipakai baris aktif lain (data lama yang lolos index unik), cek sebelum memulihkan */
    protected function activeDuplicate(string $slug, Model $model): ?object
    {
        $keys = array_keys(MasterUniqueKeyGuard::KEYS[$slug][1] ?? []);
        if ($keys === []) {
            return null;
        }

        $scope = app(MasterScope::class);
        $query = DB::connection($model->getConnectionName())->table($model->getTable())
            ->where('id', '!=', $model->getKey())
            ->whereNull($model->getDeletedAtColumn());
        foreach ($keys as $column) {
            $query->where($column, $model->getAttribute($column));
        }
        foreach ($scope->uniqueOwner($model->getTable(), $scope->ownerOf($model->getAttributes())) as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first(['id']);
    }

    protected function label(string $slug, Model $model): string
    {
        $codes = MasterUniqueKeyGuard::KEYS[$slug][2] ?? [str_replace('-', '_', $slug).'_code'];
        $code = $model->getAttribute($codes[0]);

        return filled($code) ? (string) $code : 'id '.$model->getKey();
    }
}
