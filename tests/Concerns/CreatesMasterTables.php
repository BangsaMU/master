<?php

namespace Bangsamu\Master\Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\ExcelServiceProvider;

require_once __DIR__.'/../stubs/app_helpers.php';

/**
 * Environment + tabel master (sqlite in-memory) untuk test fitur master data.
 */
trait CreatesMasterTables
{
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [ExcelServiceProvider::class]);
    }

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.themes', '_tabler');
        $app['config']->set('view.paths', array_merge([__DIR__.'/../stubs/views'], $app['config']->get('view.paths', [])));
        $app['config']->set('MasterConfig.senada.active', false);
        $app['config']->set('MasterCrudConfig.MASTER_COMPANY_ID', 1);
        $app['config']->set('test.denied_permissions', []);
    }

    protected function defineDatabaseMigrations()
    {
        parent::defineDatabaseMigrations();

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description');
            $table->longText('properties')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        $simple = [
            'master_category' => ['category_code', 'category_name', 'remark'],
            'master_uom' => ['uom_code', 'uom_name'],
            'master_pca' => ['pca_code', 'pca_name'],
            'master_item_group' => ['item_group_code', 'item_group_name', 'item_group_attributes'],
        ];
        foreach ($simple as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
                $table->string('app_code', 10)->default('APP03');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('master_item_code', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 25)->unique();
            $table->string('item_name')->nullable();
            $table->integer('uom_id')->nullable();
            $table->integer('pca_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->integer('group_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('app_code', 10)->default('APP03');
            $table->longText('attributes')->nullable();
            $table->integer('company_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_location', function (Blueprint $table) {
            $table->id();
            $table->string('loc_code', 15)->nullable();
            $table->string('loc_name', 50)->nullable();
            $table->string('group_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_company', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_project', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 10)->unique();
            $table->string('project_name', 116)->nullable();
            $table->string('project_remarks')->nullable();
            $table->date('project_start_date')->nullable();
            $table->date('project_complete_date')->nullable();
            $table->char('internal_external', 1)->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_project_detail', function (Blueprint $table) {
            $table->id();
            $table->string('project_code_client', 10)->nullable();
            $table->string('project_name_client', 100)->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('project_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // DB in-memory baru tiap test: lupakan memo upgrade skema proses sebelumnya
        \Bangsamu\Master\Services\MasterSchemaUpgrader::flush();
        // memo kolom app_code/company_id per proses, database in-memory dibuat ulang tiap test
        \Bangsamu\Master\Services\MasterScope::flush();

        // skema lama master_department (seperti production sebelum migration app_code)
        Schema::create('master_department', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 15);
            $table->string('department_name', 100);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('department_code', 'uq_dept_code');
        });

        Schema::create('master_vendor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loc_id');
            $table->string('vendor_code', 15)->nullable();
            $table->string('vendor_description')->nullable();
            $table->string('vendor_address')->nullable();
            $table->string('vendor_phone', 100)->nullable();
            $table->string('vendor_email', 100)->nullable();
            $table->string('vendor_fax', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_vendor_contact', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_contact_name', 100)->nullable();
            $table->string('vendor_contact_phone', 100)->nullable();
            $table->string('vendor_contact_email', 100)->nullable();
            $table->string('vendor_contact_fax', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /** Tabel dashboard_settings (DashboardSettings hanya membuatnya sekali per proses, DB test dibuat ulang). */
    protected function createDashboardSettings(): void
    {
        if (Schema::hasTable('dashboard_settings')) {
            return;
        }
        Schema::create('dashboard_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text');
            $table->string('label');
            $table->text('options')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /** Jalankan migration paket app_code + unik (department_code, app_code). */
    protected function migrateDepartmentAppCode(): void
    {
        (require __DIR__.'/../../src/database/migrations/2026_10_22_000001_add_app_code_to_master_department_table.php')->up();
    }
}
