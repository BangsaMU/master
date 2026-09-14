<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class MasterVendorContact extends Model
{
    use \Bangsamu\Master\Traits\BroadcastsMasterChanges;
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $connection = 'db_master';

    protected $table = 'master_vendor_contact';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s';
}
