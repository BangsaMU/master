<?php

namespace Bangsamu\Master\Models;

use App\Models\User;
use App\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
// use Auth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Routing extends Model
{
    public $table = "routing";
    protected $guarded = ['id'];

    /**
     * FUngsi mendapatkan routing id untuk originator
     * untuk digunakan kirim email
     * @param string $requisition_number
     * @param integer $matrix_type_id [145=> originator, 142=> approval, 138=> Information] untuk detail list bisa di lihat di tabekl terms filter term_group=10
     */
    public function getOriginator($requisition_number)
    {
        $query_text = '
                    select ro.id from routing ro where ro.requisition_number=? and ro.matrix_type_id=145 and ro.sequence=0
        ';
        $results = DB::select($query_text, [$requisition_number]);
        $result = $results[0] ?? null;
        return $result;
    }

    /**
     * FUngsi set routing yang aktiv untuk menentukan user yg bisa edit dokumen
     * untuk digunakan kirim email
     * @param string $requisition_number
     * @param integer $matrix_type_id [145=> originator, 142=> approval, 138=> Information] untuk detail list bisa di lihat di tabekl terms filter term_group=10
     * @param integer $user_id
     */
    public function setActive($requisition_number, $sequence)
    {
        $query_text = '
                        UPDATE `routing` SET `active` = 1
                        WHERE
                            `requisition_number` = ?
                        and `sequence` = ? ;
        ';
        $results = DB::select($query_text, [$requisition_number, $sequence]);

        $query_text2 = '
        select id from routing where `requisition_number` = ? and `sequence` =?;
        ';
        $results2 = DB::select($query_text2, [$requisition_number, $sequence]);

        $result = $results2[0] ?? null;
        return $result;
    }

    /**
     * FUngsi mendapatkan routing id yang aktiv berdasarkan requisition_number yang statusnya aktif
     * untuk digunakan kirim email
     * @param string $requisition_number
     * @param integer $matrix_type_id [145=> originator, 142=> approval, 138=> Information] untuk detail list bisa di lihat di tabekl terms filter term_group=10
     * @param integer $user_id
     */
    public function getActive($requisition_number, $matrix_type_id)
    {
        $query_text = '
                    select ro.id from routing ro where ro.requisition_number=? and ro.matrix_type_id=? and ro.active=1
        ';
        $results = DB::select($query_text, [$requisition_number, $matrix_type_id]);
        $result = $results[0] ?? null;
        return $result;
    }
    /**
     *  routing.detail
     */
    public function detail($request)
    {
        $user = new User();
        // $requisition_detail  = $requisition->detail($request);
        $tabel_user = $user->getTableName();
        $requestAll = $request->all();
        $requisition_id = $request->input('id');
        $matrix_type_id = $request->input('matrix_type_id');
        $limit = 10;
        $offest = 0;
        $Requisition = Requisition::find($requisition_id);
        $version = $Requisition->version;

        // $whereParam = !empty($request->input('q')) ? " and MATCH (caption) AGAINST ('" . $request->input('q') . "*' IN BOOLEAN MODE) " : "";
        $whereParam = !empty($request->input('search')['value']) ? " and MATCH (caption,filename) AGAINST ('" . $request->input('search')['value'] . "*' IN BOOLEAN MODE) " : "";
        $whereParam = "";

        $where = $requisition_id ? "and ro.object_id=" . $requisition_id : "";
        $results = DB::select(
            "
                                /*query route slip history detail*/
                                select
                                    @rownum := @rownum + 1 as 'index',
                                    if(ro.active, 'edit', 'view') as acces_doc,
                                    ro.id as routing_id,
                                    --   rl.id as routing_log_id,
                                    ro.object_id,
                                    ro.user_id as user_id,
                                    ro.label_user as user_name,
                                    ro.label_email as user_email,
                                    --   rl.indicate as log_indicate_id,
                                    ro.matrix_type_id as indicate_id,
                                    COALESCE(mt.name) as indicate_name,
                                    ro.sequence,
                                    ro.created_at as start_date,
                                    ro.due_date as due_date,
                                    ro.action_date as action_date,
                                    ro.version as version,
                                    ro.action_date AS response_date,
                                    ro.comment AS `comment`,
                                    ro.remarks AS `remarks`,
                                    --   rl.status_resp as status_resp_id,
                                    ro.status as status_resp_name

                                from routing ro
                                cross join (select @rownum := ? ) r
                                join terms mt on mt.term_id=ro.matrix_type_id
                                -- left join ".$tabel_user." u on u.id=ro.user_id
                                -- left join route_log rl on rl.routing_id=ro.id
                                -- left join terms t on t.term_id=rl.indicate
                                -- left join terms t2 on t2.term_id=rl.status_resp
                                where 1=1
                                    and ro.matrix_type_id=" . $matrix_type_id . "
                                    and ro.object_tabel='requisition'
                                    and ro.version=" . $version . "
                                    " . $where . "

                                limit ? offset ?
            ",
            [$offest, $limit, $offest]
        );

        /*jika detail tidak ditemukan dan bukan buat baru (id = null)*/

        if (empty($results[0]) && (empty($id) || $id != 'null')) {
            // abort('404');
        }
        $return = $results ?? [];

        return $return;
    }

    /**
     * jumlah requisition-corporate attachment semua
     */
    public function count($request)
    {
        $requisition = new Requisition;
        $requisition_detail = $requisition->detail($request);

        $requestAll = $request->all();
        $id = $request->input('id');
        $limit = $request->input('length') ?? 10;
        $offest = $request->input('start') ?? 0;
        $whereParam = "";
        $where = $id ? "and ra.requisition_id=" . $id . " " : "";
        $results = DB::select("
                            /*query count list routing*/
                            select
                                count(*) as jml_filter
                            from routing as ro
                        ")[0]->jml_filter;
        return $results;
    }

    /**
     * jumlah requisition-corporate attachment berdasarkan requisition_id
     */
    public function count_filter($request)
    {
        $requisition = new Requisition;
        $requisition_detail = $requisition->detail($request);

        $requestAll = $request->all();
        $id = $request->input('id');
        $limit = $request->input('length') ?? 10;
        $offest = $request->input('start') ?? 0;
        $whereParam = "";
        $where = $id ? " and object_tabel='requisition' and ro.object_id=" . $id . " " : "";
        $results = DB::select("
                            /*query count list routing filter by object_id tabel requisition*/
                            select
                                count(*) as jml_filter
                            from routing as ro
                            where 1=1
                                " . $where . "
                        ")[0]->jml_filter;
        return $results;
    }

    /**
     * Fungsi untuk hitung requisition berdasarkan param user.id yang login serta filter param tab
     *
     * Terdapat filter berdasarkan request param tab
     * untuk tab inbox akan menampilan data requisition berdasarkan approval atau for info yang di assign berdasarkan user.id diambil dari auth login id
     * untuk tab audit akan menampilan semua data requisition jika role user [admin,root] berdasarkan approval atau for info
     * untuk tab draf akan menampilan data requisition berdasarkan originator yang masih belum submit berdasarkan user.id yang sedang login
     *
     */
    public function statusCount($request)
    {
        // dencript_query_str($request);

        //         $id = $request->id;
        //         $where = $id ? ' and FIND_IN_SET('.$id.',r.approve_by_id) ':'';

        $tab = $request->tab;
        $user_id = $request['auth']['id'];
        if ($request->auth) {
            $role_name = $request['auth']['role_name']; /* return string role [root, admin, general]*/
        } else {
            $role_name = 'general';
        }

        $status = $request->status;
        // $where = $user_id && $role_name == 'general' ? ' and FIND_IN_SET(' . $user_id . ',r.approve_by_id) ' : '';
        switch ($tab) {
            case 'inbox':
                $where = isset($user_id) && strpos(' admin | general', $role_name) >= 0 ? ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ' : '';
                $where_vt_ro = 'and ro.user_id =' . $user_id;
                $where_vt_r = " AND r.status != 'draft' ";
                $where_vt_list = " AND r.requisition_status != 'draft'  ";
                break;
            case 'audit':
                $where = strpos(' admin | root', $role_name) ? '' : ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ';
                $where_vt_ro = ' ';
                $where_vt_r = " AND r.status != 'draft' ";
                $where_vt_list = " AND r.requisition_status != 'draft'  ";
                break;
            case 'draft':
                $where = strpos(' admin | root', $role_name) ? '' : ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ';
                $where_vt_ro = ' ';
                $where_vt_r = " AND r.status = 'draft' ";
                $where_vt_list = " AND r.requisition_status = 'draft'  ";
                break;
            case 'sent':
                $where = strpos(' admin | root', $role_name) ? '' : ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ';
                $where_vt_ro = ' AND ro.matrix_type_id = 142 '; /*ambil hanya dari user aproval*/
                $where_vt_ro .= ' AND ro.user_id = ' . $user_id; /*ambil hanya dari user yg melakukan action/login*/
                $where_vt_r = " AND r.status in('close','open') ";
                $where_vt_list = " AND ro.status in('approve','rejected') ";

                // $where_vt_r = " AND r.status != 'draft' ";
                // $where_vt_list = " AND r.requisition_status != 'draft'  ";
                break;
            default:
                $where = isset($user_id) ? ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ' : '';
                $where_vt_ro = 'and ro.user_id =' . $user_id;
                $where_vt_r = " AND r.status != 'draft' ";
                $where_vt_list = " AND r.requisition_status != 'draft'  ";
        }
        $results = DB::select(
            "
            /* query routing statusCount by tab */
            WITH vt_ro as
            (
                SELECT  ro.id                                                       AS routing_id
                       ,ro.object_id                                                AS requisition_id
                       ,ro.status
                       ,ro.sequence
                       ,ro.remarks
                       ,ro.requisition_number
                       ,ro.due_date
                       ,ro.matrix_type_id
                       ,ro.user_id
                       ,ROW_NUMBER() OVER (PARTITION BY object_id ORDER BY id DESC) AS rn /*order by id agar status terakhir ada di rn ke 1*/
                FROM routing ro
                WHERE 1 = 1
                AND ro.requisition_number != ''
                " . $where_vt_ro . "
                -- and ro.matrix_type_id = 142 /*ambil hanya status dari approval*/
            ),
            vt_t_day as(
                SELECT
                    t.name, t.slug
                FROM terms t
                WHERE 1 = 1
                    AND t.term_group = 4
                -- LIMIT 1
            ),
            vt_r as(
                SELECT
                    r.status                         AS requisition_status
                    ,r.id
                    ,r.code_number                   AS doc_number
                    ,r.version
                    ,concat(r.code_number,r.version) AS requisition_number
                    ,r.subject                       AS title
                FROM requisition r
                WHERE 1 = 1
                    AND (
                            r.id IN ( SELECT vt_ro.requisition_id FROM vt_ro)
                            " . $where . "
                        )
                   " . $where_vt_r . "
                   AND r.deleted_at is null
            ),
            vt_list as(
                SELECT
    --             COALESCE(r.requisition_status,ro.status)                                     AS 'key'

                                CASE   /*maping data status*/
                                        WHEN r.requisition_status = 'draft' THEN 'draft'
                                        WHEN r.requisition_status = 'close' THEN COALESCE(ro.status,r.requisition_status)
    --                                  WHEN r.requisition_status = 'open' AND ro.sequence >= 1 and ro.status!='open' THEN 'partialy Open'
                                        WHEN r.requisition_status = 'open' AND ro.sequence >= 1 and ro.status!='open' THEN 'open'
                                        WHEN ro.status = 'submit' THEN 'open'  ELSE COALESCE(ro.status,r.requisition_status) END AS 'key'

                   ,concat(UPPER( COALESCE(r.requisition_status,ro.status) ),' (',COUNT(*),') ') AS title
                   ,if(COUNT(*) > 0,true,false)                                                  AS folder
                   ,if(COUNT(*) > 0,true,false)                                                  AS lazy
                   ,JSON_ARRAYAGG(r.requisition_status)                                          as list_requisition_status
                   ,JSON_ARRAYAGG(ro.status)                                                     as list_routing_status
                   ,r.requisition_status
                   ,COUNT(*)                                                                     AS jml
                   ,JSON_ARRAYAGG(r.id)                                                          AS list_requisition_id
            -- r.*, ro.*
            FROM vt_r AS r
            left JOIN vt_ro AS ro
            ON ro.requisition_id = r.id AND ro.requisition_number = r.requisition_number
            WHERE 1 = 1
            AND (ro.rn = 1 or ro.rn is null)
            " . $where_vt_list . " /*filter bug jika status requisition draft namun sudah ada routing*/
            GROUP BY  1
            )

            select
            vt_list.key,
            concat(UPPER(  vt_list.key ) ,' (',vt_list.jml,') ') AS title,
            vt_list.folder,
            vt_list.lazy,
            vt_list.list_requisition_status,
            vt_list.list_routing_status,
            vt_list.requisition_status,
            vt_list.jml,
            vt_list.list_requisition_id

             from vt_list

           GROUP BY  1

            "
        );
        return $results;
    }

    /**
     * Fungsi untuk cek status routing berdasarkan
     * filter user_id jika rolenya general
     * untuk role admin dan root filter di tiadakan
     */
    public function statusCountDetail($request)
    {
        $list_requisition_id_json = $request->list_requisition_id;
        $list_requisition_id_array = json_decode($list_requisition_id_json);
        $list_requisition_id_string = implode(',', $list_requisition_id_array);

        $tab = $request->tab;
        // dencript_query_str($request);
        $user_id = $request['auth']['id'];
        // dencript_query_str($request);

        $status = $request->status;
        if ($request->auth) {
            $role_name = $request['auth']['role_name']; /* return string role [root, admin, general]*/
        } else {
            $role_name = 'general';
        }

        $status = $request->status;
        /*hanya filter jika user general*/
        // $where = $user_id && $role_name == 'general' ? ' and FIND_IN_SET(' . $user_id . ',r.approve_by_id) ' : '';
        // $where = $user_id && $role_name == 'general' ? ' and FIND_IN_SET(' . $user_id . ',r.approve_by_id) ' : '';

        /**
         * Cek status [open, aprove, rejected, close]
         */
        switch ($tab) {
            case 'inbox':
                $where = isset($user_id) && strpos(' admin | general', $role_name) >= 0 ? ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ' : '';
                $where_vt_ro = 'and ro.user_id =' . $user_id;
                break;
            case 'audit':
                $where = strpos(' admin | root', $role_name) ? '' : ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ';
                $where_vt_ro = '';
                break;
            default:
                $where = isset($user_id) ? ' or ( FIND_IN_SET(' . $user_id . ',r.approve_by_id) or FIND_IN_SET(' . $user_id . ',r.for_info_id) ) ' : '';
                $where_vt_ro = 'and ro.user_id =' . $user_id;
        }

        $query_text2 = "
                        /* query routing statusCountDetail by tab OLD*/
                        with
                        vt_ro as(
                            select
                            if(ro.user_id=" . $user_id . ",true,1) as can_edit, ro.user_id, ro.active, ro.sequence
                            , ro.id as routing_id, ro.object_id as requisition_id, ro.status, ro.remarks, ro.requisition_number, ro.due_date
                            , ROW_NUMBER() OVER (PARTITION BY object_id ORDER BY id DESC) AS rn /*order by id agar status terakhir ada di rn ke 1*/
                            from routing ro
                            where 1=1
                            and ro.requisition_number !=''
                            " . $where_vt_ro . "
                            -- and ro.matrix_type_id = 142 /*ambil hanya status dari approval*/
                            -- and ro.status='" . $status . "'
                        ),
                        vt_t_day as(
                            select
                                t.name, t.slug
                            from terms t
                            where 1=1
                                and t.term_group=4
                            --    limit 1
                        ),
                        vt_r as(
                            select
                                    r.status                         AS requisition_status
                                    , r.id
                                    , r.code_number                  AS doc_number
                                    , r.version                      AS rev
                                    , r.subject                      AS title
                                    ,concat(r.code_number,r.version) AS requisition_number
                            from requisition r
                            where 1=1
                                and (
                                        r.id in ( select vt_ro.requisition_id  from vt_ro )
                                        " . $where . "
                                    )
                                and r.status !='draft'
                            --    and r.status ='" . $status . "'
                        )

                        select
                            COALESCE(ro.can_edit,0) as can_edit,
                            COALESCE(ro.user_id," . $user_id . ") as user_id,
                            if(r.id=19,1,0) as active,
                            ro.sequence as sequence,
                            COALESCE(r.requisition_status,ro.status) as 'key',
                            r.id as requisition_id,
                            r.doc_number as doc_number,
                            r.doc_number as title,
                            r.title as judul,
                            r.requisition_status as requisition_status,
                            ro.routing_id as routing_id,
                            ro.status as status,
                            ro.remarks as remarks,
                            ro.requisition_number as requisition_number,
                            r.rev as rev,
                            ro.due_date as due_date,
                            '-' as company_vendor,
                            false as folder,
                            if(count(id)>1,true,false) as lazy

                        --    count(id) as jml
                        --    r.*, ro.*
                        from vt_r as r
                        left join vt_ro as ro ON ro.requisition_id = r.id AND ro.requisition_number = r.requisition_number
                        where 1=1
                              and (ro.rn = 1 or ro.rn is null) /*bug fix jika di routing tidak ada*/
                              and (ro.status='" . $status . "' or r.requisition_status='" . $status . "')
                        group by r.id
                    ";

        $query_text = "
                        /* query routing statusCountDetail by tab */
                        WITH vt_r as
                        (
                            SELECT  r.status                        AS requisition_status
                                ,r.code_number                   AS doc_number
                                ,r.subject                       AS title
                                ,r.version                       AS rev
                                ,concat(r.code_number,r.version) AS requisition_number
                                ,r.*
                            FROM requisition r
                            WHERE id in(" . $list_requisition_id_string . ")
                        ), vt_ro AS
                        (
                            SELECT  if(ro.user_id = ? ,true,0)                                      AS can_edit
                                ,ro.id                                                             AS routing_id
                                ,ro.object_id                                                      AS requisition_id
                                ,ROW_NUMBER() OVER (PARTITION BY ro.object_id ORDER BY ro.id DESC) AS rn
                                ,/*order by id agar status terakhir ada di rn ke 1*/ ro.*
                            FROM routing ro
                            WHERE 1 = 1
                            " . $where_vt_ro . "
                            and ro.object_id IN ( SELECT  vt_r.id FROM vt_r)
                            AND ro.object_tabel = 'requisition'
                            /* tidak pakai karena bisa ada banyak requisition AND ro.requisition_number = ? */
                        ), vt_rlo as(
                        SELECT  *
                        FROM route_log
                        WHERE routing_id IN ( SELECT vt_ro.id FROM vt_ro) )
                        SELECT
                            COALESCE(ro.can_edit,0)                   AS can_edit
                            ,COALESCE(ro.user_id," . $user_id . ")    AS user_id
                            ,if(r.id = 0,1,0)                         AS active  /*jika 1 maka row akan di hiligth*/
                            ,ro.sequence                              AS sequence
                            ,COALESCE(r.requisition_status,ro.status) AS 'key'
                            ,r.id                                     AS requisition_id
                            ,r.doc_number                             AS doc_number
                            ,r.doc_number                             AS title
                            ,r.title                                  AS judul
                            ,r.requisition_status                     AS requisition_status
                            ,ro.routing_id                            AS routing_id
                            ,ro.status                                AS status
                            ,ro.remarks                               AS remarks
                            ,ro.requisition_number                    AS requisition_number
                            ,r.rev                                    AS rev
                            ,ro.due_date                              AS due_date
                            ,'-'                                      AS company_vendor
                            ,false                                    AS folder
                            ,if(COUNT(r.id) > 1,true,false)           AS lazy
                        FROM vt_r AS r
                        LEFT JOIN vt_ro AS ro
                        ON ro.requisition_id = r.id AND ro.requisition_number = r.requisition_number
                        WHERE 1 = 1
                        AND (ro.rn = 1 or ro.rn is null) /*bug fix jika di routing tidak ada*/
                        -- AND (ro.status = ? or r.requisition_status = ? )
                        GROUP BY  r.id
        ";
        $results = DB::select($query_text, [$user_id]);
        return $results;
    }
    /**
     * Fungsi untuk cek status routing berdasarkan
     * filter object_id,user_id object_tabel = 'requisition' tabel routing
     * untuk role admin dan root filter di tiadakan
     */
    public function routeSlip($request)
    {
        $user = new User();
        $tabel_user = $user->getTableName();

        $requisition_id = $request->id;
        if ($request->auth) {
            $role_name = $request['auth']['role_name']; /* return string role [root, admin, general]*/
        } else {
            $role_name = 'general';
        }
        $status = $request->status;

        $requisition = Requisition::find($requisition_id);
        if (empty($requisition)) {
            abort(403, 'requisition not found');
        }
        $requisition_number = $requisition->code_number . $requisition->version;

        $user_id = $request['auth']['id'];
        /*hanya filter jika user general*/
        $where = strpos(' admin | root', $role_name) ? ' and (ro.user_id=' . $user_id . ' ) ' : ' and ro.user_id=' . $user_id;
        // $where = strpos(' admin | root', $role_name) ? ' and (ro.user_id=' . $user_id . ' OR ro.active=1) ' : ' and ro.user_id=' . $user_id;
        $query_text = "
                        /*get routslip detail*/
                        with
                        vt_ro as(
                            select
                            rl.status_resp as designee_resp
                            , ro.comment as designee_comments
                            , if(ro.user_id=" . $user_id . " and  ro.active =1 ,1,0) as can_edit, ro.user_id, ro.active, ro.sequence
                            , ro.id as routing_id, ro.object_id as requisition_id, ro.status as routing_status
                            , ro.remarks as designee_remarks
                            , ro_origin.remarks as origin_remarks
                            , ro.requisition_number
                            , ro.due_date
                            , t.term_id as matrix_type_id, t.name as matrix_type_name, t.slug as matrix_type_slug
                            , ROW_NUMBER() OVER (PARTITION BY ro.object_id ORDER BY ro.id DESC) AS rn /*order by id agar status terakhir ada di rn ke 1*/
                            ,u.name as designee_name, u.email as designee_email
                            ,CONV(SUBSTRING(CAST(SHA(ro.requisition_number) AS CHAR), 1, 16), 16, 10) as routing_number
                            from routing ro
                            left join route_log rl on rl.routing_id=ro.id
                            join routing as ro_origin on (ro_origin.object_id=ro.object_id and ro_origin.object_tabel='requisition' and ro_origin.matrix_type_id=145) /*routing khusus originator*/
                            join terms t on ro.matrix_type_id=t.term_id
                            join ".$tabel_user." u on ro.user_id=u.id
                            where 1=1
                            and ro.requisition_number !=''
                            and ro.requisition_number =?
                            " . $where . "
                            and ro.object_id=?
                            and ro.object_tabel='requisition'
                        ),
                        vt_t_day as(
                            select t.name, t.slug from terms t where t.term_group=4 limit 1
                        ),
                        vt_r as(
                            select r.id as requisition_id, u.id as originator_id, u.name as originator_name, r.code_number as doc_number, r.version as rev, r.subject as title
                            , r.project_id
                            , r.pdf_id
                            , if(r.submit_date='0000-00-00',date(r.updated_at),r.submit_date) as submit_date
                            from requisition r
                            join ".$tabel_user." u on r.originator_id=u.id
                            where 1=1
                                and r.id=?
                        )
                        ,
                        vt_p as(
                            select p.id as project_id, p.name as project_name, p.code as project_code , p.status as project_status
                            , c.id as company_id, c.name as company_name
                            from projects p
                            join company c on p.company_id=c.id
                            where 1=1
                            and p.id in (select r.project_id from vt_r r)
                        )

                        select
                        count(r.requisition_id) as jml,
                        r.*, ro.*, p.*
                        from vt_r as r
                        join vt_ro as ro on ro.requisition_id=r.requisition_id
                        join vt_p as p on r.project_id=p.project_id
                        where 1=1
                            and rn=1
                        -- and ro.status=?
                        group by r.requisition_id
        ";

        $results = DB::select($query_text, [$requisition_number, $requisition_id, $requisition_id]);

        $results = $results[0] ?? null;
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
                    $table->id(); // BigInt AUTO_INCREMENT (default = 20 digit cukup)
                    $table->unsignedBigInteger('object_id');
                    $table->unsignedBigInteger('matrix_type_id')->comment("tipe user berdasarkan rolenya\ncek dari terms.term_group=10");

                    $table->string('comment', 255)->nullable()->comment("diisi ketika approval muncul popup");
                    $table->char('active', 1)->default('0')->comment("routing aktif dalam batch requisition_id; hanya 1 aktif");
                    $table->unsignedBigInteger('notif_id')->nullable()->comment("join ke notif.id");

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->unsignedBigInteger('user_id')->comment("user yg ditunjuk sesuai rolenya");
                    $table->integer('sequence')->comment("urutan routing, 0=originator");

                    $table->enum('status', ['submit', 'open', 're-route', 'waiting', 'approve', 'rejected', 'revision', 'close', 'no_action'])
                        ->nullable()
                        ->default('open')
                        ->comment("status routing");

                    $table->string('remarks', 255)->nullable()->comment("diisi dari annotation / remark origin saat submit");
                    $table->string('object_tabel', 255)->comment("nama tabel referensi object_id");

                    $table->string('label_email', 255)->nullable();
                    $table->string('requisition_number', 255)->comment("unik dari code_number + version di requisition");

                    $table->dateTime('due_date')->default(DB::raw('CURRENT_TIMESTAMP'))->comment("batas waktu aksi");
                    $table->smallInteger('is_viewed');

                    $table->dateTime('action_date')->nullable();
                    $table->string('label_user', 150)->nullable();
                    $table->tinyInteger('version')->comment("penanda batch_id, default 0, naik saat reject/revisi");

                    $table->string('routing_number', 255)
                        ->default(DB::raw("CONV(SUBSTR(SHA(`requisition_number`), 1, 16), 16, 10)"));

                    // Unique constraint
                    $table->unique(['object_id', 'object_tabel', 'sequence', 'user_id', 'requisition_number'], 'newUniqueRequisitionNumber');

                    // Indexes
                    $table->index('user_id', 'idx_user_id');
                    $table->index('routing_number', 'idx_routing_number');
                    $table->index('object_id', 'idx_object_id');
                    $table->index('object_tabel', 'idx_object_tabel');
                    $table->index('matrix_type_id', 'idx_matrix_type_id');
                    $table->index('status', 'idx_status');
                    $table->index('sequence', 'idx_sequence');
                    $table->index('notif_id', 'idx_notif_id');
                    $table->index('label_email', 'idx_label_email');
                    $table->index('version', 'idx_version');
                });
            }
        }
    }
}
