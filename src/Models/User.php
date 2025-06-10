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
use Illuminate\Support\Facades\DB;

use Bangsamu\LibraryClay\Traits\Loggable;


class User extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    use Loggable;

    public $table = "master_user";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->bigIncrements('id');
                    $table->string('email', 255)->nullable()->unique(); // email column with UNIQUE constraint
                    $table->string('password', 150)->nullable(); // password column
                    $table->string('name', 25); // name column (NOT NULL)
                    $table->string('full_name', 70)->nullable(); // full_name column
                    $table->smallInteger('position_id')->nullable(); // position_id column (SmallInt)
                    $table->tinyInteger('department_id')->nullable(); // department_id column (TinyInt)
                    $table->string('api_token', 255)->nullable(); // api_token column
                    $table->dateTime('created_at')->nullable(); // created_at column
                    $table->dateTime('updated_at')->nullable(); // updated_at column
                    $table->dateTime('deleted_at')->nullable(); // deleted_at column
                    $table->integer('is_active')->default(0); // is_active column (Int) with default value 0
                    $table->string('role', 255)->nullable(); // role column
                    $table->string('list_apps_id', 100)->default('0'); // list_apps_id column with default value '0'
                    $table->string('remember_token', 100)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci'); // remember_token column with different charset/collation
                });
            }
        }
    }


    public static function details($id = null){

        $user_id = $id ?? auth()->user()->id;

        $user = DB::connection('db_master')->table('master_user as tbl1')
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
                                user_id = " . $user_id . "
                            group by user_id
                            order by user_id
                            ) `ud`
                        "),
                        'tbl1.id',
                        '=',
                        'ud.user_id'
                    )
                    ->where('tbl1.id', $user_id)
                    ->first();

                    $list_location_id = $user->location_id;
                    $hrdhire = MasterLocation::select('id', DB::raw('concat(loc_code, " - ", loc_name) as location'))->where('group_type','hrd')->whereRaw('FIND_IN_SET(id, ?)', [$list_location_id])->get();
                    $list_location = $hrdhire->pluck('location', 'id');
                    $location_label = $hrdhire->pluck('location')->toArray();

                    $user->location_detail = $list_location;
                    $user->location_detail_label = count($location_label) > 2 ? 'Multi Location' : implode(', ', $location_label);

        return $user;
    }
}
