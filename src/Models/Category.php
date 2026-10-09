<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Category extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_category';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('category_code', 55)->unique()->nullable();
                    $table->string('category_name', 110)->nullable();
                    $table->string('remark')->nullable();
                    $table->string('app_code', 10)->default('APP03');
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->index('app_code', 'index_app_code');
                });
            }
        }
    }
}
