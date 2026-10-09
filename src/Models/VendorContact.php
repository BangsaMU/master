<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class VendorContact extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_vendor_contact';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s';

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
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
