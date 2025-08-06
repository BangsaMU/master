<!-- resources/views/components/menu-item.blade.php -->
@php
    use Bangsamu\LibraryClay\Controllers\LibraryClayController;
@endphp

<li class="nav-item {{ $isActive ? 'active' : '' }}">
    <a class="nav-link"
       href="{{ LibraryClayController::getUrl(@$item) }}"
       @if(!empty(@$item['target'])) target="{{ @$item['target'] }}" @endif>
        @if(!empty(@$item['icon']))
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <i class="{{ @$item['icon'] }}"></i>
        </span>
        @endif
        <span class="nav-link-title">{{ @$item['title'] }}</span>
        @if(!empty(@$item['badge']))
        <span class="badge {{ @$item['badge']['class'] ?? 'bg-primary' }} ms-auto">{{ @$item['badge']['text'] }}</span>
        @endif
    </a>
</li>
