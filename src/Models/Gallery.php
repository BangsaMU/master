<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class Gallery extends Model
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    public $table = "master_gallery";
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel master_gallery*/
            Schema::create($this->table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('caption', 255)->nullable();
                $table->text('url')->nullable();
                $table->text('path')->nullable();
                $table->string('filename', 191)->nullable();
                $table->string('size', 191)->nullable();
                $table->string('header_type', 191)->nullable();
                $table->string('file_group', 191)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->string('hash_file', 255)->nullable();
                $table->char('file_type', 10);
            });
        }
    }

}
