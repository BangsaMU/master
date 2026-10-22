<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ItemCodePicture extends Model
{
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_item_code_picture';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    // skema sama dengan master_item_code_picture di DB master (bukan salinan master_item_code)
                    $table->integer('id', true);
                    $table->integer('item_code_id')->nullable();
                    $table->string('folder_url', 255)->nullable();
                    $table->string('thumbnail_url', 255)->nullable();
                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();

                });
            }
        }
    }
}
