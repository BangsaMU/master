<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Employee as HrdKaryawan;
use Bangsamu\Master\Models\MasterIncrement as HrdIncrement;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class EmployeeImport implements ToCollection
{
    private $error = [];
    private $success = [];

    // protected $request;

    // function __construct($request) {
    //         $this->request = $request;
    // }

    public function collection(Collection $rows)
    {
        //check format data
        if(
            strtoupper(@$rows[0][0]) == 'NAMA' &&
            strtoupper(@$rows[0][1]) == 'EMAIL PERSONAL' &&
            strtoupper(@$rows[0][2]) == 'EMAIL CORPORATE' &&
            strtoupper(@$rows[0][3]) == 'JABATAN' &&
            strtoupper(@$rows[0][4]) == 'EMPLOYEE DEPARTMENT' &&
            strtoupper(@$rows[0][5]) == 'NO ID KARYAWAN' &&
            strtoupper(@$rows[0][6]) == 'NO KTP' &&
            strtoupper(@$rows[0][7]) == 'HIRE' &&
            strtoupper(@$rows[0][8]) == 'STATUS' &&
            strtoupper(@$rows[0][9]) == 'TANGGAL JOIN' &&
            strtoupper(@$rows[0][10]) == 'TANGGAL AKHIR KONTRAK' &&
            strtoupper(@$rows[0][11]) == 'TANGGAL AKHIR KERJA' &&
            strtoupper(@$rows[0][12]) == 'EMPLOYEE BLOODTYPE' &&
            strtoupper(@$rows[0][13]) == 'EMPLOYEE PROJECT' &&
            strtoupper(@$rows[0][14]) == 'KETERANGAN'
        ){
            $list_status = DB::connection('db_master')->table('master_status')->get();
            $list_hire = DB::connection('db_master')->table('master_location')->where('group_type', 'hrd')->get();

            foreach ($rows as $key => $row) {
                //skip header row
                if($key > 0){

                    $data['key'] = $key;
                    $data['employee_name'] = $row[0];
                    $data['employee_email'] = empty($row[1]) ? null : $row[1];
                    $data['corporate_email'] = empty($row[2]) ? null : $row[2];
                    $data['employee_job_title'] = $row[3];
                    $data['employee_department'] = $row[4];
                    $data['no_id_karyawan'] = $row[5];
                    $data['no_ktp'] = trim($row[6]);
                    $hire_location = $row[7];
                    $status = $row[8];
                    $data['tanggal_join'] = $row[9];
                    $data['tanggal_akhir_kontrak'] = $row[10];
                    $data['tanggal_akhir_kerja'] = $row[11];
                    $data['employee_blood_type'] = $row[12];
                    $data['employee_project'] = $row[13];
                    $data['keterangan'] = $row[14];

                    //validate nama
                    if(empty($data['employee_name'])){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom nama yang kosong';
                        continue;
                    }else if(strlen($data['employee_name']) > 150){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki nama lebih dari 150 karakter';
                        continue;
                    }else{
                        $data['employee_name'] = strtoupper($data['employee_name']);
                    }

                    //validate posisi (jabatan)
                    if(empty($data['employee_job_title'])){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom posisi yang kosong';
                        continue;
                    }else{
                        $data['employee_job_title'] = strtoupper($data['employee_job_title']);
                    }

                    //validate hire
                    if($hire_location){
                        //get hire data
                        $hireData = self::getDataByColumn($list_hire, $hire_location, 'loc_name');

                        //cek nama lokasi sesuai
                        if(!$hireData){
                            $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki hire lokasi yang salah';
                            continue;
                        }else{
                            $data['hire_id'] = $hireData->id;
                            $data['hire_kode'] = $hireData->loc_code;
                        }
                    }

                    //validate status
                    if(empty($status)){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom status yang kosong';
                        continue;
                    }else{
                        //get status data
                        $statusData = self::getDataByColumn($list_status, $status, 'status');

                        //cek nama status sesuai
                        if(!$statusData){
                            $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki status yang salah';
                            continue;
                        }else{
                            $data['status_id'] = $statusData->id;
                            $data['status_kode'] = $statusData->kode;
                        }
                    }

                    //validate tahun join
                    if(empty($data['tanggal_join']) && $data['status_id'] != 0){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom tahun join yang kosong';
                        continue;
                    }else{
                        try {
                            $data['tanggal_join'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data['tanggal_join'])->format('Y-m-d');
                        } catch (\Throwable $th) {
                            $data['tanggal_join'] = LibraryClayController::convertDate($data['tanggal_join']);
                        }

                        $data['tahun_join_y'] = Carbon::createFromFormat('Y-m-d', $data['tanggal_join'])->format('y');
                    }

                    //validate tanggal akhir kontrak
                    try {
                        $data['tanggal_akhir_kontrak'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data['tanggal_akhir_kontrak'])->format('Y-m-d');
                    } catch (\Throwable $th) {
                        $data['tanggal_akhir_kontrak'] = LibraryClayController::convertDate($data['tanggal_akhir_kontrak']);
                    }

                    if($data['tanggal_akhir_kerja']){
                        try {
                            $data['tanggal_akhir_kerja'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data['tanggal_akhir_kerja'])->format('Y-m-d');
                        } catch (\Throwable $th) {
                            $data['tanggal_akhir_kerja'] = LibraryClayController::convertDate($data['tanggal_akhir_kerja']);
                        }
                    }

                    //validate no id karyawan
                    if(!empty($data['no_id_karyawan'])){
                        $karyawan = HrdKaryawan::where('no_id_karyawan', $data['no_id_karyawan'])->first();

                        if($karyawan){
                            $this->error[$key]['message'] = 'Data row ' . $key . ' dengan No Induk Karyawan ' . $data['no_id_karyawan'] . ' sudah digunakan oleh '.$karyawan->employee_name. '! Mohon di cek & update kembali';
                            continue;
                        }

                    }

                    //get data karyawan
                    $data_karyawan = HrdKaryawan::where('no_ktp', $data['no_ktp'])->latest()->first();

                    //Check if data karyawan exist
                    if($data_karyawan){
                        //check data import, no id karyawan is not null
                        if(!empty($data['no_id_karyawan'])){
                            // $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->where('no_id_karyawan', $data['no_id_karyawan'])->latest()->first();
                            $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->latest()->first();

                            $data_karyawan_exist->update([
                                'employee_name' => $data['employee_name'],
                                'employee_email' => $data['employee_email'],
                                'employee_job_title' => $data['employee_job_title'],
                                'employee_department' => $data['employee_department'],
                                'employee_project' => $data['employee_project'],
                                'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
                                'employee_blood_type' => $data['employee_blood_type'],
                                'corporate_email' => $data['corporate_email'],
                                'keterangan' => $data['keterangan'],
                                'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
                            ]);

                            $this->success[$key]['message'] = 'Data row ' . $key . ' dengan NIK ' . $data['no_ktp'] . ' berhasil diupdate';
                        }else{
                            /*
                            if(empty($data_karyawan->tanggal_akhir_kerja)){
                                //check hire, status, tahun join, same as exist data db
                                if(
                                    $data['hire_id'] == $data_karyawan->hire_id &&
                                    $data['status_id'] == $data_karyawan->status_id &&
                                    Carbon::createFromFormat('Y-m-d', $data['tanggal_join'])->format('Y') == Carbon::createFromFormat('Y-m-d', $data_karyawan->tanggal_join)->format('Y')
                                ){
                                    $data_karyawan->update([
                                        'employee_name' => $data['employee_name'],
                                        'employee_email' => $data['employee_email'],
                                        'employee_job_title' => $data['employee_job_title'],
                                        'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
                                        'corporate_email' => $data['corporate_email'],
                                        'keterangan' => $data['keterangan'],
                                        'employee_department' => $data['employee_department'],
                                        'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
                                        'employee_blood_type' => $data['employee_blood_type'],
                                        'employee_project' => $data['employee_project'],
                                    ]);

                                    $this->success[$key]['message'] = 'Data row ' . $key . ' dengan NIK ' . $data['no_ktp'] . ' berhasil diupdate tanpa generate NIP';
                                }else{
                                    $this->error[$key]['message'] = 'Data row ' . $key . ' dengan NIK ' . $data['no_ktp'] . ' tidak sesuai';
                                }
                            }else{
                                //insert data
                                $insert = self::insertData($data);
                            }
                            */

                            $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->latest()->first();

                            $data_karyawan_exist->update([
                                'employee_name' => $data['employee_name'],
                                'employee_email' => $data['employee_email'],
                                'employee_job_title' => $data['employee_job_title'],
                                'employee_department' => $data['employee_department'],
                                'employee_project' => $data['employee_project'],
                                'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
                                'employee_blood_type' => $data['employee_blood_type'],
                                'corporate_email' => $data['corporate_email'],
                                'keterangan' => $data['keterangan'],
                                'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
                            ]);
                        }
                    }else{
                        $insert = self::insertData($data);
                    }
                }
            }
        }else{
            $this->error[]['message'] = 'Format data salah';
        }
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getSuccess(): array
    {
        return $this->success;
    }

    public function getDataByColumn($list_data, $search, $column)
    {
        return $list_data->where($column, $search)->first();
    }

    public function insertData($data){

        $karyawan = HrdKaryawan::create([
            'employee_name' => $data['employee_name'],
            'employee_job_title' => $data['employee_job_title'],
            'employee_email' => $data['employee_email'],
            'corporate_email' => $data['corporate_email'],
            'no_ktp' => $data['no_ktp'],
            'hire_id' => $data['hire_id'],
            'status_id' => $data['status_id'],
            'tanggal_join' => $data['tanggal_join'],
            'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
            'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
            'keterangan' => $data['keterangan'],
            'employee_department' => $data['employee_department'],
            'employee_blood_type' => $data['employee_blood_type'],
            'employee_project' => $data['employee_project'],
        ]);

        //cek NIP null
        if(empty($data['no_id_karyawan'])){

            $unique_group = $data['status_kode'] . $data['hire_kode'] . $data['tahun_join_y'];

            //checking unique group on hrd increment
            $increment_data = HrdIncrement::where('unique_group', $unique_group)->max('increment');

            //create increment data
            if(!$increment_data){
                $increment_create = HrdIncrement::create([
                    'karyawan_id' => $karyawan->id,
                    'unique_group' => $unique_group,
                    'increment'=> 1,
                ]);
            }else{
                $increment_create  = HrdIncrement::create([
                    'karyawan_id' => $karyawan->id,
                    'unique_group' => $unique_group,
                    'increment'=> $increment_data + 1,
                ]);
            }

            //merge to no id karyawan
            $no_urut_karyawan = str_pad($increment_create->increment,5,"0", STR_PAD_LEFT);
            $no_id_karyawan = $unique_group . $no_urut_karyawan;

            $karyawan->update([
                'no_id_karyawan' => $no_id_karyawan,
            ]);

            $this->success[]['message'] = 'Data row ' . $data['key'] . ' dengan NIK ' . $data['no_ktp'] . ' berhasil ditambah dan generate NIP';
        }else{
            $unique_group = substr($data['no_id_karyawan'], 0, 5);
            $no_urut_karyawan = (int) substr($data['no_id_karyawan'], 5);

            $increment = HrdIncrement::create([
                'karyawan_id' => $karyawan->id,
                'unique_group' => $unique_group,
                'increment'=> $no_urut_karyawan,
            ]);

            $karyawan->update([
                'no_id_karyawan' => $data['no_id_karyawan'],
            ]);

            $this->success[]['message'] = 'Data row ' . $data['key'] . ' dengan NIK ' . $data['no_ktp'] . ' berhasil ditambah tanpa generate NIP';
        }
    }
}
