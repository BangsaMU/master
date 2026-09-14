<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class MasterBrand extends Model
{
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $connection = 'db_master';

    protected $table = 'master_brand';

    protected $guarded = [];
}
