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

use Bangsamu\LibraryClay\Traits\Loggable;


class MasterItemCode extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    use Loggable;

    protected $connection = 'db_master';
    protected $table = "master_item_code";
    protected $guarded = [];


    public function uom()
    {
        return $this->belongsTo(MasterUom::class, 'uom_id');
    }

    public function pca()
    {
        return $this->belongsTo(MasterPca::class, 'pca_id');
    }

    public function category()
    {
        return $this->belongsTo(MasterCategory::class, 'category_id');
    }

    public function group()
    {
        return $this->belongsTo(MasterItemGroup::class, 'group_id');
    }

    public function app()
    {
        return $this->belongsTo(MasterApps::class, 'app_code', 'app_code');
    }

    public function pictures()
    {
        // Parameter: (ModelTarget, ForeignKey_di_tabel_gambar, LocalKey_di_tabel_item)
        return $this->hasMany(MasterItemCodePicture::class, 'item_code_id', 'id');
    }
    
    public function latestPicture()
    {
        return $this->hasOne(MasterItemCodePicture::class, 'item_code_id', 'id')->latestOfMany();
    }
}
