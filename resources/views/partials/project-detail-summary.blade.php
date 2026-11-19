{{--
    Card ringkasan di atas daftar Project Detail (hub, ProjectDetailController::index -> $data['page']['detail_summary'],
    dihitung ProjectOwnerDetail::detailSummary): baris detail aktif per company (termasuk company yang sudah dihapus) dan
    baris bermasalah. Tombol filter mengirim parameter `masalah` (company / project) lewat window.masterListParams ke
    ajax daftar; `?masalah=` di URL halaman langsung memilih filternya (tautan dari card daftar Project).
--}}
@php
    $ds = $data['page']['detail_summary'];
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $companyProblem = $ds['company_deleted'] + $ds['company_missing'];
    $dsProblems = \Bangsamu\Master\Services\ProjectOwnerDetail::DETAIL_PROBLEMS;
    $dsInitial = in_array(request()->query('masalah'), $dsProblems, true) ? request()->query('masalah') : '';
@endphp
<div class="row mb-3" id="project-detail-summary">
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Project Detail aktif</div>
                <div class="fs-3 fw-bold" data-summary="detail_total">{{ $nf($ds['detail_total']) }}</div>
                <div class="text-muted small">semua company dan project, termasuk yang sudah terhapus</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Company bermasalah</div>
                <div @class(['fs-3 fw-bold', 'text-danger' => $companyProblem > 0]) data-summary="company_problem">{{ $nf($companyProblem) }}</div>
                <div class="text-muted small">
                    {{ $nf($ds['company_deleted']) }} memakai company yang sudah dihapus, {{ $nf($ds['company_missing']) }} company-nya tidak ada di master
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Project bermasalah</div>
                <div @class(['fs-3 fw-bold', 'text-danger' => $ds['project_problem'] > 0]) data-summary="project_problem">{{ $nf($ds['project_problem']) }}</div>
                <div class="text-muted small">project-nya sudah dihapus atau tidak ada di master</div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-0">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <div class="fw-bold">Project Detail aktif per company</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Filter baris bermasalah" data-detail-problem-filter>
                        <button type="button" @class(['btn btn-outline-secondary', 'active' => $dsInitial === '']) data-problem="">Semua</button>
                        <button type="button" @class(['btn btn-outline-danger', 'active' => $dsInitial === 'company']) data-problem="company">Company bermasalah ({{ $nf($companyProblem) }})</button>
                        <button type="button" @class(['btn btn-outline-danger', 'active' => $dsInitial === 'project']) data-problem="project">Project bermasalah ({{ $nf($ds['project_problem']) }})</button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 14rem; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-2" style="width:100%">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th class="text-end text-right" style="width: 12rem;">Project Detail aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ds['companies'] as $company)
                                <tr @class(['table-danger' => $company['deleted']]) data-summary-company="{{ $company['code'] }}">
                                    <td>
                                        <strong>{{ $company['code'] }}</strong> {{ $company['name'] }}
                                        @if ($company['deleted'])
                                            <span class="badge bg-danger badge-danger text-bg-danger ms-1 ml-1">company sudah dihapus</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-right">{{ $nf($company['detail']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">Belum ada Project Detail aktif.</td></tr>
                            @endforelse
                            @if ($ds['company_missing'] > 0)
                                <tr class="table-danger" data-summary-company="-">
                                    <td><em>Company tidak ada di master</em></td>
                                    <td class="text-end text-right">{{ $nf($ds['company_missing']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <ul class="small mb-0 ps-3 pl-3" data-summary="notes">
                    @if ($companyProblem > 0)
                        <li class="text-danger">
                            {{ $nf($companyProblem) }} baris memakai company yang sudah dihapus atau tidak ada di master. Klik
                            <em>Company bermasalah</em>, lalu <em>Pindah company</em> di kolom Action (admin Master Data).
                        </li>
                    @endif
                    @if ($ds['project_problem'] > 0)
                        <li class="text-danger">
                            {{ $nf($ds['project_problem']) }} baris milik project yang sudah dihapus atau tidak ada: pulihkan
                            project-nya, atau hapus baris itu.
                        </li>
                    @endif
                    @if ($companyProblem === 0 && $ds['project_problem'] === 0)
                        <li class="text-muted">Semua baris aktif memakai company dan project yang masih ada di master.</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        // filter baris bermasalah: parameter `masalah` ikut setiap request ajax daftar (layout: window.masterListParams)
        (function() {
            var group = document.querySelector('[data-detail-problem-filter]');
            if (!group) {
                return;
            }
            var current = @json($dsInitial);
            window.masterListParams = function() {
                return current ? { masalah: current } : {};
            };
            group.addEventListener('click', function(e) {
                var btn = e.target.closest('button[data-problem]');
                if (!btn) {
                    return;
                }
                current = btn.getAttribute('data-problem');
                group.querySelectorAll('button[data-problem]').forEach(function(b) {
                    b.classList.toggle('active', b === btn);
                });
                if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(tableName)) {
                    jQuery(tableName).DataTable().ajax.reload(); // kembali ke halaman 1
                }
            });
        })();
    </script>
@endpush
