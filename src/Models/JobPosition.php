<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class JobPosition extends Model
{
    use HasFactory, SoftDeletes;
    use Loggable;

    protected $table = 'master_job_position';
    protected $dates = ['deleted_at'];

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
                    $table->integer('department_id')->nullable();        $table->integer('department_id')->nullable();
                    $table->string('position_code', 15)->unique();
                    $table->string('position_name', 100);
                    $table->string('position_ranking_code', 20)->nullable()->index();

                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();

                    $table->index('position_code', 'index_position_code');
                });
            }
        }
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
