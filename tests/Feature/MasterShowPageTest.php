<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * Tombol View di daftar master membuka route `master.{modul}.show`. Dulu DepartmentController tidak punya show()
 * sehingga View Department = 500 (BadMethodCallException).
 */
class MasterShowPageTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    public function test_halaman_resource_master_punya_method_controller(): void
    {
        $hilang = [];
        foreach (Route::getRoutes() as $route) {
            $nama = (string) $route->getName();
            if (! preg_match('/^master\.[\w-]+\.(index|create|show|edit)$/', $nama) || ! str_contains($route->getActionName(), '@')) {
                continue;
            }
            [$controller, $method] = explode('@', $route->getActionName());
            if (! method_exists($controller, $method)) {
                $hilang[] = "$nama -> {$route->getActionName()}";
            }
        }

        $this->assertSame([], $hilang);
    }

    public function test_view_department_tampil_hanya_baca(): void
    {
        $id = DB::table('master_department')->insertGetId(['department_code' => 'ACC', 'department_name' => 'Accounting']);

        $html = $this->get(route('master.department.show', $id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input readonly type="text" name="department_code"[^>]*value="ACC"/', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }
}
