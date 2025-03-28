<?php

namespace Bangsamu\Master\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'setting';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public function __construct()
    {
        if (!Schema::hasTable($this->table)) {
            /*buat tabel settings*/
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->text('value');
                $table->string('category', 255);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['category', 'name'], 'category_name_unique');
            });
        }
    }

}
