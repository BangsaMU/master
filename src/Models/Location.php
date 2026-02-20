<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Location extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_location";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('loc_code', 15)->unique()->nullable();
                    $table->string('loc_name', 50)->nullable();
                    $table->enum('group_type', ['office', 'warehouse', 'vendor', 'clinic', 'ohih', 'mcu', 'hrd'])->default('office');
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                });
            }
        }
    }
}
