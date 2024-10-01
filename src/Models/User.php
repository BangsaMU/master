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

class User extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    public $table = "master_user";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('email', 255)->nullable()->unique(); // email column with UNIQUE constraint
                $table->string('password', 150)->nullable(); // password column
                $table->string('name', 25); // name column (NOT NULL)
                $table->string('full_name', 70)->nullable(); // full_name column
                $table->smallInteger('position_id')->nullable(); // position_id column (SmallInt)
                $table->tinyInteger('department_id')->nullable(); // department_id column (TinyInt)
                $table->string('api_token', 255)->nullable(); // api_token column
                $table->dateTime('created_at')->nullable(); // created_at column
                $table->dateTime('updated_at')->nullable(); // updated_at column
                $table->dateTime('deleted_at')->nullable(); // deleted_at column
                $table->integer('is_active')->default(0); // is_active column (Int) with default value 0
                $table->string('role', 255)->nullable(); // role column
                $table->string('list_apps_id', 100)->default('0'); // list_apps_id column with default value '0'
                $table->string('remember_token', 100)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci'); // remember_token column with different charset/collation
            });
        }
    }

}
