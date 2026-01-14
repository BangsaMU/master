<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Builder;

use Bangsamu\LibraryClay\Traits\Loggable;


class MasterLocation extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    use Loggable;

    protected $connection = 'db_master';
    public $table = "master_location";
    protected $guarded = [];

    public function scopeIsHrd(Builder $query): Builder
    {
        return $query->where('group_type', 'hrd');
    }
}
