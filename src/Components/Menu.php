<?php

namespace Bangsamu\Master\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $rawItems = config("adminlte.menu");

        if (empty($rawItems)) {
            $rawItems = config("menu.{$menu}") ?? config("MasterMenu.{$menu}", []);
        }

        $this->items = $this->normalizeMenuItems($rawItems);
    }

    /**
     * Normalize menu items for unified rendering across themes.
     *
     * @param array $items
     * @return array
     */
    protected function normalizeMenuItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Skip topnav items or navbar widgets when rendering sidebar
            if (!empty($item['topnav']) || !empty($item['topnav_right'])) {
                continue;
            }
            if (!empty($item['type']) && in_array($item['type'], ['navbar-search', 'fullscreen-widget', 'sidebar-menu-search'])) {
                continue;
            }

            // Check permissions if 'can' attribute is set
            if (!empty($item['can'])) {
                $can = $item['can'];
                if (function_exists('auth') && Auth::check()) {
                    if (!Auth::user()->can($can)) {
                        continue;
                    }
                }
            }

            // Header item
            if (isset($item['header'])) {
                $normalized[] = [
                    'type' => 'header',
                    'title' => $item['header'],
                ];
                continue;
            }

            // Title / Text resolution
            $title = $item['text'] ?? $item['title'] ?? '';

            // URL resolution
            $url = '#';
            if (!empty($item['url'])) {
                $url = ($item['url'] === '#' || Str::startsWith($item['url'], ['http://', 'https://', '/']))
                    ? $item['url']
                    : url($item['url']);
            } elseif (!empty($item['route'])) {
                $url = Route::has($item['route']) ? route($item['route']) : '#';
            }

            // Submenu handling
            $submenuRaw = $item['submenu'] ?? $item['children'] ?? [];
            $submenu = [];
            if (!empty($submenuRaw)) {
                $submenu = $this->normalizeMenuItems($submenuRaw);
            }

            $type = !empty($submenu) ? 'dropdown' : ($item['type'] ?? 'item');

            $normalized[] = [
                'type' => $type,
                'title' => $title,
                'text' => $title,
                'url' => $url,
                'icon' => $item['icon'] ?? '',
                'icon_color' => $item['icon_color'] ?? '',
                'label' => $item['label'] ?? null,
                'label_color' => $item['label_color'] ?? 'primary',
                'target' => $item['target'] ?? '',
                'children' => $submenu,
                'submenu' => $submenu,
                'raw' => $item,
            ];
        }

        return $normalized;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\Support\Htmlable|\Closure|string
     */
    public function render()
    {
        if (config('app.themes') === '_meridian') {
            return view('master::components.meridian-menu');
        }

        return view('master::components.menu');
    }
}
