<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;

use Illuminate\Support\Facades\DB;

class SequenceNumbers extends Model
{
    use HasFactory;
    use Loggable;

    protected $table = "sequence_numbers";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id(); // bigint(20) unsigned auto_increment
                    $table->unsignedBigInteger('object_id');
                    $table->string('type', 255)->comment('e.g., GDA, GDR');
                    $table->unsignedSmallInteger('year')->comment('The 4-digit year, e.g., 2025');
                    $table->unsignedInteger('sequence_number')->comment('The incrementing number for that year/type');
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                    // Constraint Unique untuk kombinasi type, year, sequence_number, dan object_id
                    $table->unique(['type', 'year', 'sequence_number', 'object_id'], 'number_sequences_type_year_sequence_unique');

                    // Index manual untuk object_id
                    $table->index('object_id', 'number_sequences_requisition_id_foreign');

                    // Konfigurasi Tabel
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
