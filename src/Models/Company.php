<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_company";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->integer('id', true);
                    $table->string('company_code', 10)->unique()->nullable();
                    $table->string('company_name', 200)->nullable();
                    $table->string('company_short', 10)->nullable();
                    $table->string('company_attention', 100)->nullable();
                    $table->string('company_address', 300)->nullable();
                    $table->bigInteger('company_logo_id')->nullable();
                    $table->text('company_logo')->nullable();
                    $table->dateTime('created_at')->nullable();
                    $table->dateTime('updated_at')->nullable();
                    $table->dateTime('deleted_at')->nullable();
                });
            }
        }
    }
}
