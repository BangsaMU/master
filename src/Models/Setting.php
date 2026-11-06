<?php

namespace Bangsamu\Master\Models;

use Bangsamu\Master\Services\SchemaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'setting';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! SchemaColumns::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 255);
                    $table->text('value')->comment('buat limit hak akses app yg boleh edit');
                    $table->string('category', 255);
                    $table->timestamps();
                    $table->softDeletes();

                    $table->unique(['category', 'name'], 'category_name_unique');
                });
            }
        }
    }
}
