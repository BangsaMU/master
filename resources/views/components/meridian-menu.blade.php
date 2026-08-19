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

    if (!function_exists('renderMeridianSvgIcon')) {
        function renderMeridianSvgIcon($iconClass = '', $sizeClass = 'w-4 h-4', $colorClass = 'text-muted-foreground') {
            $icon = strtolower($iconClass ?? '');

            if (str_contains($icon, 'tachometer') || str_contains($icon, 'dashboard') || str_contains($icon, 'gauge')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>';
            }
            if (str_contains($icon, 'user')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
            }
            if (str_contains($icon, 'lock') || str_contains($icon, 'key')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
            }
            if (str_contains($icon, 'file') || str_contains($icon, 'copy') || str_contains($icon, 'document')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>';
            }
            if (str_contains($icon, 'folder')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>';
            }
            if (str_contains($icon, 'cog') || str_contains($icon, 'gear') || str_contains($icon, 'setting')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
            }
            if (str_contains($icon, 'share') || str_contains($icon, 'branch') || str_contains($icon, 'node')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>';
            }
            if (str_contains($icon, 'sign-out') || str_contains($icon, 'logout')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
            }
            if (str_contains($icon, 'list') || str_contains($icon, 'table') || str_contains($icon, 'bars')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="13" x2="3.01" y2="18"/></svg>';
            }
            if (str_contains($icon, 'building') || str_contains($icon, 'company')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>';
            }
            if (str_contains($icon, 'calendar') || str_contains($icon, 'clock')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
            }
            if (str_contains($icon, 'headset') || str_contains($icon, 'ticket') || str_contains($icon, 'support') || str_contains($icon, 'help')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>';
            }
            if (str_contains($icon, 'info') || str_contains($icon, 'exclamation')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
            }
            if (str_contains($icon, 'home')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
            }
            if (str_contains($icon, 'box') || str_contains($icon, 'package')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
            }
            if (str_contains($icon, 'shield') || str_contains($icon, 'security')) {
                return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' '.$colorClass.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
            }

            return '<svg xmlns="http://www.w3.org/2000/svg" class="'.$sizeClass.' opacity-60 '.$colorClass.'" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>';
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
                        {!! renderMeridianSvgIcon($item['icon'] ?? 'folder', 'w-4 h-4', 'text-muted-foreground') !!}
                        <span class="text-sm">{{ __($item['title']) }}</span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <ul class="sidebar__submenu flex flex-col gap-1 ps-6 pt-1 pb-1 list-none m-0">
                    @foreach($item['children'] as $child)
                        @if(!empty($child['children']))
                            @php $hasGrandchildActive = isMeridianSubmenuActive($child, $currentUrl); @endphp
                            <li class="sidebar__item sidebar__item--has-submenu">
                                <details class="sidebar__details group" {{ $hasGrandchildActive ? 'open' : '' }}>
                                    <summary class="sidebar__button flex items-center justify-between p-2 rounded-md transition-colors hover:bg-surface-2 cursor-pointer list-none text-foreground select-none text-xs {{ $hasGrandchildActive ? 'font-semibold text-primary' : '' }}">
                                        <div class="flex items-center gap-2">
                                            {!! renderMeridianSvgIcon($child['icon'] ?? '', 'w-3.5 h-3.5', 'text-muted-foreground') !!}
                                            <span>{{ __($child['title']) }}</span>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </summary>
                                    <ul class="sidebar__submenu flex flex-col gap-1 ps-4 pt-1 pb-1 list-none m-0">
                                        @foreach($child['children'] as $subChild)
                                            @php $isSubChildActive = isMeridianUrlActive($subChild['url'] ?? '#', $currentUrl); @endphp
                                            <li class="sidebar__item">
                                                <a class="sidebar__button flex items-center gap-2 p-1.5 rounded-md text-xs transition-colors hover:bg-surface-2 {{ $isSubChildActive ? 'active text-primary font-semibold bg-surface-2' : 'text-foreground' }}"
                                                   href="{{ $subChild['url'] }}"
                                                   @if(!empty($subChild['target'])) target="{{ $subChild['target'] }}" @endif>
                                                    {!! renderMeridianSvgIcon($subChild['icon'] ?? '', 'w-3 h-3', $isSubChildActive ? 'text-primary' : 'text-muted-foreground') !!}
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
                                    {!! renderMeridianSvgIcon($child['icon'] ?? '', 'w-3.5 h-3.5', $isChildActive ? 'text-primary' : 'text-muted-foreground') !!}
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
                {!! renderMeridianSvgIcon($item['icon'] ?? '', 'w-4 h-4', $isItemActive ? 'text-primary' : 'text-muted-foreground') !!}
                <span class="flex-1 text-sm">{{ __($item['title']) }}</span>
                @if(!empty($item['label']))
                    <span class="badge badge--sm badge--{{ $item['label_color'] ?? 'primary' }} ms-auto">{{ $item['label'] }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach
