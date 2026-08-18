@php
    $currentUrl = url()->current();

    if (!function_exists('isMeridianUrlActive')) {
        function isMeridianUrlActive($url, $currentUrl) {
            if (empty($url) || $url === '#') return false;
            $urlClean = strtok($url, '?');
            $currentClean = strtok($currentUrl, '?');
            return $urlClean === $currentClean || (strlen($urlClean) > 1 && str_starts_with($currentClean, $urlClean));
        }
    }

    if (!function_exists('isMeridianSubmenuActive')) {
        function isMeridianSubmenuActive($item, $currentUrl) {
            if (empty($item['children'])) return false;
            foreach ($item['children'] as $child) {
                if (isMeridianUrlActive($child['url'] ?? '#', $currentUrl)) return true;
                if (!empty($child['children']) && isMeridianSubmenuActive($child, $currentUrl)) return true;
            }
            return false;
        }
    }
@endphp

@foreach($items as $item)
    @if(($item['type'] ?? '') === 'header')
        <li class="sidebar__group-title uppercase text-xs text-muted-foreground font-bold px-3 pt-3 pb-1 tracking-wider">
            {{ __($item['title']) }}
        </li>
    @elseif(!empty($item['children']))
        @php
            $hasActiveChild = isMeridianSubmenuActive($item, $currentUrl);
        @endphp
        <li class="sidebar__item sidebar__item--has-submenu">
            <details class="sidebar__details group" {{ $hasActiveChild ? 'open' : '' }}>
                <summary class="sidebar__button flex items-center justify-between p-2 rounded-md transition-colors hover:bg-surface-2 cursor-pointer list-none text-foreground select-none {{ $hasActiveChild ? 'font-semibold text-primary' : '' }}">
                    <div class="flex items-center gap-2.5">
                        @if(!empty($item['icon']))
                            <i class="{{ $item['icon'] }} w-4 text-center text-muted-foreground"></i>
                        @else
                            <i class="fas fa-folder w-4 text-center text-muted-foreground"></i>
                        @endif
                        <span class="text-sm">{{ __($item['title']) }}</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform duration-200 group-open:rotate-180"></i>
                </summary>
                <ul class="sidebar__submenu flex flex-col gap-1 ps-6 pt-1 pb-1 list-none m-0">
                    @foreach($item['children'] as $child)
                        @if(!empty($child['children']))
                            @php $hasGrandchildActive = isMeridianSubmenuActive($child, $currentUrl); @endphp
                            <li class="sidebar__item sidebar__item--has-submenu">
                                <details class="sidebar__details group" {{ $hasGrandchildActive ? 'open' : '' }}>
                                    <summary class="sidebar__button flex items-center justify-between p-2 rounded-md transition-colors hover:bg-surface-2 cursor-pointer list-none text-foreground select-none text-xs {{ $hasGrandchildActive ? 'font-semibold text-primary' : '' }}">
                                        <div class="flex items-center gap-2">
                                            @if(!empty($child['icon']))
                                                <i class="{{ $child['icon'] }} w-4 text-center text-muted-foreground"></i>
                                            @else
                                                <i class="fas fa-angle-right w-4 text-center text-muted-foreground"></i>
                                            @endif
                                            <span>{{ __($child['title']) }}</span>
                                        </div>
                                        <i class="fas fa-chevron-down text-xs text-muted-foreground transition-transform duration-200 group-open:rotate-180"></i>
                                    </summary>
                                    <ul class="sidebar__submenu flex flex-col gap-1 ps-4 pt-1 pb-1 list-none m-0">
                                        @foreach($child['children'] as $subChild)
                                            @php $isSubChildActive = isMeridianUrlActive($subChild['url'] ?? '#', $currentUrl); @endphp
                                            <li class="sidebar__item">
                                                <a class="sidebar__button flex items-center gap-2 p-1.5 rounded-md text-xs transition-colors hover:bg-surface-2 {{ $isSubChildActive ? 'active text-primary font-semibold bg-surface-2' : 'text-foreground' }}"
                                                   href="{{ $subChild['url'] }}"
                                                   @if(!empty($subChild['target'])) target="{{ $subChild['target'] }}" @endif>
                                                    @if(!empty($subChild['icon']))
                                                        <i class="{{ $subChild['icon'] }} w-3 text-center"></i>
                                                    @else
                                                        <i class="fas fa-circle text-2xs opacity-40 w-3 text-center"></i>
                                                    @endif
                                                    <span class="flex-1">{{ __($subChild['title']) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </li>
                        @else
                            @php $isChildActive = isMeridianUrlActive($child['url'] ?? '#', $currentUrl); @endphp
                            <li class="sidebar__item">
                                <a class="sidebar__button flex items-center gap-2 p-2 rounded-md text-xs transition-colors hover:bg-surface-2 {{ $isChildActive ? 'active text-primary font-semibold bg-surface-2' : 'text-foreground' }}"
                                   href="{{ $child['url'] }}"
                                   @if(!empty($child['target'])) target="{{ $child['target'] }}" @endif>
                                    @if(!empty($child['icon']))
                                        <i class="{{ $child['icon'] }} w-4 text-center text-muted-foreground"></i>
                                    @else
                                        <i class="fas fa-circle text-2xs opacity-40 w-4 text-center"></i>
                                    @endif
                                    <span class="flex-1">{{ __($child['title']) }}</span>
                                    @if(!empty($child['label']))
                                        <span class="badge badge--sm badge--{{ $child['label_color'] ?? 'primary' }} ms-auto">{{ $child['label'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </details>
        </li>
    @else
        @php
            $isItemActive = isMeridianUrlActive($item['url'] ?? '#', $currentUrl);
        @endphp
        <li class="sidebar__item">
            <a class="sidebar__button flex items-center gap-2.5 p-2 rounded-md transition-colors hover:bg-surface-2 {{ $isItemActive ? 'active text-primary font-semibold bg-surface-2' : 'text-foreground' }}"
               href="{{ $item['url'] }}"
               @if(!empty($item['target'])) target="{{ $item['target'] }}" @endif>
                @if(!empty($item['icon']))
                    <i class="{{ $item['icon'] }} w-4 text-center text-muted-foreground"></i>
                @elseif(!empty($item['icon_color']))
                    <i class="fas fa-circle text-xs text-{{ $item['icon_color'] }} w-4 text-center"></i>
                @else
                    <i class="fas fa-circle text-2xs opacity-40 w-4 text-center"></i>
                @endif
                <span class="flex-1 text-sm">{{ __($item['title']) }}</span>
                @if(!empty($item['label']))
                    <span class="badge badge--sm badge--{{ $item['label_color'] ?? 'primary' }} ms-auto">{{ $item['label'] }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach
