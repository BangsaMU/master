<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;

use Illuminate\Support\Facades\DB;

class RequisitionType extends Model
{
    use HasFactory;
    use Loggable;

    protected $table = "requisition_type";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id(); // bigint(20) auto_increment
                    $table->string('type_name', 100)->nullable()->default(null);
                    $table->string('code', 10);
                    $table->string('description', 255);
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->nullable();
                    
                    // Engine & Collation (Opsional, Laravel mengikuti config database)
                    $table->engine = 'InnoDB';
                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_general_ci';
 
                });
            }
        }
    }
    
    /**
     * Get the report number format associated with the requisition type.
     */
    public function reportNumberFormat()
    {
        return $this->hasOne(ReportNumberFormat::class, 'type_id');
    }
}
