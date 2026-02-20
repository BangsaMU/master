<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Project extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_project";
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
                    $table->string('project_code', 10)->unique()->nullable();
                    $table->string('project_name', 116)->nullable()->unique('unique_project_name');
                    $table->string('project_remarks', 255)->nullable();
                    $table->date('project_start_date')->nullable();
                    $table->date('project_complete_date')->nullable();
                    $table->char('internal_external', 1)->nullable();
                    $table->integer('user_id')->nullable();
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at
                });
            }
        }
    }
}
