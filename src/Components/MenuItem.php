<?php
// app/View/Components/MenuItem.php

namespace Bangsamu\Master\Components;

use Illuminate\View\Component;

class MenuItem extends Component
{
    /**
     * The menu item.
     *
     * @var array
     */
    public $item;

    /**
     * Whether the item is active.
     *
     * @var bool
     */
    public $isActive;

    /**
     * Create a new component instance.
     *
     * @param array $item
     * @param bool $isActive
     * @return void
     */
    public function __construct($item, $isActive = false)
    {
        $this->item = $item;
        $this->isActive = $isActive;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('master::components.menu-item');
    }
}
