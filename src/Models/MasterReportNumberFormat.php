<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportNumberFormat extends Model
{
    use HasFactory;

    protected $connection = 'db_master';
    // protected $guarded = [];
    protected $table = 'report_number_format';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'name',
        'format_string',
        'type_id',
    ];

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