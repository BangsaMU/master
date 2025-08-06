@props(['item', 'isActive'])

@php
    use Bangsamu\LibraryClay\Controllers\LibraryClayController;
    use Illuminate\Support\Str;
@endphp

{{-- The top-level dropdown item. The $isActive prop is passed from the parent view. --}}
<li class="nav-item dropdown {{ $isActive ? 'active' : '' }}">
    <a class="nav-link dropdown-toggle" href="#navbar-{{ Str::slug($item['title']) }}"
        data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">

        @if(!empty($item['icon']))
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <i class="{{ $item['icon'] }}"></i>
            </span>
        @endif
        <span class="nav-link-title">{{ $item['title'] }}</span>
    </a>

    <div class="dropdown-menu">
        <div class="dropdown-menu-columns">
            <div class="dropdown-menu-column">
                @foreach($item['children'] as $child)

                    {{-- FIX #1: Check for children directly instead of a 'type' key. --}}
                    @if(!empty($child['children']))
                        {{-- This child is a nested dropdown ("dropend") --}}
                        <div class="dropend">
                            {{-- FIX #2: The nested dropdown toggle now checks its own active state. --}}
                            <a class="dropdown-item dropdown-toggle {{ LibraryClayController::isDropdownActive($child) ? 'active' : '' }}"
                               href="#sidebar-{{ Str::slug($child['title']) }}"
                               data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">

                                @if(!empty($child['icon']))
                                    <i class="{{ $child['icon'] }} me-1"></i>
                                @endif
                                {{ $child['title'] }}
                            </a>
                            <div class="dropdown-menu">
                                @foreach($child['children'] as $subChild)
                                    {{-- FIX #3: Each grandchild link checks its own active state. --}}
                                    <a href="{{ LibraryClayController::getUrl($subChild) }}"
                                       class="dropdown-item {{ LibraryClayController::isActive($subChild) ? 'active' : '' }}"
                                       @if(!empty($subChild['target'])) target="{{ $subChild['target'] }}" @endif>

                                        @if(!empty($subChild['icon']))
                                            <i class="{{ $subChild['icon'] }} me-1"></i>
                                        @endif
                                        {{ $subChild['title'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- This child is a simple link --}}
                        {{-- FIX #4: Each child link checks its own active state. --}}
                        <a class="dropdown-item {{ LibraryClayController::isActive($child) ? 'active' : '' }}"
                           href="{{ LibraryClayController::getUrl($child) }}"
                           @if(!empty($child['target'])) target="{{ $child['target'] }}" @endif>

                            @if(!empty($child['icon']))
                                <i class="{{ $child['icon'] }} me-1"></i>
                            @endif
                            {{ $child['title'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</li>
