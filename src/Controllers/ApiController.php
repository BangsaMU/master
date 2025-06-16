<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Models\Apps;
use Bangsamu\Master\Models\Brand;
use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Models\Company;
use Bangsamu\Master\Models\Department;
use Bangsamu\Master\Models\Employee;
use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\UoM;
use Bangsamu\Master\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiController extends Controller
{
    protected $LIMIT;

    public function __construct()
    {
        $this->LIMIT = 5;
    }

    public function getSelect($field, $tabel, $data_var)
    {
        extract($data_var);

        $alias_tabel = array_reduce(str_word_count("$tabel", 1), function ($res, $w) {
            return $res . $w[0];
        });

        foreach ($field as $kolom) {
            /*validasi kolom tabel*/
            if (Schema::hasColumn($tabel, $kolom)) {
                $select[] = "$alias_tabel.$kolom";
            }
            /*validasi kolom tabel join*/
            // if (is_array($join)) {
            //     foreach ($join as $tabel_join => $id_join) {
            //         $tabel_join_schema = explode('.', $tabel_join);
            //         if (Schema::hasColumn($tabel_join_schema[0], $kolom)) {
            //             $select[] = "$alias_tabel_join.$kolom";
            //         }
            //     }
            // }
            // dd($kolom, $select,$join,$tabel_join,$tabel_join_schema);
        }

        return $select;
    }

    /**
     * @param id = primary key filter by id
     * @param set[text] = return replace text dari filed apa
     * @param set[field][] = array fild yg akan direturn custom
     * @param set[field]=* retrun semua filed
     * @param start= set awal pencarian record jika tidak ada 0
     * @param limit= set maksimal record yang ditampilkan jika tidak ada 10
     * @param search= data string, akan melakukan pencarian di semua kolom (filed id dihiraukan) sesuai yg di select dari param set[field] dengan where like
     * @param search[key]=val data array, akan melakukan pencarian di filed search[key] dengan orwhere =
     */
    public function getTabelByParams(Request $request, $tabel)
    {
        // contoh order by
        // http://clay.localhost:8080/api/getruning_numberbyparams?search[type_id]=47&set[text]=format_number&ap_token=f14c26fea1ba2c8df188619c4317d658&_token=KbbEthhaqxBxUkOWN1HXnCAslkQWDsXqjFAcgQnk&order[column]=format_number&order[direction]=asc&debug=1
        // contoh debug
        // http://clay.test:8181/api/getrequisition_typebyparams?_token=YPjlenwfUbYytBCsjm2fe1mIeoi8ZOHMCXm7KDPk&debug=1
        // contoh query where dan search
        // http://clay.test:8181/api/gettermsbyparams?_token=YPjlenwfUbYytBCsjm2fe1mIeoi8ZOHMCXm7KDPk&debug=1&set[id]=term_id&where[term_group]=1&search[name][]=q&set[field][]=slug&set[text]=name
        // contoh filter or dan text resul concat http://clay.test:8181/api/getmaster_projectbyparams?set[fieldx][]=project_name&set[text]=project_code&set[text][|]=id&set[text][-]=project_code&set[text][]=project_name&ap_token=ae8f35052e0f8e687387a661ce40cc9b&_token=YPjlenwfUbYytBCsjm2fe1mIeoi8ZOHMCXm7KDPk&search[project_name]=200&search[project_code][|]=200
        // contoh fin in set  mcu-meindo.localhost/api/getmcu_packagebyparams?set[text]=name&limit=1&find_in_set[project_id]=2
        // (string/integer data array) find_in_set[project_code][]=21317&find_in_set[project_code][]=21318 //list berupa array akan di loop cari di project_code exact strng / integer return 2 row jika ada
        // (string pemisah koma) find_in_set[project_code]=21317,21318 // jika pemisah koma akan dicari semua list tersebut di project_code akan return 2 row jika ada
        // (integer)find_in_set[project_code]=21317 //jika project_code berisi data dengan pemisah koma (20000,A21317) akan true
        // contoh select2 http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?set[text]=description&search[type]=vehicle&search[description]=PJP
        // contoh search http://meindo-teliti.test:8181/api/gethse_indicator_detailbyparams?&set[field][]=type&limit=13&start=0&search[type]=vehicle&search[indicator_method_id]=4&search[type]=samu
        // contoh multi saerch http://mcu-meindo.localhost/api/getmaster_projectbyparams?search[project_code]=21316&search[project_code]=21305&set[field][]=project_code&set[field][]=project_name&set[text]=project_code&set[text][|]=project_code&set[text][]=project_name&ap_token=1fa4a34a49944698769737edf2812b23&_token=ee3Hd8XalXieXGbLtJbChgiYwDax5HkDjkDEwpPR
        // contoh by id http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?id=45&set[text]=type&set[field][]=description&set[field][]=id
        // contoh get list http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?set[text]=type&set[field][]=description&set[field][]=id
        // contoh join tabel http://clay.localhost/api/getmaster_item_codebyparams?set[field][]=uom_name&set[field][]=item_name&set[field][]=pca_name&join[master_uom.id]=uom_id&join[master_pca.id]=pca_id

        /*Param serch support array dan string data yg diambil param terakhir */
        $search = $request->search;
        $alias_tabel = array_reduce(str_word_count("$tabel", 1), function ($res, $w) {
            return $res . $w[0];
        });
        // FIND_IN_SET(master_project.id, mcu_package.project_id)
        $find_in_set = $request->input("find_in_set");
        $where = $request->input("where");
        $set = $request->input("set");
        $join = $request->input("join");
        $start = $request->input("start", 0);
        $limit = $request->input("limit", 10);
        $order = $request->input("order");
        // dd($order);
        // dd($set,$start,$limit);
        // $field = $request->set['field'];
        $id = $request->id;
        // dd($field);
        $data_lokal = DB::table($tabel . " as " . $alias_tabel);
        $key = md5($id . ':' . config('SsoConfig.main.KEY'));
        // $token = $key == $request->input('api_token');
        $token = true; //bypass test

        if (is_array($set)) {
            $change_id = @$set['id'];
            $text = @$set['text'];
            $field = @$set['field'];
        }
        if (isset($change_id)) {
            if (Schema::hasColumn($tabel, $change_id)) {
                $select[] = $alias_tabel . "." . $change_id . " AS id";
            } else {
                abort(403, 'set id not valid');
            }
        } else {
            $select[] = $alias_tabel . ".id AS id";
        }

        $data_array = $data_lokal;

        // dd($data_array);
        if (isset($join)) {
            // http://clay.test:8181/api/getmaster_item_codebyparams?set[field][]=uom_name&set[field][]=item_name&set[field][]=pca_name&join[master_uom.id]=uom_id&join[master_pca.id]=pca_id
            // dd($join);
            // $join_tabel=1;
            foreach ($join as $tabel_join => $id_join) {
                $tabel_join_schema = explode('.', $tabel_join); //extrak join tabel dan field
                // array:2 [▼ // packages\bangsamu\master\src\Controllers\ApiController.php:113
                //     0 => "master_uom"
                //     1 => "id"
                // ]
                $alias_tabel_join = array_reduce(str_word_count("$tabel_join_schema[0]", 1), function ($res, $w) {
                    return $res . $w[0];
                });
                // dd($alias_tabel_join,$tabel_join_schema,$tabel_join,$id_join);
                $join_tabel[] = [
                    'tabel' => $tabel_join_schema[0],
                    'tabel_join' => "$alias_tabel_join.$tabel_join_schema[1]",
                    'tabel_join_id' => $tabel_join_schema[1],
                    'tabel_join_alias' => $alias_tabel_join,
                    'tabel_join_reff' => $id_join,
                ];
            }

            // dd($join_tabel,$alias_tabel_join,$tabel_join_schema,$tabel_join,$id_join);
            // dd($join);

            if (is_array(@$join_tabel)) {
                // dd($field);
                // dd($join_tabel);
                // array:2 [▼ // packages\bangsamu\master\src\Controllers\ApiController.php:208
                //     0 => array:5 [▼
                //         "tabel" => "master_uom"
                //         "tabel_join" => "mu.id"
                //         "tabel_join_id" => "id"
                //         "tabel_join_alias" => "mu"
                //         "tabel_join_reff" => "uom_id"
                //      ]
                foreach ($join_tabel as $key_index => $join_val) {
                    // $pfield_join[]='uom_name';
                    $ptabel_join = $join_val['tabel'];
                    // $alias_tabel = $join_val['tabel_join_alias'];
                    $select = self::getSelect($field, $ptabel_join, compact('select'));
                    $data_array = $data_array->join($join_val['tabel'] . ' as ' . $join_val['tabel_join_alias'], $join_val['tabel_join'], $join_val['tabel_join_reff']);
                    // $data_array = $data_array->join($tabel_join_schema[0] . ' as ' . $alias_tabel_join, "$alias_tabel_join.$tabel_join_schema[1]", $id_join);
                }
            }
        }

        if (isset($field)) {
            // $text = @$set['text'];
            // $field = @$set['field'];

            if ($field == '*') {
                // dd($field);
                $select[] = "$tabel.*";
            } else {
                // dd($field);
                if (is_array($field)) {
                    $select = self::getSelect($field, $tabel, compact('select'));
                }
            }
        }

        if (isset($set)) {
            if ($set) {
                $selectRaw = '';
                /*validasi kolom tabel*/
                if (is_array($text)) {
                    $text_concat = 'concat(';
                    foreach ($text as $sparator => $field_text) {
                        $sparator = $sparator == '0' ? '' : $sparator;
                        if (Schema::hasColumn($tabel, $field_text)) {
                            $sparator = $sparator !== '' ? ',\'' . $sparator . '\',' : '';
                            $text_concat .= $alias_tabel . '.' . $field_text . $sparator;
                            // select concat(`mp`.`project_code`,'|',`mp`.`project_name`) as `text` from `master_project` as `mp`
                        }
                    }

                    $text_concat .= ')';

                    //validasi concat tidak kosong
                    if ($text_concat !== 'concat()') {
                        $selectRaw = $text_concat . " AS text";
                    }
                    // dd($text_concat);
                    // dd($selectRaw,$text_concat, $text);
                } else {
                    if (Schema::hasColumn($tabel, $text)) {
                        $select[] = "$text AS text";
                    }
                }
                /*validasi kolom tabel id*/
                // if (Schema::hasColumn($tabel, $change_id)) {
                //     $select[] = "$change_id AS id";
                // }
            }
        }

        $tabel_exist = Schema::hasTable($tabel);
        // dd($token ,$tabel_exist);
        if ($token && $tabel_exist) {
            $select_text = implode(',', $select);
            $list_select = !empty($selectRaw) ? $selectRaw . ',' . $select_text : $select_text;
            // $select=DB::raw($selectRaw);
            // $data_array = $data_array->select($select);
            $data_array = $data_array->select(DB::raw($list_select));

            if (empty($request->deleted_at)) {
                // buang delete_at
                if (Schema::hasColumn($tabel, 'deleted_at')) {
                    $data_array = $data_array->whereNull($alias_tabel . '.deleted_at');
                }
            }

            // dd( $select,$data_array);
            if ($id) {
                // $data_array = $data_array->where($alias_tabel . '.id', $id);
                $data_array = $data_array->having('id', $id);
                // $data_array = $data_array->find($id);
            } else {
                /*buang array by value*/
                $del_val = 'id';
                if (($key = array_search($del_val, $select)) !== false) {
                    unset($select[$key]);
                }
                // dd($search,$field,$select);
                // foreach ($select as $filter_kolom_as) {

                if (@$field != '*') {
                    // $extrac_kolom = explode(' AS ', $filter_kolom_as);
                    // dd($search,$select, $extrac_kolom);
                    // $filter_kolom = $extrac_kolom[0];
                    // if (is_array($search)) {
                    //     $data_array->where(function ($query) use ($search, $tabel) {

                    //         foreach ($search as $keyFiled => $searchVal) {

                    //             if (Schema::hasColumn($tabel, $keyFiled)) {

                    //                 //cek where or atau and
                    //                 if (is_array($searchVal)) {
                    //                     //cek jika int gunakan where selian itu like
                    //                     if (is_numeric(array_values($searchVal)[0])) {
                    //                         $query->oRwhere($keyFiled, array_values($searchVal)[0]);
                    //                     } else {
                    //                         $query->oRwhere($keyFiled, 'like', '%' . array_values($searchVal)[0] . '%');
                    //                     }
                    //                 } else {
                    //                     if (is_numeric($searchVal)) {
                    //                         $query->where($keyFiled, $searchVal);
                    //                     } else {
                    //                         $query->where($keyFiled, 'like', '%' . $searchVal . '%');
                    //                     }
                    //                 }

                    //                 // // dd($keyFiled, $select,!in_array($keyFiled, $select));
                    //                 // if (!in_array($keyFiled, $select)) {
                    //                 //     // if (in_array($keyFiled, $select) and $filter_kolom != $keyFiled) {
                    //                 //     //cek where or atau and
                    //                 //     if (is_array($searchVal)) {
                    //                 //         $query->oRwhere($keyFiled, 'like', '%' . array_values($searchVal)[0] . '%');
                    //                 //     } else {
                    //                 //         $query->where($keyFiled, 'like', '%' . $searchVal . '%');
                    //                 //     }
                    //                 // } else {
                    //                 //     $query->where($filter_kolom, '=', $searchVal);
                    //                 // }
                    //             }
                    //         }
                    //     });
                    // } elseif ($search) {
                    //     $data_array->where($filter_kolom, 'like', '%' . $search . '%');
                    // }

                    if (is_array($search)) {

                        $data_array = $data_array->where(function ($query) use ($search, $tabel) {

                            foreach ($search as $searchKey => $searchVal) {
                                /*validasi kolom pencarian di tabel*/
                                if (Schema::hasColumn($tabel, $searchKey)) {
                                    //cek where or atau and
                                    if (is_array($searchVal)) {
                                        //cek jika int gunakan where selian itu like
                                        // if (is_numeric(array_values($searchVal)[0])) {
                                        //     $query->oRwhere($searchKey, array_values($searchVal)[0]);
                                        // } else {
                                        foreach ($searchVal as $searchValdata) {
                                            // dd('arrayMulti',$search,$searchVal,$searchValdata,$searchValdata);
                                            $query->oRwhere($searchKey, 'like', '%' . $searchValdata . '%');
                                        }
                                        // }
                                    } else {
                                        // if (is_numeric($searchVal)) {
                                        //     $query->where($searchKey, $searchVal);
                                        // } else {
                                        // dd('arraySingle',$search,$searchVal);
                                        $query->where($searchKey, 'like', '%' . $searchVal . '%');
                                        // }
                                    }
                                    // $query->where($whereKey, $whereVal);
                                    // dd($where, $whereKey, $whereVal);
                                }
                            }
                        });
                    } elseif (is_string($search)) {
                        $data_array = $data_array->where($search, 'like', '%' . $search . '%');
                    } elseif (is_numeric($search)) {
                        $data_array = $data_array->where($search, $search);
                    }
                }
                // }
                if (is_array($where)) {

                    $data_array = $data_array->where(function ($query) use ($where, $tabel) {

                        foreach ($where as $whereKey => $whereVal) {
                            /*validasi kolom pencarian di tabel*/
                            if (Schema::hasColumn($tabel, $whereKey)) {
                                //cek where or atau and
                                if (is_array($whereVal)) {
                                    //cek jika int gunakan where selian itu like
                                    if (is_numeric(array_values($whereVal)[0])) {
                                        $query->oRwhere($whereKey, array_values($whereVal)[0]);
                                    } else {
                                        $query->oRwhere($whereKey, 'like', '%' . array_values($whereVal)[0] . '%');
                                    }
                                } else {
                                    if (is_numeric($whereVal)) {
                                        $query->where($whereKey, $whereVal);
                                    } else {
                                        $query->where($whereKey, 'like', '%' . $whereVal . '%');
                                    }
                                }
                                // $query->where($whereKey, $whereVal);
                                // dd($where, $whereKey, $whereVal);
                            }
                        }
                    });
                }
                if (is_array($find_in_set)) {
                    $data_array = $data_array->where(function ($query) use ($find_in_set, $tabel) {

                        foreach ($find_in_set as $findKey => $findVal) {
                            // dd($tabel,$findKey);
                            /*validasi kolom pencarian di tabel*/
                            if (Schema::hasColumn($tabel, $findKey)) {
                                //cek where or atau and
                                //    dd(1,$findVal);
                                //    dd(array_values($findVal),array_values($findVal)[0],is_numeric(array_values($findVal)[0]),is_array($findVal),$findVal);
                                // find_in_set[project_code][]=21317&find_in_set[project_code][]=21318
                                if (is_array($findVal)) {
                                    // dd(1,'array');
                                    $query->whereIn($findKey, $findVal);
                                } else {
                                    // find_in_set[project_remarks]=21317
                                    // dd(2,is_numeric($findVal));
                                    if (is_numeric($findVal)) {
                                        // dd(2,'numeric');
                                        $query->orWhereRaw("FIND_IN_SET(?,$findKey)", [$findVal]);
                                    } else {
                                        // find_in_set[project_code]=A21317,21318
                                        // dd(3,'list string by koma');
                                        $query->whereRaw("FIND_IN_SET($findKey,?)", [$findVal]);
                                        //    $query->orWhereRaw('FIND_IN_SET('.$findVal.','.$findKey.')');
                                    }
                                }
                                //    dd($query->toSql());
                            }
                        }
                    });
                }
            }

            if ($order) {
                $data_array->orderby($order['column'], ($order['direction'] ?? 'asc'));
            }


            $builder = $data_array->offset($start)->limit($limit);
            $data_array = $builder->get()->toArray();
            $respon = $data_array;

            if ($request->debug) {
                $query_debug = str_replace(array('?'), array('\'%s\''), $builder->toSql());
                $query_debug = vsprintf($query_debug, $builder->getBindings());
                $respon['query'] = $query_debug;
            }
            // dd($query_sql, $start, $limit, $respon);
            if (empty($respon)) {
                $respon = ['Data Not Found'];
            }
        } else {
            $respon = ['Data Not Found'];
        }

        return response()->json($respon);
    }

    public function getCompanyByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $company = Company::orderBy('id', 'desc')->select('id', 'company_code', 'company_name')->limit($this->LIMIT)->get();
        } else {
            $company = Company::orderBy('id', 'desc')->select('id', 'company_code', 'company_name')->where('company_code', 'like', '%' . $search . '%')->orwhere('company_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($company as $item) {
            $response[] = array(
                "id" => $item->id,
                "text" => $item->company_code . ' - ' . $item->company_name,
            );
        }

        return response()->json($response);
    }

    public function getItemCodeByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $item_codes = ItemCode::orderBy('id', 'desc')->select('id', 'item_code')->limit($this->LIMIT)->get();
        } else {
            $item_codes = ItemCode::orderBy('id', 'desc')->select('id', 'item_code')->where('item_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($item_codes as $item) {
            $response[] = array(
                "id" => $item->id,
                "text" => $item->item_code,
            );
        }

        return response()->json($response);
    }

    public function getAppsByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $apps = Apps::orderBy('id', 'asc')->select('id', 'app_code', 'name')->limit($this->LIMIT)->get();
        } else {
            $apps = Apps::orderBy('id', 'asc')->select('id', 'app_code', 'name')->orwhere('name', 'like', '%' . $search . '%')->orwhere('app_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($apps as $app) {
            $app_name = $app->name ? ': ' . $app->name : '';
            $response[] = array(
                "id" => $app->id,
                "code" => $app->app_code,
                "text" => $app->app_code . $app_name,
            );
        }

        return response()->json($response);
    }

    public function getUomByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $uoms = UoM::orderBy('id', 'desc')->select('id', 'uom_code', 'uom_name')->limit($this->LIMIT)->get();
        } else {
            $uoms = UoM::orderBy('id', 'desc')->select('id', 'uom_code', 'uom_name')->orwhere('uom_code', 'like', '%' . $search . '%')->orwhere('uom_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($uoms as $uom) {
            $uom_name = $uom->uom_name ? ': ' . $uom->uom_name : '';
            $response[] = array(
                "id" => $uom->id,
                "code" => $uom->uom_code,
                "text" => $uom->uom_code . $uom_name,
            );
        }

        return response()->json($response);
    }

    public function getPcaByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $pcas = Pca::orderBy('id', 'desc')->select('id', 'pca_code')->limit($this->LIMIT)->get();
        } else {
            $pcas = Pca::orderBy('id', 'desc')->select('id', 'pca_code')->where('pca_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($pcas as $pca) {
            $response[] = array(
                "id" => $pca->id,
                "text" => $pca->pca_code,
            );
        }

        return response()->json($response);
    }

    public function getCategoryByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $categories = Category::orderBy('id', 'desc')->select('id', 'category_name')->limit($this->LIMIT)->get();
        } else {
            $categories = Category::orderBy('id', 'desc')->select('id', 'category_name')->where('category_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($categories as $category) {
            $response[] = array(
                "id" => $category->id,
                "text" => $category->category_name,
            );
        }

        return response()->json($response);
    }

    public function getItemGroupByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $itemgroups = ItemGroup::orderBy('id', 'desc')->select('id', 'item_group_code')->limit($this->LIMIT)->get();
        } else {
            $itemgroups = ItemGroup::orderBy('id', 'desc')->select('id', 'item_group_code')->where('item_group_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($itemgroups as $item_group) {
            $response[] = array(
                "id" => $item_group->id,
                "text" => $item_group->item_group_code,
            );
        }

        return response()->json($response);
    }

    public function getProjectByParams(Request $request)
    {
        $search = $request->search;
        if ($search) {
            $search = strip_tags($search);
            $search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8', false);
        }

        if ($search == '') {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->limit($this->LIMIT)->get();
        } else {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->orwhere('project_name', 'like', '%' . $search . '%')->orwhere('project_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($projects as $project) {
            $response[] = array(
                "id" => $project->id,
                "text" => $project->project_code . ' - ' . $project->project_name,
            );
        }

        return response()->json($response);
    }

    public function getDepartmentByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $departments = Department::orderBy('id', 'desc')->select('id', 'department_code')->limit($this->LIMIT)->get();
        } else {
            $departments = Department::orderBy('id', 'desc')->select('id', 'department_code')->where('department_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($departments as $department) {
            $response[] = array(
                "id" => $department->id,
                "text" => $department->department_code,
            );
        }

        return response()->json($response);
    }

    public function getLocationByParams(Request $request)
    {
        $search = $request->search;
        $group_type = $request->group_type ?? 'office';

        $locations = Location::select('id', 'loc_code', 'loc_name');
        $locations->where(function ($q) use ($group_type) {
            $q->where('group_type', '=', $group_type);
        });

        if ($search) {
            $locations->where(function ($q) use ($search) {
                $q->orwhere('loc_code', 'like', '%' . $search . '%')->orwhere('loc_name', 'like', '%' . $search . '%');
            });
        }

        $locations = $locations->orderBy('id', 'desc')->limit($this->LIMIT)->get();

        $response = array();
        foreach ($locations as $location) {
            $response[] = array(
                "id" => $location->id,
                "text" => ($location->loc_code ? $location->loc_code . ' - ' : '') . $location->loc_name,
            );
        }

        return response()->json($response);
    }

    public function getEmployeeByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name', 'employee_job_title')->limit($this->LIMIT)->get();
        } else {
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name', 'employee_job_title')->where('employee_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($employees as $employee) {
            $response[] = array(
                "id" => $employee->id,
                "text" => $employee->employee_name,
                "position" => $employee->employee_job_title,
            );
        }

        return response()->json($response);
    }

    public function getBrandByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $brands = Brand::orderBy('id', 'desc')->select('id', 'brand_code')->limit($this->LIMIT)->get();
        } else {
            $brands = Brand::orderBy('id', 'desc')->select('id', 'brand_code')->where('brand_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($brands as $brand) {
            $response[] = array(
                "id" => $brand->id,
                "text" => $brand->brand_code,
            );
        }

        return response()->json($response);
    }

    public function getVendorByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $vendors = Vendor::orderBy('id', 'desc')->select('id', 'vendor_code')->limit($this->LIMIT)->get();
        } else {
            $vendors = Vendor::orderBy('id', 'desc')->select('id', 'vendor_code')->where('vendor_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($vendors as $vendor) {
            $response[] = array(
                "id" => $vendor->id,
                "text" => $vendor->vendor_code,
            );
        }

        return response()->json($response);
    }
}
