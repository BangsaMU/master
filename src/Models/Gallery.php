<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Gallery extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = "master_gallery";
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
                    $table->unsignedBigInteger('user_id');
                    $table->string('caption', 255)->nullable();
                    $table->text('url')->nullable();
                    $table->text('path')->nullable();
                    $table->string('filename', 191)->nullable();
                    $table->string('size', 191)->nullable();
                    $table->string('header_type', 191)->nullable();
                    $table->string('file_group', 191)->nullable();
                    $table->string('hash_file', 255)->nullable();
                    $table->char('file_type', 10);
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }
    }
}
