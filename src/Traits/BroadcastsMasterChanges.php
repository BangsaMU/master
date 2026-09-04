<?php

declare(strict_types=1);

namespace Bangsamu\Master\Traits;

use Bangsamu\Master\Services\MasterBroadcastService;
use Throwable;

trait BroadcastsMasterChanges
{
    /**
     * Flag to disable auto-broadcasting (e.g. during internal sync).
     */
    public static bool $disableMasterBroadcast = false;

    /**
     * Boot the trait and attach Eloquent lifecycle listeners.
     */
    public static function bootBroadcastsMasterChanges(): void
    {
        static::saved(function ($model) {
            if (static::$disableMasterBroadcast || MasterBroadcastService::isBroadcastingDisabled()) {
                return;
            }

            try {
                if (config('MasterConfig.senada.active', true)) {
                    $table = $model->getTable();
                    $action = $model->wasRecentlyCreated ? 'created' : 'updated';
                    app(MasterBroadcastService::class)->broadcastTableChange($table, $model, $action);
                }
            } catch (Throwable $e) {
                // Non-blocking, do not interrupt main database transactions
            }
        });

        static::deleted(function ($model) {
            if (static::$disableMasterBroadcast || MasterBroadcastService::isBroadcastingDisabled()) {
                return;
            }

            try {
                if (config('MasterConfig.senada.active', true)) {
                    $table = $model->getTable();
                    app(MasterBroadcastService::class)->broadcastTableChange($table, $model, 'deleted');
                }
            } catch (Throwable $e) {
                // Non-blocking
            }
        });
    }
}
