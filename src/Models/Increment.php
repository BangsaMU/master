<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class Increment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_increment";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->string('unique_group', 20)->charset('utf8mb4')->collation('utf8mb4_general_ci');
                    $table->unsignedInteger('increment')->default(1);
                    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();
                    $table->unsignedBigInteger('object_id')->default(0);
                    $table->unsignedBigInteger('object_sub_id')->default(0);
                    $table->string('app_code', 10)->default('APP03');

                    $table->index('object_id', 'Index_1');
                });
            }
        }
    }
}
