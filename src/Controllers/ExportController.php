<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Exports\DataExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\ExportRunner\Jobs\RunExportReportJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function export($table)
    {
        $table_name = ucwords(str_replace('_', ' ', $table));
        $report = 'Maatwebsite'; // Maatwebsite,goReport

        // Jika diawali dengan "GO_", override jadi goReport
        if (str_starts_with($table, 'GO-')) {
            $report = 'goReport';
            $table_name = substr($table, 3); // Hapus prefix "GO-"
        }


        switch ($table) {
            case 'master_project':
                $table_name = 'Project';
                break;
        }

        // dd($report == 'goReport',$report,$table,$table_name);

        if($report == 'Maatwebsite'){
            $file_name = $table_name . '.xlsx';

            $export = new DataExport;
            $export->setTable($table);

            return Excel::download($export, $file_name);
        }

        if($report == 'goReport'){

            $file_name = $table_name . '.xlsx';

            $data = [
                'file_name' => $file_name,
                // tambahkan lainnya jika perlu
            ];

            $request = new Request($data);

            return $this->exportExcel($request);
        }

        // Default fallback: report type tidak ditemukan
        return response()->json([
            'message' => 'Report type '.$report.' tidak ditemukan untuk table: ' . $table_name
        ], 400); // 400 Bad Request
    }



    public function exportExcel($request)
    {
        $user_email = Auth::user()->email;
        $report_id = 2;

        // 1. Ambil dan prefix input
        $input = $request->all();
        $prefixedInput = [];
        foreach ($input as $key => $value) {
            $prefixedInput["param_" . $key] = $value;
        }

        // 2. Tambah extra param
        $extraParams = ['user' => $user_email];
        $params = array_merge($prefixedInput, $extraParams);
        $params_json = json_encode($params);



        // 4. Cek apakah file sudah dibuat sebelumnya
        $query = DB::table('report_log as rl')
                    ->where('report_id', $report_id)
                    ->where('executed_by', $user_email);
                    foreach ($params as $key => $value) {

                        $jsonKey = "params_json->$key";

                    //     if (is_array($value)) {
                    //         $query->whereJsonContains($jsonKey, $user_email);
                    //     } else {
                    //         $query->where($jsonKey, $value);
                    //     }

                        $excludeKeys = ['param__token', 'param__'];

                        if (!empty($key) && !empty($value) && !in_array($key, $excludeKeys, true)) {
                        // dd($key , $value);
                            $query->whereJsonContains($jsonKey, $value);
                        };
                    }

        $log = $query->latest('created_at')->first();
        // $log = DB::table('report_log as rl')
        //     ->whereJsonContains('params_json->user', $user_email)
        //     ->where('executed_by', $user_email)
        //     ->where('report_id', $report_id)
        //     ->latest('created_at')
        //     ->first();
        // dd( $user_email,$log,$params_json,$query->toSql());

        Log::info("DD params:: ",$params);
        Log::info("DD query:: ",[$query->toSql()]);
        Log::info("DD log:: ", (array) $log??[]); // casting ke array juga aman
        // Log::info("DD query:: ".json_encode([$user_email,$log,$params_json,$query->toSql()]));
        if ($log) {
            $relativePath = ltrim(preg_replace('#^storage/?#', '', $log->file_name), '/');
            $fullPath = storage_path($relativePath);


            // 3. Bersihkan file lebih dari 7 hari
            $this->cleanupOldFiles(storage_path($relativePath), 7);
            // dd(file_exists($fullPath),$fullPath);
            if (file_exists($fullPath)) {
                $fileAgeInHours = now()->diffInHours(\Carbon\Carbon::parse($log->created_at));

                if ($fileAgeInHours < 24) {
                    // ✅ File valid, langsung download
                    // dd(1);
                    return response()->download($fullPath, basename($fullPath));
                } else {
                    // dd(2);
                    // ⚠️ File expired (>1 hari), hapus
                    @unlink($fullPath);
                    DB::table('report_log')->where('id', $log->id)->delete();
                }
            }

        }else{
            // 5. Dispatch job baru
            $job = new RunExportReportJob($report_id, $params, $user_email);
            dispatch($job);

            // 6. Tunggu job selesai (opsional) — atau return respon "sedang diproses"
            return response()->json([
                'message' => 'Export is being processed, an email will be sent when it is complete.',
                'job_id' => $job->getJobId(),
            ], 422);
        }


    }

    // 🧹 Helper: Hapus file lebih dari X hari
    protected function cleanupOldFiles($directory, $maxAgeInDays = 7)
    {
        $directoryPath = dirname($directory);
        Log::info("cleanupOldFiles: $directoryPath");

        if (!is_dir($directoryPath)) return;

        $files = glob($directoryPath . '/*');
        Log::info("list files: ",$files);

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileModified = filemtime($file);
                $ageInDays = (time() - $fileModified) / 86400;

                if ($ageInDays > $maxAgeInDays) {
                    @unlink($file);
                    Log::info("unlink: $file");
                }
            }
        }
    }

}
