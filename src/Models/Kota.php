<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Kota extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_kota";
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
                    $table->string('id', 10)->primary();
                    $table->string('nama', 32);
                    $table->double('latitude')->default(0);
                    $table->double('longitude')->default(0);
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                });
            }
        }
    }
}
