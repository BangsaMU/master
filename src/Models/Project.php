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

    public $table = "master_project";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('project_code', 100)->nullable();
                $table->string('project_name', 116)->nullable()->unique('unique_project_name');
                $table->integer('contractor_id')->nullable();
                $table->integer('client_id')->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->string('pic_name', 50)->nullable();
                $table->integer('pic_user_id')->nullable();
                $table->string('status', 50)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('group', 100)->nullable()->index('index_group');
            });
        }
    }

}
