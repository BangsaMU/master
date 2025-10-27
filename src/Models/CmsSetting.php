<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class CmsSetting extends Model
{
    use HasFactory;

    protected $table = 'cms_setting';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create('cms_setting', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 255);
                    $table->text('value');
                    $table->string('category', 255);
                    $table->timestamps();
                    $table->softDeletes();

                    $table->unique(['category', 'name'], 'category_name_unique');
                });
            }
        }
    }
}
