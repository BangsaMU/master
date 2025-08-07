<?php
// config/menu.php
return [
    'main_menu' => [
        [
            'type' => 'item',
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'ti ti-home',
            'permissions' => ['access dashboard'],
        ],
        [
            'type' => 'dropdown',
            'title' => 'User Management',
            'icon' => 'ti ti-users',
            'active' => 'users*',
            'permissions' => ['view users'],
            'children' => [
                [
                    'title' => 'All Users',
                    'route' => 'users.index',
                    'icon' => 'ti ti-users',
                ],
                [
                    'title' => 'User Roles',
                    'url' => config('app.url').'/roles', // Using direct URL
                    'active' => 'roles*',
                    'icon' => 'ti ti-adjustments',
                ]
            ],
        ],
        [
            'type' => 'item',
            'title' => 'Settings',
            'url' => config('app.url').'/settings', // Using direct URL
            'icon' => 'ti ti-settings',
            'permissions' => ['manage settings']
        ],
        [
            'type' => 'dropdown',
            'title' => 'Master Management',
            'icon' => 'ti ti-database',
            'permissions' => ['view master data'],
            'children' => [
                [
                    'title' => 'Item Code',
                    'url' => config('app.url').'/master/item-code',
                    'active' => 'master/item-code',
                    'icon' => 'ti ti-barcode',   // or ti ti-scan
                ],
                [
                    'title' => 'UoM', // Unit of Measure
                    'url' => config('app.url').'/master/uom',
                    'active' => 'master/uom',
                    'icon' => 'ti ti-ruler-measure', // or ti ti-box-model-2
                ],
                [
                    'title' => 'Category',
                    'url' => config('app.url').'/master/category',
                    'active' => 'master/category',
                    'icon' => 'ti ti-category-2', // or ti ti-tags
                ],
                [
                    'title' => 'Item Group',
                    'url' => config('app.url').'/master/item-group',
                    'active' => 'master/item-group',
                    'icon' => 'ti ti-clipboard-list', // or ti ti-folders
                ],
                [
                    'title' => 'Location',
                    'url' => config('app.url').'/master/location',
                    'active' => 'master/location',
                    'icon' => 'ti ti-map-pin',
                ],
                [
                    'title' => 'Company',
                    'url' => config('app.url').'/master/company',
                    'active' => 'master/company',
                    'icon' => 'ti ti-building-skyscraper', // or ti ti-building
                ],
                [
                    'title' => 'Project',
                    'url' => config('app.url').'/master/project',
                    'active' => 'master/project',
                    'icon' => 'ti ti-briefcase',
                ],
                [
                    'title' => 'Projects Detail',
                    'url' => config('app.url').'/master/project-detail',
                    'active' => 'master/project-detail',
                    'icon' => 'ti ti-list-details', // or ti ti-clipboard-text
                ],
                [
                    'title' => 'Employee',
                    'url' => config('app.url').'/master/employee',
                    'active' => 'master/employee',
                    'icon' => 'ti ti-users', // or ti ti-user-circle
                ],
                [
                    'title' => 'Vendor',
                    'url' => config('app.url').'/master/vendor',
                    'active' => 'master/vendor',
                    'icon' => 'ti ti-truck-delivery', // or ti ti-building-store
                ],
            ],
        ],
        [
            'type' => 'divider',
            'title' => 'Main Menu',
        ],
        [
            'type' => 'item',
            'title' => 'Activity Logs',
            'route' => 'activity-logs.index',
            'icon' => 'ti ti-activity',
            'permissions' => ['view activity logs']
        ],
        [
            'type' => 'item',
            'title' => 'Document Routing',
            'route' => 'requisitions.index',
            'active' => 'requisitions*',
            'icon' => 'ti ti-files',
            'permissions' => ['view requisitions']
        ],
        [
            'type' => 'item',
            'title' => 'Routing',
            'route' => 'routings.index',
            'icon' => 'ti ti-route',
            'active' => ['routings*', 'routing*'],
            'permissions' => ['view routings']
        ],
        [
            'type' => 'item',
            'title' => 'Menu',
            'icon' => 'ti ti-chart-bar',
        ],
        [
            'type' => 'dropdown',
            'title' => 'Menu with Submenus',
            'icon' => 'ti ti-chart-bar',
            'children' => [
                [
                    'title' => 'Sub Menu 1',
                    // 'route' => 'users.index', // Using route name
                    'icon' => 'ti ti-users',
                ],
                [
                    'title' => 'Sub Menu 2',
                    // 'url' => config('app.url').'/users/create', // Using direct URL
                    'icon' => 'ti ti-user-plus',
                ],
                [
                    'title' => 'Sub Menu 3',
                    // 'url' => config('app.url').'/roles', // Using direct URL
                    'icon' => 'ti ti-adjustments',
                ]
            ],
        ],
    ],
];
