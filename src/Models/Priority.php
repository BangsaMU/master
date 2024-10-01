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

class Project extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    public $table = "master_priority";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('project_code', 10)->nullable();
                $table->string('project_name', 116)->nullable()->unique('unique_project_name');
                $table->string('project_remarks', 255)->nullable();
                $table->date('project_start_date')->nullable();
                $table->date('project_complete_date')->nullable();
                $table->char('internal_external', 1)->nullable();
                $table->integer('user_id')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }
    }

}
