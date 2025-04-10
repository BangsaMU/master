<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class ItemCode extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_item_code";
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->string('item_code', 25)->unique()->nullable();
                $table->string('item_name')->nullable();
                $table->integer('uom_id')->nullable();
                $table->integer('pca_id')->nullable();
                $table->integer('category_id')->nullable();
                $table->integer('group_id')->nullable();
                $table->text('remarks')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->string('app_code', 10)->default('APP03');
                $table->longText('attributes')->nullable();

                $table->index('app_code', 'index_app_code');
            });
        }
    }
}
