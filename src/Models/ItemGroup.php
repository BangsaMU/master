<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class ItemGroup extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    public $table = "master_item_group";
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
                    $table->string('item_group_code', 20)->unique()->nullable();
                    $table->string('item_group_name', 50)->nullable();
                    $table->string('app_code', 10)->default('APP03');
                    $table->longText('item_group_attributes')->nullable();
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at


                    $table->index('app_code', 'index_app_code');
                });
            }
        }
    }
}
