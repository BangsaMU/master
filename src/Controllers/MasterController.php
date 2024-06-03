<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Validator;
use Response;
use Illuminate\Support\Str;
use App\Models\Telegram;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MasterController extends Controller
{

    /**
     * The prefix to use for register/load the package resources.
     *
     * @var string
     */
    protected $pkgPrefix = 'master';

    function __construct($CHAT_ID = null, $param = null)
    {
    }

    function tabel(Request $request, $tabel, $id = null)
    {
        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.' . $tabel . '.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.' . $tabel . '.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.' . $tabel . '.MODEL', Str::studly($tabel));
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.' . $tabel . '.MODEL', 'Master' . Str::studly($tabel));

        // dd($key_lokal,$tabel_lokal, $key_master, $tabel_master);
        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);

        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $list_id = $data_sync_lokal->pluck('id')->toArray();
        $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        if ($jml_master) {
            $data_array = $data_sync_lokal->toArray();
            $master_array = $data_sync_master->toArray();

            /*convert array lokal index ke key dengan id */
            foreach ($data_array as  $key => $val) {
                $data_array_map_lokal[$val['id']] = $val;
            }

            /*convert array master index ke key dengan id */
            // dd(compact('data_array','master_array', 'key_master', 'key_lokal'));
            $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));

            if ($sync_update) {
                $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
            }

            /*cek data master berdasarkan id yang akan di insert*/
            if ($sync_insert) {
                $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
            }

            $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));
        } else {
            $respon['data']['message'] = 'Data Not Found';
            $respon['data']['sync'] = @$data_array_map_master;
        }

        return LibraryClayController::setOutput($respon);
    }

    function getTabel(Request $request, $tabel, $id = null)
    {
        $data_sync_lokal =  DB::table($tabel);
        $key = md5($id . ':' . config('SsoConfig.main.KEY'));
        $token = $key == $request->input('api_token');
        // dd($data_sync_lokal,$key);
        if ($token) {
            $data_array = $data_sync_lokal->where('id', $id)->get()->toArray();
            $respon['data'] = $data_array;
        } else {
            $respon['data']['message'] = 'Data Not Found';
        }

        return LibraryClayController::setOutput($respon);
    }

    function getMaster(Request $request, $tabel, $id = null)
    {
        $data_sync_lokal =  'Bangsamu\Master\Models\\' . $tabel;
        $key = md5($id . ':' . config('SsoConfig.main.KEY'));
        $token = $key == $request->input('api_token');
        // dd($key);
        if ($token) {
            $data_array = $data_sync_lokal::where('id', $id)->get()->toArray();
            $respon['data'] = $data_array;
        } else {
            $respon['data']['message'] = 'Data Not Found';
        }

        return LibraryClayController::setOutput($respon);
    }

    /**
     * fungsi synt baster datat berdasarkan param dari json
     */
    function syncTabel(Request $request, $tabel, $id = null)
    {
        $data = $request->all();
        $tabel = empty($tabel) ? @$data['tabel'] : $tabel;
        $id = empty($id) ? @$data['rows'][0]['id'] : $id;
        $validate_tabel = @$data['tabel'] ? $data['tabel'] :  $tabel;
        $validate_tabel = @$data['tabel'] == $tabel;
        $master_tabel = config(ucfirst($this->pkgPrefix) . 'Config.MASTER_TABEL');
        $validate_master = in_array($tabel, $master_tabel);
        $validate_rows = is_array(@$data['rows'][0]);
        $validate_id = @$data['rows'][0]['id'] == $id || !empty($id) ? true : false;
        $data_master = @$data['rows'];
        // dd($data, $validate_master, $validate_tabel, $validate_rows, $validate_id);


        if (!$validate_master || !$validate_tabel || !$validate_rows || !$validate_id) {

            if($validate_master==false){
                $message = 'tabel `'.$tabel.'` tidak masuk di list MASTER_TABEL';
            }
            $respond['status'] = 'gagal';
            $respond['code'] = 400;
            $respond['data'] = 'Data not valid ' . (int)$validate_master . '-' . (int)$validate_tabel . '-' . (int)$validate_rows . '-' . (int)$validate_id;
            $respond['message'] = $message;

            Response::make(LibraryClayController::setOutput($respond))->send();
            exit();
        }

        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.' . $tabel . '.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.' . $tabel . '.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.' . $tabel . '.MODEL')?? $tabel;
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.' . $tabel . '.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master', 'data_master'));
        extract($getDataSync);

        $jml = $data_sync_lokal->count();
        $jml_master = count($data_master);/*ambil dari param json data*/

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_master;/*ambil dari param json data*/

        $list_id  = array_column($data_array, 'id');
        $list_id_master  = array_column($master_array, 'id');

        if ($jml < $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/

        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }


        /*convert array master index ke key dengan id */
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));
        // dd($master_array, $key_master, $key_lokal, 1, $data, $tabel, $validate_master, $validate_tabel, $validate_rows, $validate_id);

        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    function uom(Request $request, $id = null)
    {
        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.uom.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.uom.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.uom.MODEL');
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.uom.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);

        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $list_id = $data_sync_lokal->pluck('id')->toArray();
        $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_sync_master->toArray();


        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }

        /*convert array master index ke key dengan id */
        // dd(compact('master_array', 'key_master', 'key_lokal'));
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));

        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    function itemCode(Request $request, $id = null)
    {
        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.item_code.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.item_code.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.item_code.MODEL');
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.item_code.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);

        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $list_id = $data_sync_lokal->pluck('id')->toArray();
        $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_sync_master->toArray();


        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }

        /*convert array master index ke key dengan id */
        // dd(compact('master_array', 'key_master', 'key_lokal'));
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));

        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    function location(Request $request, $id = null)
    {

        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.location.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.location.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.location.MODEL');
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.location.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);


        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_sync_master->toArray();
        $list_id  = array_column($data_array, 'id');
        $list_id_master  = array_column($master_array, 'id');
        // dd($list_id, array_column($data_array,'id'));
        // $list_id = $data_sync_lokal->pluck('id')->toArray();
        // $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }

        /*convert array master index ke key dengan id */
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));

        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    function project(Request $request, $id = null)
    {
        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.project.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.project.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.project.MODEL');
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.project.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);
        // dd($getDataSync);
        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $list_id = $data_sync_lokal->pluck('id')->toArray();
        $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_sync_master->toArray();


        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }

        /*convert array master index ke key dengan id */
        // dd(compact('master_array', 'key_master', 'key_lokal'));
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));

        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    function employee(Request $request, $id = null)
    {
        $log = [];
        $sync_insert = []; /*list insert array id*/
        $sync_update = []; /*list update array id*/

        $key_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.employee.FIELD');
        $key_master = config(ucfirst($this->pkgPrefix) . 'Config.master.employee.FIELD');

        $tabel_lokal = config(ucfirst($this->pkgPrefix) . 'Config.lokal.employee.MODEL');
        $tabel_master = config(ucfirst($this->pkgPrefix) . 'Config.master.employee.MODEL');

        $getDataSync = LibraryClayController::getDataSync(compact('id', 'tabel_lokal', 'tabel_master'));
        extract($getDataSync);

        $jml = $data_sync_lokal->count();
        $jml_master = $data_sync_master->count();

        /*cek jumlah data master dan lokal jika beda maka insert*/
        $list_id = $data_sync_lokal->pluck('id')->toArray();
        $list_id_master = $data_sync_master->pluck('id')->toArray();
        if ($jml != $jml_master) {
            /*cek list id master dan lokal*/
            $list_id_diff = array_diff($list_id_master, $list_id);
            $sync_insert = $list_id_diff;/*list id yang akan di insert*/
            // dd($list_id_master, $list_id, $list_id_diff);
        };

        $list_id_same = array_intersect($list_id_master, $list_id);
        $sync_update = $list_id_same;/*list id yang akan di update*/
        // dd($list_id_same);

        $data_array = $data_sync_lokal->toArray();
        $master_array = $data_sync_master->toArray();


        /*convert array lokal index ke key dengan id */
        foreach ($data_array as  $key => $val) {
            $data_array_map_lokal[$val['id']] = $val;
        }

        /*convert array master index ke key dengan id */
        $data_array_map_master = LibraryClayController::arrayIndexToId(compact('master_array', 'key_master', 'key_lokal'));


        if ($sync_update) {
            $log = LibraryClayController::sync_update(compact('sync_update', 'tabel_lokal', 'data_array_map_master', 'data_array_map_lokal', 'log'));
        }

        /*cek data master berdasarkan id yang akan di insert*/
        if ($sync_insert) {
            $log = LibraryClayController::sync_insert(compact('sync_insert', 'tabel_lokal', 'data_array_map_master', 'log'));
        }

        $respon = LibraryClayController::syncLog(compact('log', 'data_array_map_master'));

        return LibraryClayController::setOutput($respon);
    }

    public function saveDB($data)
    {

        if (isset($data->ok)) {
            $result = $data->result;

            if (isset($result->message_id)) {
                $created['message_id'] = @$result->message_id;
            }
            if (isset($result->from)) {
                $created['from_id'] = @$result->from->id;
                $created['from_is_bot'] = @$result->from->is_bot;
                $created['from_first_name'] = @$result->from->first_name;
                $created['from_username'] = @$result->from->username;
            }
            if (isset($result->chat)) {
                $created['chat_id'] = @$result->chat->id;
                $created['chat_first_name'] = @$result->chat->first_name;
                $created['chat_username'] = @$result->chat->username;
                $created['chat_type'] = @$result->chat->type;
            }
            if (isset($result->date)) {
                $created['date'] = @$result->date;
            }
            if (isset($result->caption)) {
                $created['caption'] = @$result->caption;
            }
            if (isset($result->text)) {
                $created['text'] = @$result->text;
            }
            if (isset($result->document)) {
                $created['document'] = json_encode(@$result->document);
            }
            if (isset($result->entities)) {
                $created['entities'] = json_encode(@$result->entities);
            }
            if (isset($result->photo)) {
                $created['photo'] = json_encode(@$result->photo);
            }
            $created['raw'] = json_encode($data);
            $telegram_db = Telegram::create($created);
        } else {
            $telegram_db = false;
        };
        // dd($telegram_db, isset($data->ok), $data);
        return $telegram_db;
    }
}
