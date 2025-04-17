<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_category";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->integer('id', true);
                    $table->string('category_code', 25)->unique()->nullable();
                    $table->string('category_name', 50)->nullable();
                    $table->string('remark')->nullable();
                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();
                    $table->string('app_code', 10)->default('APP03');

                    $table->index('app_code', 'index_app_code');
                });
            }
        }
    }
}
