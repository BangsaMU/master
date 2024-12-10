<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_employee";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_employee*/
            Schema::create('master_employee', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('employee_name', 100)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->unique('name');
                $table->string('employee_job_title', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->comment('di hrd app == posisi');
                $table->string('employee_email', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->unique('email');
                $table->string('employee_phone', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci');
                $table->timestamps(); // Automatically creates `created_at` and `updated_at`
                $table->dateTime('deleted_at')->nullable();
                $table->string('corporate_email', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->unique('unique_corporate_email');
                $table->string('no_ktp', 17)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->unique('unique_no_ktp');
                $table->string('no_id_karyawan', 15)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci')->unique('unique_no_id_karyawan');
                $table->tinyInteger('status_id')->default(2);
                $table->tinyInteger('hire_id')->nullable();
                $table->date('tanggal_join')->nullable();
                $table->date('tanggal_akhir_kerja')->nullable();
                $table->date('valid_to')->nullable();
                $table->string('keterangan', 255)->nullable()->charset('utf8mb4')->collation('utf8mb4_general_ci');
                $table->tinyInteger('work_location_id')->nullable();

                // Indexes
                $table->index('hire_id', 'index_hire_id');
                $table->index('status_id', 'index_status_id');
                $table->index('work_location_id', 'index_work_location_id');
            });

        }
    }
}
