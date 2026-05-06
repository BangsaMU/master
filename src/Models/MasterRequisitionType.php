<?php

namespace Bangsamu\Master\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterRequisitionType extends Model
{
    use HasFactory;

    protected $connection = 'db_master';
    // protected $guarded = [];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'requisition_type';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['type_name', 'code'];

    /**
     * Get the report number format associated with the requisition type.
     */
    public function reportNumberFormat()
    {
        return $this->hasOne(ReportNumberFormat::class, 'type_id');
    }
}
