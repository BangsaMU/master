<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DashboardSettings extends Model
{
    use HasFactory;

    protected $table = 'dashboard_settings';
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            // FIX: Gunakan string langsung, jangan 'new static'
            $tableName = 'dashboard_settings'; 

            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->id();
                    $table->string('key')->unique();
                    $table->text('value')->nullable();
                    $table->string('group')->default('general');
                    $table->string('type')->default('text');
                    $table->string('label');
                    $table->text('options')->nullable();
                    $table->integer('order')->default(0);
                    $table->timestamps();

                    $table->index('group', 'group_index');
                });
            }
        }
    }
}
