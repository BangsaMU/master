<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class Location extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_location';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('loc_code', 15)->nullable();
                    $table->string('loc_name', 50)->nullable();
                    $table->enum('group_type', ['office', 'warehouse', 'vendor', 'clinic', 'ohih', 'mcu', 'hrd'])->default('office');
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->unique(['loc_code', 'group_type'], 'loc_code_group_unique');
                    $table->index('loc_name');

                });
            }
        }
    }
}
