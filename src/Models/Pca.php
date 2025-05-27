<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Pca extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    public $table = "master_pca";
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
                    $table->string('pca_code', 20)->unique()->nullable();
                    $table->string('pca_name', 150)->nullable();
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
