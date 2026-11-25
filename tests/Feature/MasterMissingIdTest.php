<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * View/Edit master dengan id yang tidak ada = 404 (laporan 2026-11-25: `master/item-code/19999999999999/edit`).
 * Dulu: Item Code 500 ("Attempt to read property uom_id on null"), Location/Project 500 (`empty()` pada Collection
 * selalu false lalu `$formdata[0]`), modul lain 200 berisi form Create kosong karena view form dipakai bersama.
 * Baris terhapus (soft delete) tetap bisa dibuka, untuk Restore.
 */
class MasterMissingIdTest extends TestCase
{
    use CreatesMasterTables {
        defineDatabaseMigrations as createMasterTables;
    }

    private const ID_TIDAK_ADA = 19999999999999;

    protected function defineDatabaseMigrations()
    {
        $this->createMasterTables();

        // kolom yang di-join query edit() Company / Project Detail, tidak ada di tabel test bersama
        Schema::table('master_company', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('company_logo_id')->nullable();
        });
        Schema::create('master_gallery', function (Blueprint $table) {
            $table->id();
            $table->string('url')->nullable();
        });

        Schema::create('master_priority', function (Blueprint $table) {
            $table->id();
            $table->text('priority_code')->nullable();
            $table->text('priority_name')->nullable();
            $table->string('app_code', 10)->default('APP03');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    public static function modulProvider(): array
    {
        $kasus = [];
        foreach (['category', 'company', 'department', 'item-code', 'item-group', 'location', 'pca', 'priority',
            'project', 'project-detail', 'uom', 'vendor'] as $slug) {
            $kasus["$slug edit"] = [$slug, 'edit'];
            $kasus["$slug show"] = [$slug, 'show'];
        }

        return $kasus;
    }

    /** @dataProvider modulProvider */
    #[DataProvider('modulProvider')]
    public function test_id_tidak_ada_404(string $slug, string $aksi): void
    {
        $this->get(route("master.$slug.$aksi", self::ID_TIDAK_ADA))->assertNotFound();
    }

    public function test_baris_terhapus_tetap_bisa_dibuka(): void
    {
        $id = DB::table('master_uom')->insertGetId(['uom_code' => 'KG', 'uom_name' => 'Kilogram', 'deleted_at' => now()]);

        $this->get(route('master.uom.edit', $id))->assertOk()->assertSee('value="KG"', false);
    }
}
