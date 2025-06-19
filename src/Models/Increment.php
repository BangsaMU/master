<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;

use Illuminate\Support\Facades\DB;

class Increment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

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
                    $table->id();
                    $table->string('unique_group', 20)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
                    $table->unsignedInteger('increment')->default(1);
                    $table->unsignedBigInteger('object_id')->default(0);
                    $table->unsignedBigInteger('object_sub_id')->default(0);
                    $table->string('app_code', 10)->default('APP03');
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->index('object_id', 'Index_1');
                });
            }
        }
    }
}
