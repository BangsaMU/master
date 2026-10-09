<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Kecamatan extends Model
{
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_kecamatan';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
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
