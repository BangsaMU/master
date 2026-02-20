<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class VendorContact extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_vendor_contact";
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
                    $table->unsignedBigInteger('vendor_id')->nullable();
                    $table->string('vendor_contact_name', 100)->nullable();
                    $table->string('vendor_contact_phone', 100)->nullable();
                    $table->string('vendor_contact_email', 100)->nullable();
                    $table->string('vendor_contact_fax', 100)->nullable();

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at
                });
            }
        }
    }
}
