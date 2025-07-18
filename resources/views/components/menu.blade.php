<!-- resources/views/components/menu.blade.php -->
@php
use App\Helpers\MenuHelper;
@endphp
<ul class="navbar-nav">
@foreach($items as $item)
    @if(MenuHelper::userHasAnyPermission($item))
        @if($item['type'] === 'item')
            <x-menu-item :item="$item" :is-active="MenuHelper::isActive($item)" />
        @elseif($item['type'] === 'dropdown')
            <x-menu-dropdown :item="$item" :is-active="MenuHelper::isDropdownActive($item)" />
        @endif
    @else
        @if($item['type'] === 'divider' && \App\Facades\Settings::get('appearance.sidebar', false))
            <li class="nav-link">
                <div class="hr-text m-1">{{ $item['title'] }}</div>
            </li>
        @endif
    @endif
@endforeach
</ul>
