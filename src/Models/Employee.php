<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;


class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    public $table = "master_employee";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create('master_employee', function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->string('employee_name', 100)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
                    $table->string('employee_job_title', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->comment('di hrd app == posisi');
                    $table->string('employee_email', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->unique('email');
                    $table->string('corporate_email', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');//->unique('unique_corporate_email'); unik dihapus karena banyak email corp yg ga unik
                    $table->string('employee_phone', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
                    $table->string('employee_department', 155)->nullable();
                    $table->timestamps(); // Automatically creates `created_at` and `updated_at`
                    $table->dateTime('deleted_at')->nullable();
                    $table->string('no_ktp', 17)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->unique('unique_no_ktp');
                    $table->string('no_id_karyawan', 15)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->unique('unique_no_id_karyawan');
                    $table->tinyInteger('status_id')->default(2);
                    $table->tinyInteger('hire_id')->nullable();
                    $table->date('tanggal_join')->nullable()->comment('isi tanggal awal kontrak');
                    $table->date('tanggal_akhir_kerja')->nullable()->comment('jika resign/sebelum habis kontrak bisa di isi');;
                    $table->date('tanggal_akhir_kontrak')->nullable()->comment('isi tanggal akhir kontrak');
                    $table->string('keterangan', 255)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
                    $table->tinyInteger('work_location_id')->nullable();
                    $table->string('app_code', 10)->default('APP11')->comment('buat limit hak akses app yg boleh edit default emplye HRD meindo app');

                    // Tambahkan kolom employee_blood_type
                    $table->string('employee_blood_type', 5)
                    ->nullable()
                    ->default('-');

                    $table->string('employee_project', 10)->nullable();
                    $table->integer('project_id')->nullable();
                    $table->integer('job_position_id')->nullable();
                    $table->date('employee_dob')->nullable()->comment('isi tanggal lahir wni dari ktp auto extrak');

                    // Indexes
                    $table->index('app_code', 'index_app_code');
                    $table->index('hire_id', 'index_hire_id');
                    $table->index('status_id', 'index_status_id');
                    $table->index('work_location_id', 'index_work_location_id');
                    $table->index('employee_blood_type', 'index_employee_blood_type'); // Tambahkan index untuk employee_blood_type
                });
            }
        }
    }
}
