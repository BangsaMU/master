<?php

namespace Bangsamu\Master\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Bangsamu\LibraryClay\Traits\Loggable;

class MasterSequenceNumbers extends Model
{
    use HasFactory;
    use Loggable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sequence_numbers';
 
    protected $connection = 'db_master';
    protected $guarded = [];
}
