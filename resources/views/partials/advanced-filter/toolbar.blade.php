{{--
    Tombol Filter Lanjutan di atas daftar master (layouts.dashboard.index, opt-in $data['datatable']['advanced_filter']).
    Modal dan skripnya: master::partials.advanced-filter.modal (di stack js). Isi badge/ringkasan diisi skrip itu.
--}}
<button type="button" class="btn btn-sm btn-outline-primary mb-3" data-maf-open aria-haspopup="dialog" aria-controls="maf-modal">
    <i class="fas fa-filter mr-1 me-1" aria-hidden="true"></i>Filter Lanjutan
    <span class="badge badge-primary bg-primary ml-1 ms-1 d-none" data-maf-count></span>
</button>
<button type="button" class="btn btn-sm btn-outline-secondary mb-3 d-none" data-maf-clear title="Hapus filter lanjutan">
    <i class="fas fa-times mr-1 me-1" aria-hidden="true"></i>Hapus Filter
</button>
<div class="maf-summary small mb-2 d-none" data-maf-summary aria-live="polite"></div>
