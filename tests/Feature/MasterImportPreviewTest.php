<?php

namespace {
    if (! function_exists('checkPermission')) {
        /** Stub helper app: izin ditolak bila tercantum di config test.denied_permissions. */
        function checkPermission($permission)
        {
            return auth()->check() && ! in_array($permission, config('test.denied_permissions', []), true);
        }
    }
}

namespace Bangsamu\Master\Tests\Feature {

    use App\Models\User;
    use Bangsamu\LibraryClay\Models\ActivityLog;
    use Bangsamu\Master\Tests\TestCase;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Http\UploadedFile;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Schema;
    use Maatwebsite\Excel\ExcelServiceProvider;

    class MasterImportPreviewTest extends TestCase
    {
        private User $user;

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

        protected function setUp(): void
        {
            parent::setUp();

            Http::fake();
            $this->user = User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']);
            $this->actingAs($this->user);
        }

        private function csv(string $content, string $name = 'import.csv'): UploadedFile
        {
            return UploadedFile::fake()->createWithContent($name, $content);
        }

        /** Upload file lalu kembalikan token preview. */
        private function upload(string $entity, string $content): string
        {
            $response = $this->post(route('master.import.preview', $entity), ['file' => $this->csv($content)]);
            $response->assertRedirect();

            $this->assertMatchesRegularExpression('#/master/import/'.$entity.'/preview/([0-9a-f-]{36})$#', $response->headers->get('Location'));

            return basename($response->headers->get('Location'));
        }

        private function seedCategories(): void
        {
            DB::table('master_category')->insert([
                ['category_code' => 'A01', 'category_name' => 'Lama', 'remark' => 'catatan', 'app_code' => 'APP27'],
                ['category_code' => 'A02', 'category_name' => 'Sama', 'remark' => null, 'app_code' => 'APP27'],
                ['category_code' => 'A03', 'category_name' => 'Punya app lain', 'remark' => null, 'app_code' => 'APP03'],
            ]);
        }

        private const CATEGORY_CSV = "Category Code,Category Name,Remark\n"
            ."a01,Baru Diganti,\n"     // update nama, remark kosong tidak menghapus
            ."A02,Sama,\n"             // tidak berubah
            ."A03,Diubah,\n"           // milik app lain
            ."B01,Kategori Baru,ok\n"  // baru
            .",Tanpa Kode,\n"          // error wajib
            ."B01,Duplikat,\n";        // duplikat di file

        public function test_preview_mengklasifikasi_baris_tanpa_menulis_database(): void
        {
            $this->seedCategories();
            $token = $this->upload('category', self::CATEGORY_CSV);

            $response = $this->get(route('master.import.show', ['category', $token]));

            $response->assertOk();
            $analysis = $response->viewData('analysis');
            $this->assertSame(['new' => 1, 'update' => 1, 'same' => 1, 'locked' => 1, 'error' => 2], $analysis['summary']);
            $this->assertSame(['Category Name' => 1], $analysis['update_fields']);

            $update = collect($analysis['rows'])->firstWhere('status', 'update');
            $this->assertSame(['category_name'], array_keys($update['changes']));
            $this->assertSame('Lama', $update['changes']['category_name']['old']);
            $this->assertSame(2, $update['row']);

            $response->assertSee('Izinkan update');
            $response->assertSee('duplikat dengan baris 5', false);

            $this->assertSame(3, DB::table('master_category')->count());
            $this->assertSame(0, DB::table('activity_logs')->count());
        }

        public function test_commit_tanpa_izin_update_hanya_membuat_data_baru(): void
        {
            $this->seedCategories();
            $token = $this->upload('category', self::CATEGORY_CSV);

            $this->post(route('master.import.commit', ['category', $token]))
                ->assertRedirect(route('master.category.index'))
                ->assertSessionHas('success_message', fn ($m) => str_contains($m[0], '1 dibuat, 0 diupdate, 3 dilewati, 2 gagal'));

            $this->assertSame('Lama', DB::table('master_category')->where('category_code', 'A01')->value('category_name'));
            $this->assertDatabaseHas('master_category', ['category_code' => 'B01', 'category_name' => 'Kategori Baru', 'remark' => 'ok', 'app_code' => 'APP27']);

            $log = ActivityLog::sole();
            $this->assertSame('created', $log->action);
            $this->assertSame('Bagas Setyo created a new Category (via import)', $log->description);
            $this->assertSame($this->user->id, $log->user_id);
            $this->assertSame('B01', $log->properties['category_code']);
            $this->assertSame('import', $log->properties['import']['source']);
            $this->assertSame(5, $log->properties['import']['row']);
        }

