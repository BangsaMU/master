<?php

/*
 * Stub class milik app (paket ini memakai App\Models\* dari app yang memasangnya).
 * Hanya dipakai saat menjalankan test paket.
 */

namespace App\Http\Controllers {
    abstract class Controller extends \Illuminate\Routing\Controller {}
}

namespace App\Models {
    class User extends \Illuminate\Foundation\Auth\User
    {
        protected $table = 'users';

        protected $guarded = [];
    }

    class UserDetail extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'user_details';

        protected $guarded = [];
    }
}
