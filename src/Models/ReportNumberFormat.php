<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReportNumberFormat extends Model
{
    use HasFactory;
    use Loggable;

    protected $table = 'report_number_format';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->tinyIncrements('id'); // tinyint unsigned auto_increment primary key
                    $table->string('name', 255);
                    $table->string('format_string', 255);
                    $table->unsignedInteger('type_id')->unique(); // int unsigned & unique constraint

                    // Konfigurasi Database Engine & Collation
                    $table->engine = 'InnoDB';
                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_general_ci';

                });
            }
        }
    }

    /**
     * Get the requisition type that owns the report number format.
     */
    public function requisitionType(): BelongsTo
    {
        return $this->belongsTo(RequisitionType::class, 'type_id');
    }

    /**
     * Get the type code attribute from the related requisition type.
     */
    public function getTypeCodeAttribute(): ?string
    {
        return $this->requisitionType?->type_code;
    }
}