        public function test_izin_update_wajib_disertai_alasan(): void
        {
            $this->seedCategories();
            $token = $this->upload('category', self::CATEGORY_CSV);

            $this->from(route('master.import.show', ['category', $token]))
                ->post(route('master.import.commit', ['category', $token]), ['allow_update' => '1', 'update_reason' => ''])
                ->assertRedirect(route('master.import.show', ['category', $token]))
                ->assertSessionHasErrors('update_reason');

            $this->assertSame(3, DB::table('master_category')->count());
        }

        public function test_commit_dengan_izin_update_mencatat_alasan_di_activity_log(): void
        {
            $this->seedCategories();
            $token = $this->upload('category', self::CATEGORY_CSV);

            $this->post(route('master.import.commit', ['category', $token]), [
                'allow_update' => '1',
                'update_reason' => 'koreksi nama dari SAP',
            ])->assertSessionHas('success_message', fn ($m) => str_contains($m[0], '1 dibuat, 1 diupdate, 2 dilewati, 2 gagal'));

            $a01 = DB::table('master_category')->where('category_code', 'A01')->first();
            $this->assertSame('Baru Diganti', $a01->category_name);
            $this->assertSame('catatan', $a01->remark, 'kolom kosong di file tidak mengosongkan data lama');
            $this->assertSame('Punya app lain', DB::table('master_category')->where('category_code', 'A03')->value('category_name'));

            $log = ActivityLog::where('action', 'updated')->sole();
            $this->assertSame('Bagas Setyo updated Category (via import: koreksi nama dari SAP)', $log->description);
            $this->assertSame((int) $a01->id, (int) $log->model_id);
            $this->assertSame(['category_name' => 'Lama'], $log->properties['before']);
            $this->assertSame('Baru Diganti', $log->properties['after']['category_name']);
            $this->assertSame('koreksi nama dari SAP', $log->properties['import']['reason']);
            $this->assertSame(1, ActivityLog::where('action', 'created')->count());

            // token preview sekali pakai
            $this->get(route('master.import.show', ['category', $token]))->assertRedirect(route('master.category.index'));
        }

        public function test_update_ditolak_tanpa_permission_update(): void
        {
            config(['test.denied_permissions' => ['is_admin', 'update_category']]);
            $this->seedCategories();
            $token = $this->upload('category', self::CATEGORY_CSV);

            $this->get(route('master.import.show', ['category', $token]))->assertOk()->assertDontSee('Izinkan update');

            $this->post(route('master.import.commit', ['category', $token]), ['allow_update' => '1', 'update_reason' => 'coba update'])
                ->assertForbidden();
        }

        public function test_tanpa_permission_create_tidak_bisa_preview(): void
        {
            config(['test.denied_permissions' => ['is_admin', 'create_uom']]);

            $this->post(route('master.import.preview', 'uom'), ['file' => $this->csv("UOM Code,UOM Name\nPCS,Pieces\n")])
                ->assertForbidden();
        }

        public function test_preview_milik_user_lain_tidak_bisa_dibuka(): void
        {
            $token = $this->upload('pca', "PCA Code,PCA Name\nP-1,Satu\n");

            $this->actingAs(User::create(['name' => 'Lain', 'email' => 'lain@meindo.test']));

            $this->get(route('master.import.show', ['pca', $token]))->assertRedirect(route('master.pca.index'));
            $this->post(route('master.import.commit', ['pca', $token]))->assertRedirect(route('master.pca.index'));
            $this->assertSame(0, DB::table('master_pca')->count());
        }

        public function test_uom_nama_yang_dipakai_kode_lain_error(): void
        {
            DB::table('master_uom')->insert(['uom_code' => 'PCS', 'uom_name' => 'Pieces', 'app_code' => 'APP27']);
            $token = $this->upload('uom', "UOM Code,UOM Name\nPC,Pieces\nPCS,Piece\n");

            $rows = $this->get(route('master.import.show', ['uom', $token]))->viewData('analysis')['rows'];

            $this->assertSame('error', $rows[0]['status']);
            $this->assertStringContainsString("sudah dipakai UOM Code 'PCS'", $rows[0]['message']);
            $this->assertSame('update', $rows[1]['status']);
        }

