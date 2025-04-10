<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class ItemGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_item_group";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('item_group_code', 20)->unique()->nullable();
                $table->string('item_group_name', 50)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('app_code', 10)->default('APP03');

                $table->index('app_code', 'index_app_code');
            });
        }
    }
}
