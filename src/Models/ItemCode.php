<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class ItemCode extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    public $table = "master_item_code";
    protected $primaryKey = 'id';
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->bigInteger('id', true);
                    $table->string('item_code', 25)->unique()->nullable();
                    $table->string('item_name')->nullable();
                    $table->integer('uom_id')->nullable();
                    $table->integer('pca_id')->nullable();
                    $table->integer('category_id')->nullable();
                    $table->integer('group_id')->nullable();
                    $table->text('remarks')->nullable();
                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();
                    $table->string('app_code', 10)->default('APP03');
                    $table->longText('attributes')->nullable();
                    $table->string('nav_code', 25)->nullable();

                    $table->index('app_code', 'index_app_code');
                });
            }
        }
    }
}
