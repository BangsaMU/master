<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apps extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_apps";
    protected $guarded = [];
}