        public function test_item_code_membuat_referensi_otomatis_dan_update(): void
        {
            DB::table('master_uom')->insert(['id' => 1, 'uom_code' => 'BOT', 'uom_name' => 'Bottle', 'app_code' => 'APP27']);
            DB::table('master_category')->insert(['id' => 1, 'category_code' => 'A04', 'category_name' => 'A04', 'app_code' => 'APP27']);
            DB::table('master_item_group')->insert(['id' => 1, 'item_group_code' => 'HSE', 'item_group_name' => 'HSE', 'item_group_attributes' => '{"size_1":null}', 'app_code' => 'APP27']);
            DB::table('master_item_code')->insert([
                'item_code' => 'HYG-1', 'item_name' => 'Nama Lama', 'uom_id' => 1, 'pca_id' => 0, 'category_id' => 1, 'group_id' => 1,
                'attributes' => '{"size_1":"10"}', 'app_code' => 'APP27', 'company_id' => 1,
            ]);

            $csv = "Item Code,Item Name,UoM Code,PCA Code,Category Code,Item Group Code,Size 1,Unit Weight\n"
                ."HYG-1,Nama Baru,BOT,,A04,HSE,10,5\n"
                ."HYG-2,Item Baru,LTR,PCA-1,A04,HSE,,\n";
            $token = $this->upload('item-code', $csv);

            $rows = $this->get(route('master.import.show', ['item-code', $token]))->assertOk()->viewData('analysis')['rows'];
            $this->assertSame('update', $rows[0]['status']);
            $this->assertSame(['item_name', 'attributes'], array_keys($rows[0]['changes']));
            $this->assertSame('{"size_1":"10","unit_weight":"5"}', $rows[0]['changes']['attributes']['new']);
            $this->assertSame('new', $rows[1]['status']);
            $this->assertContains("UOM 'LTR' belum ada, akan dibuat otomatis.", $rows[1]['notes']);

            $this->post(route('master.import.commit', ['item-code', $token]), ['allow_update' => '1', 'update_reason' => 'revisi nama item'])
                ->assertSessionHas('success_message', fn ($m) => str_contains($m[0], '1 dibuat, 1 diupdate'));

            $this->assertSame('Nama Baru', DB::table('master_item_code')->where('item_code', 'HYG-1')->value('item_name'));
            $new = DB::table('master_item_code')->where('item_code', 'HYG-2')->first();
            $ltr = DB::table('master_uom')->where('uom_code', 'LTR')->first();
            $this->assertSame((int) $ltr->id, (int) $new->uom_id);
            $this->assertSame('APP27', $new->app_code);
            $this->assertNotNull(DB::table('master_pca')->where('pca_code', 'PCA-1')->first());
            $this->assertSame(['size_1' => null, 'unit_weight' => null], json_decode(DB::table('master_item_group')->value('item_group_attributes'), true));

            $this->assertSame(1, ActivityLog::where('description', 'Bagas Setyo updated ItemCode (via import: revisi nama item)')->count());
            $this->assertSame(1, ActivityLog::where('description', 'Bagas Setyo created a new Uom (via import)')->count());
            $this->assertSame(1, ActivityLog::where('description', 'Bagas Setyo updated ItemGroup (via import: revisi nama item)')->count());
            $this->assertSame(1, ActivityLog::where('description', 'Bagas Setyo created a new ItemCode (via import)')->count());
        }

        public function test_location_memakai_kunci_kode_dan_group_type(): void
        {
            DB::table('master_location')->insert(['loc_code' => 'JKT', 'loc_name' => 'Jakarta', 'group_type' => 'office']);
            $token = $this->upload('location', "Location Code,Location Name,Group Type\nJKT,Jakarta Pusat,office\nJKT,Gudang Jakarta,warehouse\nBDG,Bandung,kantor\n");

            $summary = $this->get(route('master.import.show', ['location', $token]))->viewData('analysis')['summary'];
            $this->assertSame(['new' => 1, 'update' => 1, 'same' => 0, 'locked' => 0, 'error' => 1], $summary);

            $this->post(route('master.import.commit', ['location', $token]), ['allow_update' => '1', 'update_reason' => 'nama kantor baru']);

            $this->assertSame('Jakarta Pusat', DB::table('master_location')->where(['loc_code' => 'JKT', 'group_type' => 'office'])->value('loc_name'));
            $this->assertDatabaseHas('master_location', ['loc_code' => 'JKT', 'group_type' => 'warehouse', 'loc_name' => 'Gudang Jakarta']);
        }

