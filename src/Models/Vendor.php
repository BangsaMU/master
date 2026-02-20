<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;
class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_vendor";
    protected $guarded = [];

    protected $fillable = [
        'vendor_code',
        'vendor_description',
        'vendor_address',
        'vendor_phone',
    ];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->unsignedBigInteger('loc_id');
                    $table->string('vendor_code', 15)->nullable();
                    $table->string('vendor_description')->nullable();
                    $table->string('vendor_address')->nullable();
                    $table->string('vendor_phone', 100)->nullable();
                    $table->string('vendor_email', 100)->nullable();
                    $table->string('vendor_fax', 100)->nullable();
                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at
                });
            }
        }
    }
}
