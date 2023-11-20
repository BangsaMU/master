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

class Location extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    public $table = "master_location";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('location_code', 50)->nullable();
                $table->string('location_name', 100)->nullable();
                $table->string('location_address', 100)->nullable();
                $table->string('location_description', 150)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('group', 100)->nullable()->index('group');
            });
        }
    }
}
