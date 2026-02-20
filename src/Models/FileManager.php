<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Bangsamu\LibraryClay\Traits\Loggable;

use Illuminate\Support\Facades\DB;

use App\Jobs\SyncMasterJob;

class FileManager extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $table = "master_file_manager";
    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tfr_id');
                    $table->unsignedBigInteger('tfrs_id')->nullable();
                    $table->unsignedBigInteger('wgallery_id');
                    $table->unsignedBigInteger('user_id');
                    $table->enum('attachment_type', ['evidence', 'action'])->nullable()->collation('utf8mb4_unicode_ci');
                    $table->string('app', 255)->default('teliti')->collation('utf8mb4_unicode_ci');

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at
                });
            }
        }
    }
}
