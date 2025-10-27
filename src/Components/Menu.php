<?php
// packages/bangsamu/master/src/Components/Menu.php
namespace Bangsamu\Master\Components;

use Illuminate\View\Component;

class Menu extends Component
{
    /**
     * Menu items to be rendered.
     *
     * @var array
     */
    public $items;

    /**
     * Create a new component instance.
     *
     * @param string $menu
     * @return void
     */
    public function __construct($menu = 'main_menu')
    {
        //  dd($menu,config());
        $this->items = config("menu.{$menu}") ?? config("MasterMenu.{$menu}", []);

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\Support\Htmlable|\Closure|string
     */
    public function render()
    {
        return view('master::components.menu');
    }
}
