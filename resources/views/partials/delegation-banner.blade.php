{{-- Banner session delegasi di aplikasi Master Data (hub): user datang dari aplikasi lain lewat "Edit di Master Data" --}}
@php($masterDelegation = app(\Bangsamu\Master\Services\MasterScope::class)->delegation())
@if ($masterDelegation)
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="status">
        <div>
            <i class="fas fa-user-shield me-1"></i>
            Mengelola master data untuk <strong>{{ $masterDelegation['app_name'] ?? $masterDelegation['app_code'] }}</strong>
            ({{ $masterDelegation['app_code'] }}) · company
            <strong>{{ $masterDelegation['company_code'] ?? $masterDelegation['company_id'] }}</strong>.
            Hanya data milik aplikasi dan company ini yang dapat diubah.
        </div>
        @if (filled($masterDelegation['back'] ?? null))
            <a href="{{ route('master-launch.back') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke {{ $masterDelegation['app_name'] ?? $masterDelegation['app_code'] }}
            </a>
        @endif
    </div>
@endif
