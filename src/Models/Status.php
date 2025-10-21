<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Status extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    public $table = "master_status";
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
                    $table->string('kode', 2)->unique();
                    $table->string('status', 14)->unique();
                    $table->string('app_code', 10)
                        ->default('APP03')
                        ->comment('buat limit hak akses app yg boleh edit');

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    // Index dan constraint
                    $table->unique(['kode', 'app_code'], 'kode');
                    $table->unique(['status', 'app_code'], 'status');
                    $table->index('app_code', 'Index_1');
                });
            }
        }
    }
}
