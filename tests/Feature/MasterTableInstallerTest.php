<?php

namespace Bangsamu\Master\Tests\Feature;

use Bangsamu\Master\Models\MasterUserPosition;
use Bangsamu\Master\Models\UserPosition;
use Bangsamu\Master\Services\MasterTableInstaller;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Auto-create model hanya jalan sekali per proses, jadi tiap test di proses sendiri.
 */
#[RunTestsInSeparateProcesses]
class MasterTableInstallerTest extends TestCase
{
    public function test_membuat_tabel_master_lokal_yang_belum_ada(): void
    {
        $this->assertFalse(Schema::hasTable('master_user_position'));

        $result = app(MasterTableInstaller::class)->install();

        $this->assertContains('master_user_position', $result['created']);
        $this->assertArrayNotHasKey('master_user_position', $result['failed']);
        $this->assertSame(
            ['id', 'position_code', 'position_name', 'created_at', 'updated_at', 'deleted_at'],
            Schema::getColumnListing('master_user_position')
        );
        $this->assertSame(
            ['id', 'item_code_id', 'folder_url', 'thumbnail_url', 'created_at', 'updated_at', 'deleted_at'],
            Schema::getColumnListing('master_item_code_picture')
        );

        // tabel non master_* milik app tidak ikut dibuat
        $this->assertFalse(Schema::hasTable('requisition'));
        $this->assertFalse(Schema::hasTable('routing'));

        // jalan ulang tidak membuat apa-apa lagi
        $this->assertSame([], app(MasterTableInstaller::class)->install()['created']);
    }

    public function test_model_koneksi_db_master_tidak_dipakai_installer(): void
    {
        $models = app(MasterTableInstaller::class)->localMasterModels();

        $this->assertArrayHasKey(UserPosition::class, $models);
        $this->assertArrayNotHasKey(MasterUserPosition::class, $models);
        $this->assertSame(count($models), count(array_unique($models)));
    }

    public function test_command_install_tables(): void
    {
        $this->artisan('master:install-tables')
            ->expectsOutputToContain('master_user_position');

        $this->assertTrue(Schema::hasTable('master_user_position'));
    }
}
