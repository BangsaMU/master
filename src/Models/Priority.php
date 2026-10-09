<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class Priority extends Model
{
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_priority';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('priority_code', 4)->nullable();
                    $table->string('priority_name', 50)->nullable();
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at
                });
            }
        }
    }
}
