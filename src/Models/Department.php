<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Department extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_department';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('department_code', 15)->nullable();
                    $table->string('department_name', 100)->nullable();
                    $table->string('app_code', 10)->default('APP03');
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    // kode department unik per aplikasi (sama seperti kepemilikan app_code di master_item_code)
                    $table->unique(['department_code', 'app_code'], 'uq_dept_code_app');
                    $table->index('app_code', 'idx_dept_app_code');
                });
            }
        }
    }
}
