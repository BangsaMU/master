{{--
    Bar filter tetap di atas daftar master (layouts.dashboard.index, opt-in $data['datatable']['fixed_filter'], mis.
    Support\ProjectListFilter::config): filter dipilih lewat URL halaman (mis. ?project_id= dari tautan Project Code di
    daftar Project Detail), `params` ikut setiap request ajax daftar, tombol hapus filter = buka ulang URL tanpa
    parameternya. Teks: text, label, badge (opsional), note (opsional), clear_text, clear_url.
--}}
@php
    $fixedFilter = $data['datatable']['fixed_filter'];
@endphp
<div class="mb-3" data-fixed-filter-bar>
    <div class="alert alert-warning d-flex flex-wrap align-items-center py-2 px-3 mb-0" role="status">
        <span class="me-3 mr-3">
            <i class="fas fa-filter me-1 mr-1" aria-hidden="true"></i>{{ $fixedFilter['text'] }}
            <strong data-fixed-filter-label>{{ $fixedFilter['label'] }}</strong>
            @if (! empty($fixedFilter['badge']))
                <span class="badge bg-danger badge-danger text-bg-danger ms-1 ml-1" data-fixed-filter-badge>{{ $fixedFilter['badge'] }}</span>
            @endif
            @if (! empty($fixedFilter['note']))
                <span class="d-block small mt-1" data-fixed-filter-note>{{ $fixedFilter['note'] }}</span>
            @endif
        </span>
        <a href="{{ $fixedFilter['clear_url'] }}" class="btn btn-sm btn-outline-secondary ms-auto ml-auto" data-fixed-filter-clear>
            <i class="fas fa-times me-1 mr-1" aria-hidden="true"></i>{{ $fixedFilter['clear_text'] }}
        </a>
    </div>
</div>
