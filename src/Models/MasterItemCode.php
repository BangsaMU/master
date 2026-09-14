<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class MasterItemCode extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $connection = 'db_master';

    protected $table = 'master_item_code';

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
