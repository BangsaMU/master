<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Requisition extends Model
{
    use HasFactory, SoftDeletes;
    // HasSEO,
    protected $table = 'requisition';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public function detail($request)
    {
        //         test ganti query ini

        //   select
        //   t.name AS approval_action,
        //   ro.label_email AS approval_email,
        //   ro.remarks AS approval_remark,
        //   ro.sequence AS sequence,
        //   ro.status AS approval_status,
        //   ro.active AS can_edit,
        //   ro.comment AS approval_comment,
        //   ro.updated_at AS approval_date,
        //   ro.send_notif AS send_notif
        //   from routing ro
        //   join terms t on ro.matrix_type_id=t.term_id
        //   where object_tabel='requisition' and object_id=8

        // dd($request->all());
        $id = $request->id ?? 0;
        // $requestAll = $request->all();
        $limit = 1;
        $offest = 0;

        $Requisition = Requisition::find($id);
        // dd(Auth::user());
        // $user_id = Auth::user()->id ?? 0;
        $user_email = $Requisition->label_originator;
        $user = getMasterUserByEmail($user_email);
        $user_id = $user[0]->master_user_id;

        if(empty($id)){
            abort(403,'ID required');
        }
        $where = !empty($request->input('search')['value']) ? " and r.subject like '%" . $request->input('search')['value'] . "%' " : '';

        // $query_text = "
        // /*detail SPB corporate*/
        // with
        //     vt_user as(
        //     select
        //         r.created_by as created_by_id
        //         , r.remarks as requisition_remarks
        //         -- , c.company_name as created_by
        //         , originator_id
        //         , o.name as originator_name
        //         , 'od.person_position' as originator_position
        //         , approve_by_id
        //         , (select GROUP_CONCAT(name order by FIND_IN_SET(id,approve_by_id) ) from users where FIND_IN_SET(id,approve_by_id)<>0 ) as approve_name
        //         -- , (select GROUP_CONCAT(COALESCE(person_position,'-') order by FIND_IN_SET(user_id,approve_by_id) ) from user_details where FIND_IN_SET(user_id,approve_by_id)<>0 ) as approve_position
        //         , 'approve_position' as approve_position
        //         , for_info_id
        //         , (select GROUP_CONCAT(name order by FIND_IN_SET(id,for_info_id)) from users where FIND_IN_SET(id,for_info_id)<>0 order by FIND_IN_SET(id,for_info_id)) as for_info_name
        //         -- , (select GROUP_CONCAT(COALESCE(person_position,'-') order by FIND_IN_SET(user_id,for_info_id) ) from user_details where FIND_IN_SET(user_id,for_info_id)<>0 ) as for_info_position
        //         , 'for_info_position' as for_info_position
        //     from requisition r
        //         left join routing ro on ro.object_id=r.id and ro.requisition_number=concat(r.code_number,r.version) and ro.matrix_type_id=145 /*filter hanya origin*/
        //         left join users c on c.id=r.created_by
        //         left join users o on o.id=r.originator_id
        //         left join user_details od on od.user_id=o.id
        //         where 1=1
        //                 and r.id= ?
        //     )
        //     ,vt_r as(
        //         select
        //         ro.id, ro.object_id, ro.matrix_type_id, ro.comment, ro.remarks, ro.active, ro.sequence, ro.status, ro.requisition_number, ro.action_date
        //         ,u.name
        //         , if(ro.user_id=" . $user_id . " and  ro.active =1,1,0) as can_edit
        //         from routing ro
        //         join users u on ro.user_id=u.id
        //             where 1=1
        //             and ro.object_id=" . $id . "
        //             and ro.matrix_type_id=142 /*filter hanya approval*/
        //         )
        //     ,vt_department as(
        //             select tt.term_id, tt.taxonomy  from term_taxonomy tt
        //             where tt.taxonomy='dept_name'
        //         )
        //     ,vt_detail as(
        //       select
        //       right(r.code_number, 6) as runing_number
        //       , r.status
        //       , ro.can_edit
        //       , ro.action_date
        //       , r.routing_id
        //       , CONV(SUBSTRING(CAST(SHA(ro.requisition_number) AS CHAR), 1, 16), 16, 10) as routing_number
        //       , r.submit_date
        //       , ro.active, ro.sequence, r.id batch_id, r.code_number, r.subject, u.name
        //       , DATE_FORMAT(r.created_at, '%Y-%m-%d') as request_date
        //       , ro.name as aprover_info, ro.comment
        //       , r.duration_id as duration_id
        //       , w.name as duration_name
        //       , COALESCE(ro.status, r.status) as status_latest
        //       , r.status as status_requisition
        //       , r.version as rev
        //       , r.pdf_id as pdf_id
        //       , ro.status as status_aproval
        //       , ro.remarks as routing_remarks
        //       , ro.comment as routing_comment
        //       , r.department_id
        //       , d.name as department_name
        //       , r.spb_source_id
        //       , s.name as spb_source
        //       , r.type_id
        //       , (select name from terms where term_id=r.type_id) as type_name
        //       , r.remarks as scope_of_work
        //       , r.worklocation_id as worklocation_id
        //       , l.name as worklocation_name
        //       , r.project_id as project_id
        //       , p.project_code as project_number
        //       , p.project_name as project_name
        //       , c.id as client_id
        //       , c.company_name as client_name
        //       , t.slug as type_label
        //           from requisition r
        //               left join terms t on t.term_id = r.type_id
        //               left join terms d on d.term_id = r.department_id
        //               left join terms s on s.term_id = r.spb_source_id
        //               left join terms l on l.term_id = r.worklocation_id
        //               left join terms w on w.term_id = r.duration_id
        //               join master_project p on p.id=r.project_id
        //               join master_company c on c.id=1 -- p.company_id hardcode
        //               left join users u on u.id=r.created_by
        //               left join vt_r ro on ro.object_id=r.id

        //       where
        //             1=1
        //               AND (ro.status <> 'close' OR ro.status is null)
        //               and r.id= ?
        //           order By ro.sequence desc
        //           limit ? OFFSET ?
        //     )

        //         select
        //             vt_detail.sequence as sequence,
        //             vt_detail.type_label as type_label,
        //             vt_detail.submit_date,
        //             vt_detail.runing_number as runing_number,
        //             vt_detail.batch_id as id,
        //             vt_detail.code_number,
        //             vt_detail.department_id,
        //             vt_detail.department_name,
        //             vt_detail.request_date,
        //             vt_detail.subject,

        //             vt_detail.type_id,
        //             vt_detail.type_name,
        //             vt_detail.project_id,
        //             vt_detail.project_number,
        //             vt_detail.spb_source_id,
        //             vt_detail.spb_source,
        //             vt_detail.project_name,
        //             vt_detail.client_name,
        //             vt_detail.routing_id,
        //             vt_detail.routing_number,
        //             vt_detail.status,
        //             vt_detail.status_latest,
        //             vt_detail.rev,
        //             vt_detail.pdf_id,
        //             vt_detail.can_edit,

        //             vt_detail.duration_id,
        //             vt_detail.duration_name,
        //             vt_detail.worklocation_id,
        //             vt_detail.worklocation_name,
        //             vt_detail.scope_of_work,
        //             vt_detail.routing_remarks,
        //             vt_detail.routing_comment,
        //             vt_detail.action_date,

        //             vt_user.created_by_id ,
        //             vt_user.originator_id ,
        //             vt_user.originator_name ,
        //             vt_user.originator_position ,
        //             vt_user.approve_by_id ,
        //             vt_user.approve_name ,
        //             vt_user.approve_position ,
        //             vt_user.for_info_id,
        //             vt_user.for_info_name,
        //             vt_user.for_info_position,
        //             vt_user.requisition_remarks

        //         from vt_detail,vt_user
        //         order By vt_detail.sequence desc
        //         limit 1
        // ";
        // $results = DB::select($query_text, [$id, $id, $limit, $offest]);


        $query_text = "
                        select
                                ro.id AS routing_id,
                                ro.routing_number AS routing_number,
                                ro.remarks AS routing_remarks,
                                ro.comment AS routing_comment,
                                ro.sequence AS sequence,
                                t.slug AS type_label,
                                r.submit_date AS submit_date,
                                right(r.code_number, 6) AS runing_number,
                                r.id AS batch_id,
                                r.remarks AS remarks,
                                r.code_number AS code_number,
                                r.department_id AS department_id,
                                d.department_name AS department_name,
                                DATE_FORMAT(r.created_at, '%Y-%m-%d') AS request_date,
                                r.subject AS subject,
                                r.type_id AS type_id,
                                t.name AS type_name,
                                r.project_id AS project_id,
                                mp.project_code AS project_number,
                                mp.project_name AS project_name,
                                r.spb_source_id AS spb_source_id,
                                s.loc_name AS spb_source,
                                mc.company_name AS client_name,
                                r.routing_id AS routing_id,
                                ro.routing_number AS routing_number,
                                r.status AS status,
                                COALESCE(ro.status, r.status) AS status_latest,
                                r.version AS rev,
                                r.remarks AS requisition_remarks,
                                r.originator_id AS originator_id,
                                r.label_originator AS originator_name,
                                r.pdf_id AS pdf_id,
                                if(ro.label_email='" . $user_email . "' and  ro.active =1,1,0)AS can_edit,
                                r.duration_id AS duration_id,
                                w.name AS duration_name,
                                r.worklocation_id AS worklocation_id,
                                l.loc_name AS worklocation_name,
                                null AS scope_of_work,
                                ro.action_date AS action_date,
                                r.approve_by_id AS approve_by_id,
                                r.label_disetujui_oleh AS approve_name,
                                null AS approve_position,
                                r.for_info_id AS for_info_id,
                                r.label_diketahui_oleh AS for_info_name,
                                null AS for_info_position,
                                null AS originator_position

                            from requisition r
                                left join routing ro on r.id =ro.object_id
                                left join terms t on t.term_id = r.type_id
                                left join master_department d on d.id = r.department_id
                                left join master_project mp on mp.id=r.project_id
                                left join master_location s on s.id = r.spb_source_id
                                left join master_project_detail mpd on mpd.project_id=r.project_id
                                left join master_company mc on mc.id=mpd.company_id
                                left join terms w on w.term_id = r.duration_id
                                left join master_location l on l.id = r.worklocation_id

                            where 1=1
                                and r.id=" . $id . "
                                order By ro.sequence desc
                                limit 1
                        ";

        $results = DB::select($query_text);
        $results = isset($results[0]) ? $results[0] : [];

        if(empty($results)){
            abort(403,'slip route detail empty');
        }
        return $results;
    }


    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->id(); // bigint unsigned auto_increment

                    $table->string('code_number', 255)->nullable()->unique()
                        ->comment("format: 21295-SPB-2021-XII-PMT-000001_R1");

                    $table->string('subject', 255);
                    $table->string('project_id', 255)->nullable();

                    $table->unsignedBigInteger('created_by')->nullable()->comment("user login pembuat");

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->unsignedBigInteger('type_id')->comment("SPB/SPJ/SPB-Corporate/SPJ-Corporate");

                    $table->unsignedBigInteger('department_id')->nullable();

                    $table->enum('status', ['draft', 'submit', 'open', 'revision', 'rejected', 'close'])
                        ->default('draft')->comment("status lifecycle SPB/SPJ");

                    $table->unsignedInteger('duration_id')->default(1)->comment("join ke terms (durasi hari)");
                    $table->unsignedBigInteger('spb_source_id')->nullable();
                    $table->unsignedBigInteger('worklocation_id')->nullable();

                    $table->string('remarks', 255)->nullable()->comment("Scope of Work");

                    $table->string('approve_by_id', 255)->nullable()->comment("user approval aktif (sequence tertentu)");
                    $table->string('for_info_id', 255)->nullable()->comment("readonly user info (setelah approved)");
                    $table->unsignedBigInteger('originator_id')->nullable()->comment("user pengaju");

                    $table->date('requisition_date')->useCurrent();
                    $table->date('submit_date')->nullable();

                    $table->string('label_originator', 255)->nullable()->collation('utf8mb4_unicode_ci');
                    $table->string('label_disetujui_oleh', 255)->nullable()->collation('utf8mb4_unicode_ci');
                    $table->string('label_diketahui_oleh', 255)->nullable()->collation('utf8mb4_unicode_ci');

                    $table->unsignedBigInteger('routing_id')->comment("deprecated, routing.active yang digunakan");
                    $table->tinyInteger('version')->comment("increment versi batch (0 default)");

                    $table->unsignedBigInteger('pdf_id')->comment("file PDF hasil generate submit");
                    $table->unsignedBigInteger('expense_id')->nullable();
                    $table->string('project2_id', 255)->nullable();

                    // Indexes
                    $table->index('department_id');
                    $table->index('duration_id');
                    $table->index('label_originator');
                    $table->index('spb_source_id');
                    $table->index('project_id');
                    $table->index('worklocation_id');
                    $table->index('for_info_id');
                    $table->index('originator_id');
                    $table->index('approve_by_id');
                    $table->index('created_by');
                    $table->index('type_id');
                    $table->index('expense_id');
                    $table->index('pdf_id');
                    $table->index('routing_id');

                    // Fulltext index (tidak didukung default Laravel, harus via DB::statement)
                    // DB::statement('ALTER TABLE requisition ADD FULLTEXT code_number_2 (code_number, subject)');
                });
            }
        }
    }
}