        public function test_project_baru_membuat_project_detail_dan_project_lama_bisa_diupdate(): void
        {
            DB::table('master_company')->insert(['id' => 7, 'company_code' => 'ME']);
            DB::table('master_project')->insert(['project_code' => 'P001', 'project_name' => 'Proyek Lama', 'project_start_date' => '2026-01-01']);

            $token = $this->upload('project', "Project Code,Project Name,Project Type,Project Start Date,Project Complete Date,Project Remarks\n"
                ."P001,Proyek Lama,Internal,2026-02-01,,\n"
                ."P002,Proyek Baru,External,2026-03-01,2026-12-31,catatan\n");

            $rows = $this->get(route('master.import.show', ['project', $token]))->viewData('analysis')['rows'];
            $this->assertSame(['internal_external', 'project_start_date'], array_keys($rows[0]['changes']));

            $this->post(route('master.import.commit', ['project', $token]), ['allow_update' => '1', 'update_reason' => 'jadwal mundur']);

            $p1 = DB::table('master_project')->where('project_code', 'P001')->first();
            $this->assertSame('2026-02-01', substr($p1->project_start_date, 0, 10));
            $this->assertSame('I', $p1->internal_external);
            $p2 = DB::table('master_project')->where('project_code', 'P002')->first();
            $this->assertDatabaseHas('master_project_detail', ['project_id' => $p2->id, 'project_code_client' => 'P002', 'company_id' => 7]);
            $this->assertSame(1, ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'created')->count());
        }

        public function test_project_detail_update_nama_client(): void
        {
            DB::table('master_company')->insert(['id' => 7, 'company_code' => 'ME']);
            DB::table('master_project')->insert(['id' => 3, 'project_code' => 'P001', 'project_name' => 'Proyek']);
            DB::table('master_project_detail')->insert(['project_id' => 3, 'project_code_client' => 'C-1', 'project_name_client' => 'Lama', 'company_id' => 7]);

            $token = $this->upload('project-detail', "Project Code,Project Code Client,Project Name Client,Company Code\n"
                ."P001,C-1,Nama Client Baru,ME\n"
                ."P999,C-2,Tidak Ada,ME\n");

            $rows = $this->get(route('master.import.show', ['project-detail', $token]))->viewData('analysis')['rows'];
            $this->assertSame('update', $rows[0]['status']);
            $this->assertSame('error', $rows[1]['status']);

            $this->post(route('master.import.commit', ['project-detail', $token]), ['allow_update' => '1', 'update_reason' => 'nama dari kontrak']);

            $this->assertSame('Nama Client Baru', DB::table('master_project_detail')->value('project_name_client'));
        }

        public function test_vendor_update_lewat_vendor_service(): void
        {
            DB::table('master_location')->insert(['id' => 5, 'loc_code' => 'V001', 'loc_name' => 'Vendor Lama', 'group_type' => 'vendor']);
            DB::table('master_vendor')->insert(['id' => 9, 'loc_id' => 5, 'vendor_code' => 'V001', 'vendor_description' => 'Vendor Lama', 'vendor_address' => 'Jl. Lama', 'vendor_phone' => '0211']);
            DB::table('master_vendor_contact')->insert(['vendor_id' => 9, 'vendor_contact_name' => 'Andi']);

            $header = "Vendor Code,Vendor Name,Vendor Address,Vendor Phone,Vendor Fax,Vendor Email,Vendor Contact Name,Vendor Contact Phone,Vendor Contact Email,Vendor Contact Fax\n";
            $token = $this->upload('vendor', $header
                ."V001,Vendor Baru,Jl. Lama,,,,Budi,,,\n"
                ."V002,Vendor Dua,Jl. Dua,0222,,,,,,\n");

            $rows = $this->get(route('master.import.show', ['vendor', $token]))->viewData('analysis')['rows'];
            $this->assertSame(['vendor_description', 'vendor_contact_name'], array_keys($rows[0]['changes']));

            $this->post(route('master.import.commit', ['vendor', $token]), ['allow_update' => '1', 'update_reason' => 'ganti PIC']);

            $vendor = DB::table('master_vendor')->where('id', 9)->first();
            $this->assertSame('Vendor Baru', $vendor->vendor_description);
            $this->assertSame('0211', $vendor->vendor_phone, 'kolom kosong tidak menghapus data lama');
            $this->assertSame('Budi', DB::table('master_vendor_contact')->where('vendor_id', 9)->value('vendor_contact_name'));
            $this->assertSame('Vendor Baru', DB::table('master_location')->where('id', 5)->value('loc_name'));
            $this->assertDatabaseHas('master_vendor', ['vendor_code' => 'V002', 'vendor_phone' => '0222']);

            $log = ActivityLog::where('action', 'updated')->sole();
            $this->assertSame('Bagas Setyo updated Vendor (via import: ganti PIC)', $log->description);
            $this->assertSame(['vendor_description' => 'Vendor Lama', 'vendor_contact_name' => 'Andi'], $log->properties['before']);
        }
    }
}
