<?php

namespace Bangsamu\Master\Components;

use Illuminate\View\Component;
use Illuminate\Database\Eloquent\Collection;

class Select2Ajax extends Component
{
    public string $name;
    public string $model;
    public string $label;
    public bool $multiple;
    public Collection $initialSelection;

    public function __construct(
        string $name,
        string $model,
        string $label,
        bool $multiple = false,
        Collection $initialSelection = null
    ) {
        $this->name = $name;
        $this->model = $model;
        $this->label = $label;
        $this->multiple = $multiple;
        $this->initialSelection = $initialSelection ?? collect();
    }

    public function render()
    {
        return view('master::components.select2-ajax');
    }
}
