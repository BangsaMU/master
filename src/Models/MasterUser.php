<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class MasterUser extends Model
{
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $connection = 'db_master';

    protected $table = 'master_user';

    protected $guarded = [];

    public static function detailsLocation($id = null)
    {

        $user_id = $id ?? auth()->user()->id;

        $user = DB::table('master_user as tbl1')
            ->select(
                'tbl1.id',
                'tbl1.name',
                'tbl1.email',
                'tbl1.role',
                'tbl2.id as role_id',
                'tbl2.name as role_name',
                // 'tbl3.id as company_id',
                // 'tbl3.company_name',
                'ud.signature',
                'ud.alamat',
                'ud.location_id',
            )
            ->leftJoin('roles as tbl2', 'tbl2.id', '=', 'tbl1.role')
                    // ->leftJoin('master_companys as tbl3', 'tbl1.company_id', '=', 'tbl3.id')
                    // ->leftJoin('user_details as ud', 'tbl1.id', '=', 'ud.user_id')
            ->leftJoin(
                DB::raw("
                            (
                            select
                                user_id,
                                max(case when (field_key='signature') then field_value else NULL end) as 'signature',
                                max(case when (field_key='alamat') then field_value else NULL end) as 'alamat',
                                group_concat(case when (field_key='location_id') then field_value else NULL end) as 'location_id'
                            from master_user_details
                            where
                                user_id = ".$user_id.'
                            group by user_id
                            order by user_id
                            ) `ud`
                        '),
                'tbl1.id',
                '=',
                'ud.user_id'
            )
            ->where('tbl1.id', $user_id)
            ->first();

        $list_location_id = $user->location_id;
        $hrdhire = MasterLocation::select('id', DB::raw('concat(loc_code, " - ", loc_name) as location'))->whereRaw('FIND_IN_SET(id, ?)', [$list_location_id])->get();
        $list_location = $hrdhire->pluck('location', 'id');
        $location_label = $hrdhire->pluck('location')->toArray();

        $user->location_detail = $list_location;
        $user->location_detail_label = count($location_label) > 2 ? 'Multi Location' : implode(', ', $location_label);

        return $user;
    }
}
