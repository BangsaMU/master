<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Employee as HrdKaryawan;
use Bangsamu\Master\Models\JobPosition as HrdJobPosition;
use Bangsamu\Master\Models\MasterIncrement as HrdIncrement;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class EmployeeImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    // protected $request;

    // function __construct($request) {
    //         $this->request = $request;
    // }

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows)
    {
        //check format data
        if($rows){
            $list_status = DB::connection('db_master')->table('master_status')->get();
            $list_hire = DB::connection('db_master')->table('master_location')->where('group_type', 'hrd')->get();

            foreach ($rows as $key => $row) {
                //skip header row
                if($key > 0){

                    $key = $key + 1; //adjust key to start from 1
                    $data['key'] = $key;
                    $data['employee_name'] = $row['nama'];
                    $data['employee_email'] = empty($row['email_personal']) ? null : $row['email_personal'];
                    $data['corporate_email'] = empty($row['email_corporate']) ? null : $row['email_corporate'];
                    $job_position_code = $row['job_position_code'];
                    // $data['employee_department'] = $row[4];
                    $data['no_id_karyawan'] = $row['no_id_karyawan'];
                    $data['no_ktp'] = trim($row['no_ktp']);
                    $data['dob'] = empty($row['dob']) ? null : $row['dob'];
                    $poh_code = trim($row['poh_code']);
                    $status = trim($row['status']);
                    $data['tanggal_join'] = empty($row['tanggal_join']) ? null : $row['tanggal_join'];
                    $data['tanggal_akhir_kontrak'] = $row['tanggal_akhir_kontrak'];
                    $data['tanggal_akhir_kerja'] = $row['tanggal_akhir_kerja'];
                    $data['employee_blood_type'] = $row['employee_bloodtype'];
                    $data['employee_project'] = $row['employee_project'];
                    $data['keterangan'] = $row['keterangan'];

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

                    //validate nik
                    if(empty($data['no_ktp'])){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom NO_KTP yang kosong';
                        continue;
                    }else if(strlen($data['no_ktp']) > 16){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki NO_KTP lebih dari 16 karakter';
                        continue;
                    }else{
                        $data['no_ktp'] = strtoupper($data['no_ktp']);
                    }

                    //validate posisi (jabatan)
                    if(!$job_position_code){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom kode posisi yang kosong';
                        continue;
                    }else{
                        $jobPositionData = HrdJobPosition::where('position_code', $job_position_code)->with('department')->first();

                        if(!$jobPositionData){
                            $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki Kode Posisi yang salah, Mohon cek kembali kode pada menu Job Position!';
                            continue;
                        }else{
                            $data['employee_department'] = $jobPositionData->department->department_name;
                            $data['employee_job_title'] = $jobPositionData->position_name;
                            $data['job_position_code'] = $jobPositionData->position_code;
                            $data['job_position_id'] = $jobPositionData->id;
                        }
                    }

                    //validate hire
                    if($poh_code){
                        //get hire data
                        $pohData = self::getDataByColumn($list_hire, $poh_code, 'loc_name');

                        //cek nama lokasi sesuai
                        if(!$pohData){
                            $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki hire lokasi yang salah';
                            continue;
                        }else{
                            $data['hire_id'] = $pohData->id;
                            $data['hire_code'] = $pohData->loc_code;
                            $data['hire_name'] = $pohData->loc_name;
                        }
                    }

                    //validate status
                    if(!is_numeric($status)){
                        $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom status yang kosong / tidak sesuai';
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
                            $data['status_name'] = $statusData->status;
                        }
                    }

                    //validate DOB
                    if(empty($data['dob'])){
                        $extractDataKTP = LibraryClayController::extractDataKTP($data['no_ktp']);
                        $data['dob'] = $extractDataKTP['tanggalLahir'];
                    }else{
                        try {
                            $data['dob'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data['dob'])->format('Y-m-d');
                        } catch (\Throwable $th) {
                            $data['dob'] = LibraryClayController::convertDate($data['dob']);
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

                    if($data['employee_blood_type']){
                        $bloodTypes = ['A','A+','A-','B','B+','B-','O','O+','O-','AB','AB+','AB-'];

                        if (!in_array($data['employee_blood_type'], $bloodTypes)) {
                            $this->error[$key]['message'] = 'Data row ' . $key . ' memiliki kolom Blood Type yang tidak sesuai!';
                            continue;
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
                        $original = $data_karyawan->getOriginal();
                        //check data import, no id karyawan is not null
                        if(!empty($data['no_id_karyawan'])){
                            // $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->where('no_id_karyawan', $data['no_id_karyawan'])->latest()->first();
                            // $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->latest()->first();

                            $data_karyawan->update([
                                'employee_name' => $data['employee_name'],
                                'employee_email' => $data['employee_email'],
                                'corporate_email' => $data['corporate_email'],
                                'employee_job_title' => $data['employee_job_title'],
                                'employee_department' => $data['employee_department'],
                                'job_position_id' => $data['job_position_id'],
                                'employee_blood_type' => $data['employee_blood_type'],
                                'employee_project' => $data['employee_project'],
                                'hire_id' => $data['hire_id'],
                                'employee_dob' => $data['dob'],
                                'status_id' => $data['status_id'],
                                'tanggal_join' => $data['tanggal_join'],
                                'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
                                'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
                                'keterangan' => $data['keterangan'],
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

                            // $data_karyawan_exist = HrdKaryawan::where('no_ktp', $data['no_ktp'])->latest()->first();

                            $data_karyawan->update([
                                'employee_name' => $data['employee_name'],
                                'employee_email' => $data['employee_email'],
                                'corporate_email' => $data['corporate_email'],
                                'employee_job_title' => $data['employee_job_title'],
                                'employee_department' => $data['employee_department'],
                                'job_position_id' => $data['job_position_id'],
                                'employee_blood_type' => $data['employee_blood_type'],
                                'employee_project' => $data['employee_project'],
                                'hire_id' => $data['hire_id'],
                                'employee_dob' => $data['dob'],
                                'status_id' => $data['status_id'],
                                'tanggal_join' => $data['tanggal_join'],
                                'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
                                'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
                                'keterangan' => $data['keterangan'],
                            ]);

                            $this->success[$key]['message'] = 'Data row ' . $key . ' dengan NIK ' . $data['no_ktp'] . ' berhasil diupdate';
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
            'employee_email' => $data['employee_email'],
            'corporate_email' => $data['corporate_email'],
            'employee_job_title' => $data['employee_job_title'],
            'employee_department' => $data['employee_department'],
            'job_position_id' => $data['job_position_id'],
            'employee_blood_type' => $data['employee_blood_type'],
            'employee_project' => $data['employee_project'],
            'no_ktp' => $data['no_ktp'],
            'employee_dob' => $data['dob'],
            'hire_id' => $data['hire_id'],
            'status_id' => $data['status_id'],
            'tanggal_join' => $data['tanggal_join'],
            'tanggal_akhir_kontrak' => $data['tanggal_akhir_kontrak'],
            'tanggal_akhir_kerja' => $data['tanggal_akhir_kerja'],
            'keterangan' => $data['keterangan'],
        ]);

        //cek NIP null
        $skip=true;
        if(empty($data['no_id_karyawan'])&&$skip=='false'){

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
