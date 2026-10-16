<?php

namespace Bangsamu\Master\Tests;

use Bangsamu\Master\MasterPackageServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $sessionPath;

    protected function getPackageProviders($app)
    {
        return [MasterPackageServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $this->sessionPath = sys_get_temp_dir().'/bangsamu-master-test-sessions-'.getmypid();
        File::ensureDirectoryExists($this->sessionPath);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('database.default', 'testing');
        // HandleCors global tidak ikut campur, supaya header CORS dari controller yang teruji
        $app['config']->set('cors.paths', []);
        // session server-side (seperti redis di GDA) supaya bisa dibaca lewat handler
        $app['config']->set('session.driver', 'file');
        $app['config']->set('session.files', $this->sessionPath);
        $app['config']->set('auth.providers.users.model', \App\Models\User::class);
        $app['config']->set('SsoConfig.main.APP_CODE', 'APP27');
        $app['config']->set('AnnotationConfig.main', [
            'URL' => 'http://meindo-annotation.test',
            'VALIDATE_SESSION' => true,
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        // database sqlite in-memory khusus test, bukan database app
        $pdo = DB::connection()->getPdo();
        // query legacy memakai MD5(email) milik MySQL
        method_exists($pdo, 'createFunction') ? $pdo->createFunction('MD5', 'md5', 1) : $pdo->sqliteCreateFunction('MD5', 'md5', 1);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('api_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('field_key');
            $table->string('field_value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);

        parent::tearDown();
    }
}
