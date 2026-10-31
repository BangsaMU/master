<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterEditLink;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

/**
 * MASTER_DIRECT_EDIT=false + MASTER_EDIT_URL: tombol Edit/Tambah di daftar master membuka aplikasi Master Data, hanya
 * untuk is_admin atau permission per modul (create_/update_ + nama permission aplikasi Master Data).
 */
class MasterEditLinkTest extends TestCase
{
    /** @var list<string> izin user yang sedang login */
    private array $abilities = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['MasterCrudConfig.MASTER_DIRECT_EDIT' => false, 'MasterCrudConfig.MASTER_EDIT_URL' => 'http://192.168.20.187:9002/']);
        Gate::before(fn ($user, string $ability) => in_array($ability, $this->abilities, true) ? true : null);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function actingWith(array $abilities): void
    {
        $this->abilities = $abilities;
        config(['test.allowed_permissions' => $abilities]);
        $this->actingAs(User::create(['name' => 'Penguji', 'email' => uniqid('edit', true).'@meindo.test']));
    }

    public function test_urls_point_to_master_data_app_with_same_route_path(): void
    {
        $this->assertSame('http://192.168.20.187:9002/master/item-code/5944/edit', MasterEditLink::url('item-code', 5944));
        $this->assertSame('http://192.168.20.187:9002/master/category/create', MasterEditLink::url('category'));
        $this->assertSame(['id' => 'create', 'title' => 'Tambah di Master Data', 'icon' => 'btn-primary',
            'url' => 'http://192.168.20.187:9002/master/uom/create', 'target' => '_blank'], MasterEditLink::createButton('uom'));
    }

    public function test_permission_names_follow_master_data_app(): void
    {
        $this->assertSame('update_item', MasterEditLink::permission('item-code', 'update'));
        $this->assertSame('create_itemgroup', MasterEditLink::permission('item-group', 'create'));
        $this->assertSame('update_uom', MasterEditLink::permission('uom', 'update'));

        config(['MasterCrudConfig.MASTER_EDIT_PERMISSIONS' => ['item-code' => 'item_code']]);
        $this->assertSame('update_item_code', MasterEditLink::permission('item-code', 'update'));
    }

    public function test_buttons_follow_module_permission(): void
    {
        $this->actingWith(['update_item', 'create_uom']);

        $button = MasterEditLink::rowButton('item-code', 5944);
        $this->assertStringContainsString('href="http://192.168.20.187:9002/master/item-code/5944/edit"', $button);
        $this->assertStringContainsString('target="_blank"', $button);
        $this->assertFalse(MasterEditLink::allowed('item-code', 'create'));
        $this->assertTrue(MasterEditLink::allowed('uom', 'create'));
        $this->assertFalse(MasterEditLink::allowed('uom', 'update'));
        $this->assertSame('', MasterEditLink::rowButton('company', 1));
        $this->assertFalse(MasterEditLink::allowed('company', 'create'));
    }

    public function test_admin_and_optional_all_modules_permission(): void
    {
        $this->actingWith(['is_admin']);
        $this->assertTrue(MasterEditLink::allowed('company', 'create'));

        $this->actingWith(['edit_semua_master']);
        $this->assertFalse(MasterEditLink::allowed('company'));
        config(['MasterCrudConfig.MASTER_EDIT_PERMISSION' => 'edit_semua_master']);
        $this->assertTrue(MasterEditLink::allowed('company'));
    }

    public function test_no_button_when_direct_edit_is_on_or_url_is_empty(): void
    {
        $this->actingWith(['is_admin']);

        config(['MasterCrudConfig.MASTER_DIRECT_EDIT' => true]);
        $this->assertFalse(MasterEditLink::enabled());
        $this->assertSame('', MasterEditLink::rowButton('category', 1));

        config(['MasterCrudConfig.MASTER_DIRECT_EDIT' => false, 'MasterCrudConfig.MASTER_EDIT_URL' => null]);
        $this->assertFalse(MasterEditLink::enabled());
    }
}
