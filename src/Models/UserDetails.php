<?php

namespace Bangsamu\Master\Models;

use Bangsamu\LibraryClay\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class UserDetails extends Model
{
    use HasFactory, Notifiable;
    use Loggable;
    use SoftDeletes;

    protected $table = 'master_user_details';

    protected $guarded = [];

    protected static $hasCheckedTable = false;

    protected static function booted()
    {

        if (! self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (! Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

                    $table->string('field_key', 255)->collation('utf8mb4_unicode_ci');
                    $table->string('field_value', 255)->nullable()->collation('utf8mb4_unicode_ci');

                    $table->timestamps(); // created_at & updated_at
                    $table->softDeletes(); // deleted_at

                    $table->unsignedBigInteger('user_id');
                    $table->string('user_email', 255)->collation('utf8mb4_unicode_ci');

                    // Unique constraints
                    $table->unique('id', 'unique_id'); // redundant but matched
                    $table->unique(['user_id', 'field_key'], 'unique_ker_id');

                    // Indexes
                    $table->index('field_key', 'index_signature');
                    $table->index('user_email', 'index_user_email');

                    // Optional: tambahkan foreign key jika perlu
                    // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                });
            }
        }
    }
}
