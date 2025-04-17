<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class Apps extends Model
{
    use HasFactory, SoftDeletes;

    public $table = "master_apps";
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
                    $table->string('app_code', 10)->unique();
                    $table->string('name', 25);
                    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                    $table->softDeletes();
                    $table->dateTime('updated_at')->nullable();
                    $table->string('app_url', 100)->nullable();
                    $table->string('app_icon', 100)->nullable();

                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_general_ci';
                });
            }
        }
    }
}
