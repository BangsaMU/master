<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = "master_vendor";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_locations*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->bigInteger('id')->primary();
                $table->string('vendor_code', 15)->nullable();
                $table->string('vendor_description')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }
    }
}
