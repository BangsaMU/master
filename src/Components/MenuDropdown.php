<?php

namespace Bangsamu\Master\Components;

use Illuminate\View\Component;

class MenuDropdown extends Component
{
    /**
     * The menu dropdown item.
     *
     * @var array
     */
    public $item;

    /**
     * Whether the dropdown is active.
     *
     * @var bool
     */
    public $isActive;

    /**
     * The parent component.
     *
     * @var \App\View\Components\Menu
     */
    public $component;

    /**
     * Create a new component instance.
     *
     * @param array $item
     * @param bool $isActive
     * @param \App\View\Components\Menu $component
     * @return void
     */
    public function __construct($item, $isActive = false, $component = null)
    {
        $this->item = $item;
        $this->isActive = $isActive;
        $this->component = $component;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('master::components.menu-dropdown');
    }
}
