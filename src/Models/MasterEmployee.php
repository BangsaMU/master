<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class MasterEmployee extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $connection = 'db_master';
    public $table = "master_employee";
    protected $guarded = [];

}
