<?php

namespace Bangsamu\Master\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use JeroenNoten\LaravelAdminLte\Menu\Builder as MenuBuilder;

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
        $rawItems = [];

        if (class_exists(MenuBuilder::class) && class_exists(BuildingMenu::class)) {
            try {
                $filters = config('adminlte.filters', []);
                $resolvedFilters = array_map([app(), 'make'], $filters);
                $builder = new MenuBuilder($resolvedFilters);

                $staticMenu = config('adminlte.menu', []);
                if (is_array($staticMenu)) {
                    $builder->add(...$staticMenu);
                }

                // Dispatch BuildingMenu event so EventServiceProvider listeners run every time
                event(new BuildingMenu($builder));

                $rawItems = $builder->menu;
            } catch (\Throwable $e) {
                $rawItems = [];
            }
        }

        if (empty($rawItems) && app()->bound('adminlte')) {
            try {
                $rawItems = app('adminlte')->menu('sidebar');
                if (empty($rawItems)) {
                    $rawItems = app('adminlte')->menu();
                }
            } catch (\Throwable $e) {
                $rawItems = [];
            }
        }

        if (empty($rawItems)) {
            $rawItems = config("adminlte.menu", []);
        }

        if (empty($rawItems)) {
            $rawItems = config("menu.{$menu}") ?? config("MasterMenu.{$menu}", []);
        }

        $this->items = $this->normalizeMenuItems($rawItems);
    }

    /**
     * Normalize menu items for unified rendering across themes, removing dummy anchors and duplicates.
     *
     * @param array $items
     * @param array $seenKeys
     * @param array $seenSignatures
     * @return array
     */
    protected function normalizeMenuItems(array $items, array &$seenKeys = [], array &$seenSignatures = []): array
    {
        $normalized = [];

        foreach ($items as $item) {
            // Support raw string headers (e.g. 'MASTER', 'CMS Menu')
            if (is_string($item)) {
                $headerTitle = trim($item);
                $headerSig = 'header|' . strtolower($headerTitle);
                if (in_array($headerSig, $seenSignatures, true)) {
                    continue;
                }
                $seenSignatures[] = $headerSig;
                $normalized[] = [
                    'type' => 'header',
                    'title' => $headerTitle,
                ];
                continue;
            }

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

            // Header item defined as array ['header' => 'Title']
            if (isset($item['header'])) {
                $headerTitle = trim($item['header']);
                $headerSig = 'header|' . strtolower($headerTitle);
                if (in_array($headerSig, $seenSignatures, true)) {
                    continue;
                }
                $seenSignatures[] = $headerSig;
                $normalized[] = [
                    'type' => 'header',
                    'title' => $headerTitle,
                ];
                continue;
            }

            // Check permissions if 'can' attribute is set
            if (!empty($item['can'])) {
                $can = $item['can'];
                if (function_exists('auth') && Auth::check()) {
                    $canList = is_array($can) ? $can : [$can];
                    $hasPermission = false;
                    foreach ($canList as $perm) {
                        if (function_exists('checkPermission')) {
                            if (checkPermission($perm)) {
                                $hasPermission = true;
                                break;
                            }
                        } else {
                            try {
                                $user = Auth::user();
                                if (method_exists($user, 'hasRole') && $user->hasRole($perm)) {
                                    $hasPermission = true;
                                    break;
                                }
                                if ($user->can($perm)) {
                                    $hasPermission = true;
                                    break;
                                }
                            } catch (\Throwable $e) {
                                // Ignore non-existent permission in database
                            }
                        }
                    }
                    if (!$hasPermission) {
                        continue;
                    }
                }
            }

            // Check for duplicate key
            if (!empty($item['key'])) {
                if (in_array($item['key'], $seenKeys, true)) {
                    continue;
                }
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
                if (is_array($item['route'])) {
                    $routeName = $item['route'][0] ?? '';
                    $routeParams = $item['route'][1] ?? [];
                    $url = Route::has($routeName) ? route($routeName, $routeParams) : '#';
                } else {
                    $url = Route::has($item['route']) ? route($item['route']) : '#';
                }
            }

            // Submenu handling
            $submenuRaw = $item['submenu'] ?? $item['children'] ?? [];
            $submenu = [];
            if (!empty($submenuRaw)) {
                $submenu = $this->normalizeMenuItems($submenuRaw, $seenKeys, $seenSignatures);
            }

            // Skip dummy anchor keys (e.g. cms-item, module-item, master-item) used only for addBefore placement
            if (!empty($item['key']) && Str::endsWith($item['key'], '-item') && empty($submenu)) {
                continue;
            }

            // Skip items with no URL, no route, no submenu, and no icon
            if (($url === '#' || empty($url)) && empty($submenu) && empty($item['icon'])) {
                continue;
            }

            // Check for duplicate signature (Title + URL)
            $signature = strtolower($title) . '|' . strtolower($url);
            if ($url !== '#' && in_array($signature, $seenSignatures, true)) {
                continue;
            }

            // Mark key and signature as seen
            if (!empty($item['key'])) {
                $seenKeys[] = $item['key'];
            }
            if ($url !== '#') {
                $seenSignatures[] = $signature;
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
